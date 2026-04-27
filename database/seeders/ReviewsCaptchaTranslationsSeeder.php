<?php

namespace Database\Seeders;

use App\Models\SiteText;
use Illuminate\Database\Seeder;

class ReviewsCaptchaTranslationsSeeder extends Seeder
{
    public function run(): void
    {
        $translations = [
            'reviews.captcha_required' => [
                'uk' => 'Підтвердіть, що ви не робот.',
                'ru' => 'Подтвердите, что вы не робот.',
                'en' => 'Please confirm you are not a robot.',
            ],
            'reviews.captcha_failed' => [
                'uk' => 'Помилка перевірки captcha. Спробуйте ще раз.',
                'ru' => 'Ошибка проверки captcha. Попробуйте еще раз.',
                'en' => 'Captcha verification failed. Please try again.',
            ],
        ];

        foreach ($translations as $slug => $defaultValue) {
            $row = SiteText::query()->where('slug', $slug)->first();

            if (! $row) {
                SiteText::query()->create([
                    'group' => 'reviews',
                    'slug' => $slug,
                    'value' => $defaultValue,
                    'description' => 'Reviews captcha translation',
                ]);

                continue;
            }

            $current = is_array($row->value) ? $row->value : [];
            $merged = $current;

            foreach ($defaultValue as $locale => $text) {
                $existing = trim((string) ($current[$locale] ?? ''));
                if ($existing === '') {
                    $merged[$locale] = $text;
                }
            }

            $row->update([
                'group' => $row->group ?: 'reviews',
                'value' => $merged,
                'description' => $row->description ?: 'Reviews captcha translation',
            ]);
        }
    }
}
