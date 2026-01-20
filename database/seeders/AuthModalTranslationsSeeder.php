<?php

namespace Database\Seeders;

use App\Models\SiteText;
use Illuminate\Database\Seeder;

class AuthModalTranslationsSeeder extends Seeder
{
    /**
     * Seed translations for authentication modal (phone_sms variant)
     */
    public function run(): void
    {
        $translations = [
            [
                'group' => 'auth',
                'slug' => 'auth.enter_phone_for_sms',
                'value' => [
                    'uk' => 'Введіть номер телефону, на цей номер надійде дзвінок або SMS:',
                    'ru' => 'Введите номер телефона, на этот номер поступит звонок или SMS:',
                    'en' => 'Enter your phone number, a call or SMS will be sent to this number:',
                ],
                'description' => 'Инструкция для ввода номера телефона на первом шаге авторизации через SMS',
            ],
            [
                'group' => 'auth',
                'slug' => 'auth.send_code',
                'value' => [
                    'uk' => 'Надіслати код',
                    'ru' => 'Отправить код',
                    'en' => 'Send code',
                ],
                'description' => 'Кнопка отправки SMS кода',
            ],
            [
                'group' => 'auth',
                'slug' => 'auth.sending',
                'value' => [
                    'uk' => 'Відправка…',
                    'ru' => 'Отправка…',
                    'en' => 'Sending…',
                ],
                'description' => 'Текст на кнопке во время отправки кода',
            ],
            [
                'group' => 'auth',
                'slug' => 'auth.success',
                'value' => [
                    'uk' => 'Авторизація успішна!',
                    'ru' => 'Авторизация успешна!',
                    'en' => 'Authorization successful!',
                ],
                'description' => 'Сообщение об успешной авторизации',
            ],
            [
                'group' => 'auth',
                'slug' => 'auth.redirecting_wait',
                'value' => [
                    'uk' => 'Зачекайте... зараз відбудеться переадресація',
                    'ru' => 'Ожидайте... пока идет переадресация',
                    'en' => 'Please wait... redirecting',
                ],
                'description' => 'Текст во время ожидания переадресации после успешной авторизации',
            ],
            [
                'group' => 'auth',
                'slug' => 'auth.enter_last_4_digits',
                'value' => [
                    'uk' => 'Введіть останні 4 цифри вхідного номера або код з SMS:',
                    'ru' => 'Введите последние 4 цифры входящего номера или код из SMS:',
                    'en' => 'Enter the last 4 digits of the incoming number or the code from SMS:',
                ],
                'description' => 'Инструкция для ввода SMS кода на втором шаге',
            ],
            [
                'group' => 'auth',
                'slug' => 'auth.to_number',
                'value' => [
                    'uk' => 'На ваш номер',
                    'ru' => 'На ваш номер',
                    'en' => 'To your number',
                ],
                'description' => 'Текст перед номером телефона при отображении информации о SMS',
            ],
            [
                'group' => 'auth',
                'slug' => 'auth.code_sent_valid_3min',
                'value' => [
                    'uk' => 'надіслано код підтвердження. Термін дії вашого коду 3 хвилини.',
                    'ru' => 'отправлен код подтверждения. Срок действия вашего кода 3 минуты.',
                    'en' => 'a confirmation code has been sent. Your code is valid for 3 minutes.',
                ],
                'description' => 'Текст после номера телефона с информацией о сроке действия кода',
            ],
            [
                'group' => 'auth',
                'slug' => 'auth.send_again',
                'value' => [
                    'uk' => 'Надіслати код ще раз',
                    'ru' => 'Повторно отправить код',
                    'en' => 'Resend code',
                ],
                'description' => 'Кнопка для повторной отправки SMS кода',
            ],
            [
                'group' => 'auth',
                'slug' => 'auth.resend_in',
                'value' => [
                    'uk' => 'Повторно через',
                    'ru' => 'Повторно через',
                    'en' => 'Resend in',
                ],
                'description' => 'Текст перед таймером повторной отправки кода',
            ],
            [
                'group' => 'auth',
                'slug' => 'auth.change_phone',
                'value' => [
                    'uk' => 'Змінити номер телефону',
                    'ru' => 'Изменить номер телефона',
                    'en' => 'Change phone number',
                ],
                'description' => 'Кнопка для изменения номера телефона на втором шаге',
            ],
            [
                'group' => 'auth',
                'slug' => 'auth.code_invalid',
                'value' => [
                    'uk' => 'Невірний код. Перевірте цифри та спробуйте ще раз.',
                    'ru' => 'Неверный код. Проверьте цифры и попробуйте еще раз.',
                    'en' => 'Incorrect code. Check the digits and try again.',
                ],
                'description' => 'Сообщение об ошибке при вводе неверного SMS кода',
            ],
            [
                'group' => 'header',
                'slug' => 'header.logo_alt',
                'value' => [
                    'uk' => 'Три пироги',
                    'ru' => 'Три пироги',
                    'en' => 'Three Pies',
                ],
                'description' => 'Альтернативный текст для логотипа компании',
            ],
        ];

        foreach ($translations as $translation) {
            // Сначала удаляем старые записи с коротким slug (если они есть)
            $shortSlug = str_replace($translation['group'] . '.', '', $translation['slug']);
            if ($shortSlug !== $translation['slug']) {
                SiteText::where('group', $translation['group'])
                    ->where('slug', $shortSlug)
                    ->delete();
            }
            
            // Создаем/обновляем с полным slug
            SiteText::updateOrCreate(
                [
                    'slug' => $translation['slug'],
                ],
                [
                    'group' => $translation['group'],
                    'value' => $translation['value'],
                    'description' => $translation['description'] ?? null,
                ]
            );
        }

        $this->command->info('Auth modal translations seeded successfully!');
    }
}