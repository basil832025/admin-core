<?php
// app/Http/Controllers/Auth/PasswordResetSmsController.php
namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Shop\Client;
use App\Services\Sms\EsputnikSms;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class PasswordResetSmsController extends Controller
{
    protected function guard() { return Auth::guard('web'); }

    // Нормализация  -> 380XXXXXXXXX
    private function normalize(string $raw): string
    {
        $d = preg_replace('/\D+/', '', $raw);
        if (str_starts_with($d,'0')) $d = '38'.$d;
        if (strlen($d) === 9)       $d = '380'.$d;
        if (!str_starts_with($d,'380') && strlen($d) >= 10) $d = '380'.substr($d, -9);
        return $d;
    }

    // Шаг 1: прислать код на телефон существующего клиента
    public function sendCode(Request $r, EsputnikSms $sms)
    {
        $digits = $this->normalize((string) $r->input('phone'));
        $r->merge(['phone' => $digits]);

        $data = $r->validate([
            'phone' => ['required','regex:/^380\d{9}$/'],
        ],[
            'phone.required' => st('auth.enter_phone', 'Вкажіть номер телефону'),
            'phone.regex'    => st('auth.invalid_phone_format', 'Невірний формат телефону'),
        ]);

        $client = Client::query()
            ->whereIn('phone', [$digits, '0'.substr($digits,3), substr($digits,3)])
            ->first();

        if (!$client) {
            throw ValidationException::withMessages([
                'phone' => st('auth.client_not_found', 'Клієнта з таким номером не знайдено.')
            ]);
        }

        $ttl         = (int) env('PWD_RESET_SMS_TTL', 180);
        $resendAfter = (int) env('PWD_RESET_RESEND_AFTER', 60);
        $key         = 'pwd:' . $digits;
        $lockKey     = $key . ':resend_lock';

        // 1) rate-limit: если лок уже есть — сразу ошибка
        if (Cache::has($lockKey)) {
            throw ValidationException::withMessages([
                'phone' => st('auth.resend_unavailable', 'Повторна відправка поки недоступна.'),
            ]);
        }

        // 2) берём существующее состояние (чтобы не потерять attempts при переотправке)
        $state = Cache::get($key, []);
        $code  = $state['code']
            ?? (config('services.sms.fake')
                ? (string) config('services.sms.test_code', '1234')
                : (string) random_int(1000, 9999));

        Cache::put($key, [
            'code'     => $code,
            'user_id'  => $client->id,
            'attempts' => $state['attempts'] ?? 0,
        ], $ttl);

        // FAKE-режим — просто возвращаем успех и ставим лок
        if (config('services.sms.fake')) {
            Cache::put($lockKey, 1, $resendAfter);
            Log::info('FAKE-SMS pwd', ['phone' => $data['phone'], 'code' => $code, 'key' => $key]);
            return response()->json([
                'ok'        => true,
                'ttl'       => $ttl,
                'resend_in' => $resendAfter,
                'phone'     => $data['phone'],
                'debug_code'=> $code,
            ]);
        }

        // 3) реальная отправка
        $resp = $sms->sendCode($digits, $code);

        if (($resp['status'] ?? 500) >= 300) {
            Cache::forget($key);
            // лок не ставим/снимаем, чтобы можно было попытаться снова
            return response()->json([
                'ok'      => false,
                'message' => st('auth.send_error', 'Не вдалося відправити SMS.') . ' ' . st('auth.try_later', 'Спробуйте пізніше.'),
            ], 422);
        }

        // 4) успешная отправка — ставим лок на повторную отправку
        Cache::put($lockKey, 1, $resendAfter);

        return response()->json([
            'ok'        => true,
            'ttl'       => $ttl,
            'resend_in' => $resendAfter,
            'phone'     => $digits,
        ]);
    }



    // Шаг 2: проверка кода, авто-логин и редирект в профиль (страница смены пароля)
    // Наприклад, PasswordController@verify (той, де «забули пароль»)
    public function verify(Request $r)
    {
        $digits = preg_replace('/\D+/', '', (string) $r->input('phone'));
        if (str_starts_with($digits,'0')) $digits = '38'.$digits;
        if (strlen($digits) === 9)        $digits = '380'.$digits;

        $r->merge(['phone' => $digits]);

        $data = $r->validate([
            'phone' => ['required','regex:/^380\d{9}$/'],
            'code'  => ['required','digits:4'],
        ], [
            'phone.required' => st('auth.enter_phone', 'Вкажіть номер телефону'),
            'phone.regex'    => st('auth.invalid_phone_format', 'Невірний формат номера телефону'),
            'code.required'  => st('auth.enter4', 'Введіть 4 цифри'),
            'code.digits'    => st('auth.enter4', 'Введіть 4 цифри'),
        ]);

        $key   = 'pwd:'.$data['phone']; // ключ для коду «забули пароль»
        $state = Cache::get($key);

        if (!$state) {
            return $this->jsonErr(st('auth.code_expired2',  'Код прострочений. Надішліть новий.')  , 'code');
        }

        $attempts = (int)($state['attempts'] ?? 0);
        if ($attempts >= 5) {
            return $this->jsonErr(st('auth.too_many_attempts',  'Забагато спроб. Спробуйте пізніше або надішліть код ще раз.')   , 'code');
        }

        if ((string)$state['code'] !== (string)$data['code']) {
            $state['attempts'] = $attempts + 1;
            Cache::put($key, $state, now()->addMinutes(3));
            return $this->jsonErr(st('auth.code_invalid',  'Невірний код. Перевірте цифри та спробуйте ще раз.'), 'code');
        }

        // Знаходимо користувача, логінимо і ведемо в профіль (щоб він змінив пароль)
        $client = \App\Models\Shop\Client::query()
            ->where('phone', $data['phone'])
            ->first();

        if (!$client) {
            return $this->jsonErr(st('auth.user_not_found',  'Користувача з таким номером не знайдено.'), 'phone');
        }

        Auth::guard('web')->login($client, true);
        Cache::forget($key);

        return $this->jsonOk([
            'message'  => st('auth.login_by_code_success',  'Вхід за кодом виконано'),
            'redirect' => in_array(app()->getLocale(), ['ru', 'en'], true)
                ? route('localized.profile.index', ['locale' => app()->getLocale()])
                : route('profile.index'),
        ]);
    }
// будь-який ваш базовий контролер або прямо в поточному
    private function jsonOk(array $data = [], int $status = 200)
    {
        return response()->json(array_merge(['ok' => true], $data), $status);
    }

    private function jsonErr(string $message, string $codeKey = 'general', int $status = 422)
    {
        return response()->json([
            'ok'      => false,
            'message' => $message,
            'errors'  => [$codeKey => [$this->shortCodeFromMessage($message) ?? $codeKey]],
        ], $status);
    }

// опціонально: якщо хочеш завжди віддавати короткий код саме той, що передаєш у $codeKey
    private function shortCodeFromMessage(?string $message): ?string { return null; }
}
