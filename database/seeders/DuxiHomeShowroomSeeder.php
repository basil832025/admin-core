<?php

namespace Database\Seeders;

use App\Models\Pages;
use Illuminate\Database\Seeder;
use RuntimeException;
use Illuminate\Support\Facades\Storage;

class DuxiHomeShowroomSeeder extends Seeder
{
    public function run(): void
    {
        $connection = (new Pages())->getConnection();

        if (config('project.name') !== 'duxi'
            || $connection->getDriverName() !== 'mysql'
            || ! in_array($connection->getDatabaseName(), ['duxi', 'xfizwzzz_sevia'], true)) {
            throw new RuntimeException('This seeder requires the DUXI project and an approved DUXI MySQL database.');
        }

        $slug = 'home-showroom';
        if (Pages::query()->where('slug', $slug)->exists()) {
            return;
        }

        $disk = Storage::disk('public');
        $images = [];
        foreach ([
            'main' => 'try-new-up.png',
            'secondary' => 'try-new-down.png',
            'mobile' => 'showroom-mobile.png',
        ] as $key => $filename) {
            $source = base_path("packages/frontend-sevia/public/images/{$filename}");
            $bytes = file_get_contents($source);
            if ($bytes === false) {
                throw new RuntimeException("Cannot read showroom photo {$filename}.");
            }

            $hash = substr(hash('sha256', $bytes), 0, 12);
            $path = "uploads/home-showroom/{$hash}-{$filename}";
            if (! $disk->exists($path) && ! $disk->put($path, $bytes, 'public')) {
                throw new RuntimeException("Cannot store showroom photo {$filename}.");
            }

            $images[$key] = $path;
        }

        Pages::query()->firstOrCreate(['slug' => $slug], [
            'template' => 'home-showroom',
            'title' => ['uk' => 'Шоурум на головній'],
            'status' => 'published',
            'content' => [
                'images' => $images,
                'quote' => [
                    'uk' => '«Sevia починалася з моєї власної шафи з парфумами та маленького Instagram-акаунту. Мені хотілося, щоб люди могли спробувати нішевий парфум, не платячи одразу за повний флакон.»',
                ],
                'eyebrow' => [
                    'uk' => 'магазин Sevia',
                ],
                'benefit_1' => [
                    'text' => [
                        'uk' => 'Лише офіційні дистрибʼютори та європейські бутики.',
                    ],
                    'title' => [
                        'uk' => 'Гарантія оригіналу.',
                    ],
                ],
                'benefit_2' => [
                    'text' => [
                        'uk' => 'Спробуй вдома й повертайся за більшим обʼємом, коли впевнишся, що це твоє.',
                    ],
                    'title' => [
                        'uk' => 'Розпив від 3 мл.',
                    ],
                ],
                'benefit_3' => [
                    'text' => [
                        'uk' => 'Допоможемо знайти аромат під настрій, сезон і твою шкіру.',
                    ],
                    'title' => [
                        'uk' => 'Тепла консультація.',
                    ],
                ],
                'image_alt' => [
                    'uk' => 'Шоурум Sevia',
                ],
                'catalog_cta' => [
                    'uk' => 'Перейти в каталог',
                ],
                'quote_author' => [
                    'uk' => '— наталія, засновниця Sevia',
                ],
                'title_line_1' => [
                    'uk' => 'Спробуй нові аромати',
                ],
                'title_line_2' => [
                    'uk' => 'в нашому шоурумі',
                ],
            ],
        ]);
    }
}
