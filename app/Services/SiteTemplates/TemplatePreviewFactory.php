<?php

namespace App\Services\SiteTemplates;

use App\Models\Pages;
use App\Services\HeaderContacts;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class TemplatePreviewFactory
{
    /**
     * @return array<string, mixed>
     */
    public function make(string $key): array
    {
        $page = Pages::query()->where('slug', 'home')->first() ?? new Pages([
            'slug' => 'preview',
            'title' => ['uk' => 'Превью сторінки'],
            'content' => ['uk' => '<p>Превью контенту</p>'],
            'meta_title' => ['uk' => 'Превью SEO'],
        ]);

        $shared = $this->sharedFrontendData();

        return match ($key) {
            'home' => array_merge($shared, [
                'page' => $page,
                'banners' => collect(),
                'promo' => [],
                'hits' => [],
                'news' => [],
                'favoriteIds' => [],
                'priceMin' => 0,
                'priceMax' => 0,
                'filterCharacteristicGroups' => collect(),
                'MainMenuItems' => collect(),
                'categorySections' => [],
                'homeBlog' => null,
            ]),
            'pages.blog.index' => array_merge($shared, [
                'category' => null,
                'posts' => new LengthAwarePaginator([], 0, 9, 1, ['path' => '/preview/blog']),
                'title' => 'Превью блогу',
                'slug' => 'blog',
                'page' => $page,
            ]),
            'pages.blog.show' => array_merge($shared, [
                'post' => (object) [
                    'title' => 'Превью статті',
                    'slug' => 'preview-post',
                    'content' => '<p>Текст превью статті.</p>',
                    'excerpt' => 'Короткий опис',
                    'published_at' => now(),
                    'image' => null,
                ],
                'title' => 'Превью статті',
                'date' => now()->format('d.m.Y'),
                'comments' => new LengthAwarePaginator([], 0, 10, 1, ['path' => '/preview/blog/post']),
                'related' => new Collection(),
            ]),
            default => array_merge($shared, [
                'page' => $page,
            ]),
        };
    }

    /**
     * @return array<string, mixed>
     */
    protected function sharedFrontendData(): array
    {
        $slug = config('site.header_location_slug', '3pie');
        $header = app(HeaderContacts::class)->buildBySlug($slug);

        return [
            'headerPhones' => $header['phones'] ?? collect(),
            'headerPhonePrimary' => $header['primary'] ?? null,
            'headerLocation' => $header['location'] ?? null,
            'headerSchedule' => $header['schedule'] ?? collect(),
        ];
    }
}
