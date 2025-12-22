<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Shop\Client;
use App\Services\Sms\EsputnikSms;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class PhoneRegisterController extends Controller
{
    private function normalize(string $raw): string
    {
        $d = preg_replace('/\D+/', '', $raw);
        if (str_starts_with($d,'0')) $d = '38'.$d;
        if (strlen($d) === 9)       $d = '380'.$d;
        if (!str_starts_with($d,'380') && strlen($d) >= 10) $d = '380'.substr($d, -9);
        return $d;
    }

    // Шаг 1: отправка кода для регистрации
    public function sendCode(Request $r, EsputnikSms $sms)
    {
        $digits = $this->normalize((string) $r->input('phone'));
        $r->merge(['phone' => $digits]);

        // базовая валидация только телефона (остальные поля проверим на verify)
        $r->validate([
            'phone' => ['required', 'regex:/^380\d{9}$/'],
        ], [
            'phone.required' => st('auth.enter_phone', 'Вкажіть номер телефону'),
            'phone.regex'    => st('auth.invalid_phone_format', 'Невірний формат телефону'),
        ]);

        // если такой телефон уже есть — ошибка
        if (Client::where('phone', $digits)->exists()) {
            return response()->json([
                'ok'      => false,
                'message' => st('auth.phone_already_registered', 'Номер вже зареєстровано. Увійдіть або відновіть доступ.'),
            ], 422);
        }

        $ttl         = (int) config('services.sms.ttl', 180);
        $resendAfter = (int) config('services.sms.resend_in', 60);
        $key         = 'reg:' . $digits;
        $lockKey     = $key . ':resend_lock';

        // 1) если стоит лок — запрещаем повторную отправку
        if (Cache::has($lockKey)) {
            return response()->json([
                'ok'      => false,
                'message' => st('auth.resend_unavailable', 'Повторна відправка поки недоступна.'),
            ], 422);
        }

        // 2) готовим код и пейлоад
        $state    = Cache::get($key, []);
        $code     = $state['code']
            ?? (config('services.sms.fake')
                ? (string) config('services.sms.test_code', '1234')
                : (string) random_int(1000, 9999));

        $payload = [
            'name'                  => trim((string) $r->input('name')),
            'email'                 => trim((string) $r->input('email')) ?: null,
            'password'              => (string) $r->input('password'),
            'password_confirmation' => (string) $r->input('password_confirmation'),
            'phone'                 => $digits,
        ];

        Cache::put($key, [
            'code'     => $code,
            'attempts' => (int) ($state['attempts'] ?? 0),
            'payload'  => $payload,
        ], $ttl);

        // 3) FAKE — сразу успех и ставим лок
        if (config('services.sms.fake')) {
            Log::info('FAKE-SMS reg', ['phone' => $digits, 'code' => $code, 'key' => $key]);
            Cache::put($lockKey, 1, $resendAfter);

            return response()->json([
                'ok'        => true,
                'ttl'       => $ttl,
                'resend_in' => $resendAfter,
                'phone'     => $digits,
                'debug_code'=> $code,
            ]);
        }

        // 4) Реальная отправка
        $resp = $sms->sendCode($digits, $code);
        if (($resp['status'] ?? 500) >= 300) {
            // не ставим лок, чтобы можно было попробовать ещё раз
            return response()->json([
                'ok'      => false,
                'message' => st('auth.send_error', 'Не вдалося відправити SMS.') . ' ' . st('auth.try_later', 'Спробуйте пізніше.'),
            ], 422);
        }

        // 5) успешная отправка → ставим лок
        Cache::put($lockKey, 1, $resendAfter);

        return response()->json([
            'ok'        => true,
            'ttl'       => $ttl,
            'resend_in' => $resendAfter,
            'phone'     => $digits,
        ]);
    }

    // Шаг 2: проверка кода и создание аккаунта
    public function verify(Request $r)
    {
        $digits = $this->normalize((string) $r->input('phone'));
        $r->merge(['phone' => $digits]);

        $r->validate([
            'phone' => ['required', 'regex:/^380\d{9}$/'],
            'code'  => ['required', 'digits:4'],
        ], [
            'phone.required' => st('auth.enter_phone', 'Вкажіть номер телефону'),
            'phone.regex'    => st('auth.invalid_phone_format', 'Невірний формат телефону'),
            'code.required'  => st('auth.enter4', 'Введіть 4 цифри'),
            'code.digits'    => st('auth.enter4', 'Введіть 4 цифри'),
        ]);

        $key   = 'reg:' . $digits;
        $state = Cache::get($key);
        if (!$state) {
            return response()->json([
                'ok'      => false,
                'errors'  => ['code' => ['expired']],
                'message' => st('auth.code_expired', 'Код прострочений. Надішліть новий.'),
            ], 422);
        }

        $input    = (string) $r->input('code');
        $expected = (string) ($state['code'] ?? '');
        if ($input !== $expected) {
            Cache::put($key, [
                'code'     => $expected,
                'attempts' => (int) ($state['attempts'] ?? 0) + 1,
                'payload'  => $state['payload'] ?? null,
            ], now()->addMinutes(3));

            return response()->json([
                'ok'      => false,
                'errors'  => ['code' => ['invalid']],
                'message' => st('auth.code_invalid', 'Невірний код. Перевірте цифри та спробуйте ще раз.'),
            ], 422);
        }

        // --- создаём пользователя ---
        $payload = (array) ($state['payload'] ?? []);

        $validator = \Validator::make($payload, [
            'name'     => ['required', 'string', 'max:255'],
            'email'    => ['nullable', 'email', 'max:255', 'unique:bs_clients,email'],
            'password' => ['required', 'string', 'min:6', 'confirmed'],
            'phone'    => ['required', 'regex:/^380\d{9}$/', 'unique:bs_clients,phone'],
        ], [
            'email.unique' => st('auth.email_taken', 'Ця пошта вже використовується.'),
            'phone.unique' => st('auth.phone_taken', 'Цей номер вже зареєстровано.'),
        ]);

        if ($validator->fails()) {
            return response()->json([
                'ok'      => false,
                'message' => $validator->errors()->first(),
                'errors'  => $validator->errors(),
            ], 422);
        }

        /** @var \App\Models\Shop\Client $client */
        $client = Client::create([
            'name'     => $payload['name'],
            'email'    => $payload['email'] ?: null,
            'phone'    => $digits,
            'password' => Hash::make($payload['password']),
        ]);

        Auth::guard('web')->login($client, true);

        Cache::forget($key);
        Cache::forget($key . ':resend_lock');

        return response()->json([
            'ok'       => true,
            'message'  => st('auth.phone_verified_account_created', 'Телефон підтверджено. Обліковий запис створено.'),
            'redirect' => route('profile.index'),
        ]);
    }
}
