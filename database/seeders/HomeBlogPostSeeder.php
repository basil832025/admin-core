<?php

namespace Database\Seeders;

use App\Models\Blog;
use App\Models\BlogCategory;
use Illuminate\Database\Seeder;

class HomeBlogPostSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $previewImagePath = 'discounts/small/home_blog.s.png';
        $detailImagePath = 'discounts/main/home_blog.b.png';

        $categoryId = BlogCategory::query()
            ->where('slug', 'blog')
            ->value('id');

        if (! $categoryId) {
            $categoryId = BlogCategory::query()->value('id');
        }

        Blog::query()->updateOrCreate(
            ['slug' => 'home_blog'],
            [
                'blog_category_id' => $categoryId,
                'title' => [
                    'uk' => 'Осетинські пироги у Києві',
                    'ru' => 'Осетинские пироги в Киеве',
                    'en' => 'Ossetian pies in Kyiv',
                ],
                'anons' => [
                    'uk' => 'Пироги зустрічаються в будь-якій кухні світу і є універсальною та смачною стравою. Ми пропонуємо неймовірно смачні осетинські пироги - чудову альтернативу фаст-фуду та піці у сучасній культурі їжі Києва.',
                    'ru' => 'Пироги встречаются в любой кухне мира и являются универсальным и вкусным блюдом. Мы предлагаем невероятно вкусные осетинские пироги - отличную альтернативу фастфуду и пицце в современной культуре еды Киева.',
                    'en' => 'Pies can be found in cuisines all over the world and remain a universal, delicious dish. We offer incredibly tasty Ossetian pies - a great alternative to fast food and pizza in Kyiv\'s modern food culture.',
                ],
                'content' => [
                    'uk' => '<p>Пироги зустрічаються в будь-якій кухні світу і є універсальною та смачною стравою.</p><p>Ми пропонуємо вашій увазі неймовірно смачні осетинські пироги - чудову альтернативу фаст-фуду та піці, які останнім часом посіли знакове місце у сучасній культурі їжі Києва.</p><p>Осетинські пироги поєднують тонке тісто, соковиту начинку та традиційні рецепти, що передаються поколіннями.</p>',
                    'ru' => '<p>Пироги встречаются в любой кухне мира и являются универсальным и вкусным блюдом.</p><p>Мы предлагаем вашему вниманию невероятно вкусные осетинские пироги - отличную альтернативу фастфуду и пицце, которые в последнее время заняли заметное место в современной культуре еды Киева.</p><p>Осетинские пироги сочетают тонкое тесто, сочную начинку и традиционные рецепты, передающиеся из поколения в поколение.</p>',
                    'en' => '<p>Pies can be found in cuisines around the world and are a universal, tasty dish.</p><p>We offer incredibly delicious Ossetian pies - an excellent alternative to fast food and pizza, both of which have become a notable part of modern food culture in Kyiv.</p><p>Ossetian pies combine thin dough, juicy fillings, and traditional recipes passed down through generations.</p>',
                ],
                'preview_image' => $previewImagePath,
                'detail_image' => $detailImagePath,
                'preview_image_i18n' => [
                    'uk' => $previewImagePath,
                    'ru' => $previewImagePath,
                    'en' => $previewImagePath,
                ],
                'detail_image_i18n' => [
                    'uk' => $detailImagePath,
                    'ru' => $detailImagePath,
                    'en' => $detailImagePath,
                ],
                'is_published' => true,
                'published_at' => now(),
                'tags' => ['home', 'ossetian-pies'],
            ]
        );
    }
}
