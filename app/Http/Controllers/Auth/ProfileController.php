<?php
// app/Http/Controllers/Auth/ProfileController.php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Hash;
use Carbon\Carbon;

class ProfileController extends Controller
{
    public function update(Request $request)
    {
        $user  = $request->user();
        $field = (string) $request->string('field');

        if ($field === 'birthday') {
            $request->merge([
                'birthday' => $this->normalizeBirthdayInput((string) $request->input('birthday', '')),
            ]);
        }

        // Если день рождения уже задан — запретить менять
        if ($field === 'birthday' && $user->birthday) {
            return back(303)->withErrors([
                'birthday' => __('Дата народження вже встановлена і не може бути змінена.'),
            ]);
        }

        $rules = match ($field) {
            'name' => [
                'name' => ['required', 'string', 'max:60'],
            ],
            'email' => [
                'email' => [
                    'required', 'string', 'email:rfc',
                    Rule::unique($user->getTable(), 'email')->ignore($user->getKey()),
                ],
            ],
            'birthday' => [
                // ⬇️ формат с точками + дата должна быть в прошлом (и разумная нижняя граница)
                'birthday' => ['required', 'date_format:d.m.Y', 'before:today', 'after:01.01.1900'],
            ],
            'password' => [
                'password' => ['required', 'string', 'min:6'],
            ],
            'photo' => [
                'photo' => ['required', 'file', 'image', 'max:5120'],
            ],
            default => [
                'field' => ['in:name,email,birthday,password,avatar'],
            ],
        };

        $data = $request->validate($rules, [
            'required'        => 'Поле :attribute є обов’язковим.',
            'string'          => 'Поле :attribute повинно бути рядком.',
            'email'           => 'Введіть коректну адресу електронної пошти.',
            'min.string'      => 'Поле :attribute має містити щонайменше :min символів.',
            'max.string'      => 'Поле :attribute не може перевищувати :max символів.',
            'date_format'     => 'Поле :attribute має бути у форматі :format.',
            'before'          => 'Дата має бути в минулому.',
            'after'           => 'Дата надто стара. Вкажіть пізнішу дату.',
            'image'           => 'Поле :attribute повинно містити зображення.',
            'file'            => 'Поле :attribute повинно містити файл.',
            'unique'          => 'Це значення вже використовується.',
            'name.required'   => 'Вкажіть ім’я.',
            'email.required'  => 'Вкажіть адресу електронної пошти.',
            'email.unique'    => 'Ця адреса вже використовується.',
            'password.required' => 'Введіть новий пароль.',
            'password.min'    => 'Мінімальна довжина пароля — :min символів.',
            'avatar.image'    => 'Завантажте дійсний файл зображення.',
        ]);

        switch ($field) {
            case 'name':
                $user->name = $data['name'];
                break;

            case 'email':
                $user->email = $data['email'];
                break;

            case 'birthday':
                // ⬇️ парсим d.m.Y
                $user->birthday = Carbon::createFromFormat('d.m.Y', $data['birthday'])->startOfDay();
                break;

            case 'password':
                $user->password = Hash::make($data['password']);
                break;

            case 'photo':
                $path = $request->file('photo')->store('avatars', 'public');
                $user->photo = $path;
                break;
        }

        $user->save();

        // Если это AJAX запрос, возвращаем JSON
        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => __('Зміни збережено'),
                'name' => $user->name,
                'email' => $user->email,
            ]);
        }

        return back(303)->with('success', __('Зміни збережено'));
    }

    private function normalizeBirthdayInput(string $input): string
    {
        $raw = trim($input);
        if ($raw === '') {
            return '';
        }

        $digits = preg_replace('/\D+/', '', $raw);
        if (strlen($digits) === 8) {
            return substr($digits, 0, 2).'.'.substr($digits, 2, 2).'.'.substr($digits, 4, 4);
        }

        $normalized = preg_replace('/[\/\-,\s]+/', '.', $raw);
        $normalized = preg_replace('/\.+/', '.', (string) $normalized);
        $normalized = trim((string) $normalized, '.');

        if (preg_match('/^(\d{1,2})\.(\d{1,2})\.(\d{4})$/', $normalized, $m)) {
            return str_pad($m[1], 2, '0', STR_PAD_LEFT)
                .'.'.str_pad($m[2], 2, '0', STR_PAD_LEFT)
                .'.'.$m[3];
        }

        return $raw;
    }
}
