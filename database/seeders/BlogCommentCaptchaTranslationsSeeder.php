<?php

namespace Database\Seeders;

use App\Models\SiteText;
use Illuminate\Database\Seeder;

class BlogCommentCaptchaTranslationsSeeder extends Seeder
{
    public function run(): void
    {
        $translations = [
            'blog.comment_form.captcha_required' => [
                'uk' => 'Підтвердіть, що ви не робот.',
                'ru' => 'Подтвердите, что вы не робот.',
                'en' => 'Please confirm you are not a robot.',
            ],
            'blog.comment_form.captcha_failed' => [
                'uk' => 'Помилка перевірки captcha. Спробуйте ще раз.',
                'ru' => 'Ошибка проверки captcha. Попробуйте еще раз.',
                'en' => 'Captcha verification failed. Please try again.',
            ],
            'blog.comment_form.spam_detected' => [
                'uk' => 'Виявлено спам. Спробуйте ще раз.',
                'ru' => 'Обнаружен спам. Попробуйте еще раз.',
                'en' => 'Spam detected. Please try again.',
            ],
            'blog.comment_form.success' => [
                'uk' => 'Коментар додано.',
                'ru' => 'Комментарий добавлен.',
                'en' => 'Comment added.',
            ],
            'blog.comment_form.sent_for_moderation' => [
                'uk' => 'Коментар надіслано на модерацію.',
                'ru' => 'Комментарий отправлен на модерацию.',
                'en' => 'Comment sent for moderation.',
            ],
        ];

        foreach ($translations as $slug => $defaultValue) {
            $row = SiteText::query()->where('slug', $slug)->first();

            if (! $row) {
                SiteText::query()->create([
                    'group' => 'blog',
                    'slug' => $slug,
                    'value' => $defaultValue,
                    'description' => 'Blog comments anti-spam/captcha translation',
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
                'group' => $row->group ?: 'blog',
                'value' => $merged,
                'description' => $row->description ?: 'Blog comments anti-spam/captcha translation',
            ]);
        }
    }
}
