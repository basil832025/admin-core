<?php

namespace Database\Seeders;

use App\Models\SiteText;
use Illuminate\Database\Seeder;

class BlogCommentFormTranslationsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $translations = [
            [
                'group' => 'blog',
                'slug' => 'blog.comment_form.title',
                'value' => [
                    'uk' => 'Залишити коментар',
                    'ru' => 'Оставить комментарий',
                    'en' => 'Leave a comment',
                ],
                'description' => 'Заголовок формы комментария в блоге',
            ],
            [
                'group' => 'blog',
                'slug' => 'blog.comment_form.reply_to',
                'value' => [
                    'uk' => 'Відповідь на',
                    'ru' => 'Ответ на',
                    'en' => 'Reply to',
                ],
                'description' => 'Префикс в форме: ответ на комментарий',
            ],
            [
                'group' => 'blog',
                'slug' => 'blog.comment_form.submit',
                'value' => [
                    'uk' => 'Надіслати',
                    'ru' => 'Отправить',
                    'en' => 'Send',
                ],
                'description' => 'Кнопка отправки комментария в блоге',
            ],
        ];

        foreach ($translations as $data) {
            SiteText::updateOrCreate(
                ['slug' => $data['slug']],
                [
                    'group' => $data['group'],
                    'value' => $data['value'],
                    'description' => $data['description'],
                ]
            );
        }
    }
}
