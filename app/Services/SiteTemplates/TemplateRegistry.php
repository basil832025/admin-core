<?php

namespace App\Services\SiteTemplates;

class TemplateRegistry
{
    /**
     * @return array<string, array{title: string, source_path: string, fallback_view: string}>
     */
    public function all(): array
    {
        return [
            'home' => [
                'title' => 'Стартова',
                'source_path' => 'resources/views/home.blade.php',
                'fallback_view' => 'home',
            ],
            'pages.show' => [
                'title' => 'Сторінки: загальний шаблон',
                'source_path' => 'resources/views/pages/show.blade.php',
                'fallback_view' => 'pages.show',
            ],
            'pages.about' => [
                'title' => 'Сторінки: Про нас',
                'source_path' => 'resources/views/pages/about.blade.php',
                'fallback_view' => 'pages.about',
            ],
            'pages.delivery' => [
                'title' => 'Сторінки: Доставка',
                'source_path' => 'resources/views/pages/delivery.blade.php',
                'fallback_view' => 'pages.delivery',
            ],
            'pages.reviews' => [
                'title' => 'Сторінки: Відгуки',
                'source_path' => 'resources/views/pages/reviews.blade.php',
                'fallback_view' => 'pages.reviews',
            ],
            'pages.nashi-restorany' => [
                'title' => 'Сторінки: Наші ресторани',
                'source_path' => 'resources/views/pages/nashi-restorany.blade.php',
                'fallback_view' => 'pages.nashi-restorany',
            ],
            'pages.nas-blagodaryat' => [
                'title' => 'Сторінки: Нас благодарять',
                'source_path' => 'resources/views/pages/nas-blagodaryat.blade.php',
                'fallback_view' => 'pages.nas-blagodaryat',
            ],
            'pages.blog.index' => [
                'title' => 'Блог: список',
                'source_path' => 'resources/views/pages/blog/index.blade.php',
                'fallback_view' => 'pages.blog.index',
            ],
            'pages.blog.show' => [
                'title' => 'Блог: стаття',
                'source_path' => 'resources/views/pages/blog/show.blade.php',
                'fallback_view' => 'pages.blog.show',
            ],
        ];
    }

    public function get(string $key): ?array
    {
        return $this->all()[$key] ?? null;
    }
}
