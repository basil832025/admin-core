<?php

namespace App\Console\Commands;

use App\Models\Blog;
use App\Models\BlogCategory;
use App\Models\Pages;
use App\Models\Setting;
use App\Models\Shop\Product;
use App\Models\Shop\ProductCategory;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;
use Spatie\Sitemap\Sitemap;
use Spatie\Sitemap\Tags\Url;

class GenerateSitemap extends Command
{
    protected $signature = 'seo:sitemap {--path= : Absolute or relative output path (default: public/sitemap.xml)}';

    protected $description = 'Generate sitemap.xml for SEO';

    public function handle(): int
    {
        $outputPath = (string) ($this->option('path') ?: public_path('sitemap.xml'));

        if (! str_contains($outputPath, ':') && ! str_starts_with($outputPath, DIRECTORY_SEPARATOR)) {
            $outputPath = base_path($outputPath);
        }

        $sitemap = Sitemap::create();

        $locales = $this->resolveLocales();

        foreach ($locales as $locale) {
            foreach ($this->staticPaths() as $path) {
                $sitemap->add($this->makeUrl($locale, $path));
            }
        }

        if ($this->canQueryDatabase()) {
            $this->addPages($sitemap, $locales);
            $this->addCatalog($sitemap, $locales);
            $this->addBlog($sitemap, $locales);
        } else {
            $this->warn('Database tables are not available; sitemap will contain only static URLs.');
        }

        $sitemap->writeToFile($outputPath);

        $this->info('Sitemap generated: ' . $outputPath);

        return self::SUCCESS;
    }

    private function staticPaths(): array
    {
        return [
            '/',
            '/pies',
            '/feedbacks',
        ];
    }

    private function resolveLocales(): array
    {
        $fallback = ['uk', 'ru', 'en'];

        try {
            if (! Schema::hasTable('bs_languages')) {
                return $fallback;
            }
        } catch (\Throwable) {
            return $fallback;
        }

        try {
            $locales = Setting::getActiveLocales();
            if (empty($locales)) {
                return $fallback;
            }
            return array_values(array_unique(array_map('strtolower', $locales)));
        } catch (\Throwable) {
            return $fallback;
        }
    }

    private function canQueryDatabase(): bool
    {
        try {
            return Schema::hasTable('bs_pages')
                && Schema::hasTable('bs_product_categories')
                && Schema::hasTable('bs_products')
                && Schema::hasTable('bs_blog_categories')
                && Schema::hasTable('bs_blogs');
        } catch (\Throwable) {
            return false;
        }
    }

    private function makeUrl(string $locale, string $path, ?\DateTimeInterface $lastMod = null): Url
    {
        $path = '/' . ltrim($path, '/');
        $localizedPath = $this->localizePath($locale, $path);

        $url = Url::create(url($localizedPath));
        if ($lastMod) {
            $url->setLastModificationDate($lastMod);
        }

        return $url;
    }

    private function localizePath(string $locale, string $path): string
    {
        $locale = strtolower($locale);
        $path = '/' . ltrim($path, '/');

        // Front routes use prefixes only for ru/en.
        if (in_array($locale, ['ru', 'en'], true)) {
            return '/' . $locale . ($path === '/' ? '' : $path);
        }

        return $path;
    }

    private function addPages(Sitemap $sitemap, array $locales): void
    {
        $pages = Pages::query()
            ->where('status', 'published')
            ->whereNotNull('slug')
            ->get(['slug', 'updated_at']);

        foreach ($pages as $page) {
            foreach ($locales as $locale) {
                $sitemap->add($this->makeUrl($locale, '/' . $page->slug, $page->updated_at));
            }
        }
    }

    private function addCatalog(Sitemap $sitemap, array $locales): void
    {
        $categories = ProductCategory::query()
            ->where('is_visible', 1)
            ->where('slug', 'not like', 'src-%')
            ->whereNotNull('slug')
            ->get(['slug', 'updated_at']);

        foreach ($categories as $category) {
            foreach ($locales as $locale) {
                $sitemap->add($this->makeUrl($locale, '/' . $category->slug, $category->updated_at));
            }
        }

        $products = Product::query()
            ->where('in_stock', 1)
            ->where(function ($q) {
                // Imported items use src-* slugs and/or the is_imported flag.
                $q->where('is_imported', 0)
                    ->orWhereNull('is_imported');
            })
            ->where('slug', 'not like', 'src-%')
            ->whereNotNull('slug')
            ->whereHas('mainCategory', function ($q) {
                $q->whereNotNull('slug')
                    ->where('slug', 'not like', 'src-%');
            })
            ->with(['mainCategory:id,slug'])
            ->get(['id', 'slug', 'category_id', 'updated_at']);

        foreach ($products as $product) {
            $categorySlug = (string) ($product->mainCategory?->slug ?? '');
            if ($categorySlug === '') {
                continue;
            }

            $path = '/' . $categorySlug . '/' . $product->slug;

            foreach ($locales as $locale) {
                $sitemap->add($this->makeUrl($locale, $path, $product->updated_at));
            }
        }
    }

    private function addBlog(Sitemap $sitemap, array $locales): void
    {
        $categories = BlogCategory::query()
            ->where('is_active', true)
            ->whereNotNull('slug')
            ->get(['slug', 'updated_at']);

        foreach ($categories as $category) {
            foreach ($locales as $locale) {
                $sitemap->add($this->makeUrl($locale, '/' . $category->slug, $category->updated_at));
            }
        }

        $posts = Blog::query()
            ->published()
            ->whereNotNull('slug')
            ->with(['category:id,slug'])
            ->get(['id', 'blog_category_id', 'slug', 'updated_at', 'published_at']);

        foreach ($posts as $post) {
            $categorySlug = (string) ($post->category?->slug ?? '');
            if ($categorySlug === '') {
                continue;
            }

            $path = '/' . $categorySlug . '/' . $post->slug;
            $lastMod = $post->updated_at ?? $post->published_at;

            foreach ($locales as $locale) {
                $sitemap->add($this->makeUrl($locale, $path, $lastMod));
            }
        }
    }
}
