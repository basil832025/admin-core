<?php

namespace App\Services\Callcenter;

use App\Models\Shop\Product;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class TimeshopCatalogService
{
    public const SOURCE_ID = 'timeshop';

    public function source(): array
    {
        return [
            'id' => self::SOURCE_ID,
            'name' => (string) config('services.timeshop.menu_label', 'Timeshop'),
        ];
    }

    public function categories(): Collection
    {
        $rows = collect(DB::connection('timeshop')->select(<<<'SQL'
            SELECT MIN(d.variant_id) as id, MIN(d.variant_id) as kod, d.variant as name
            FROM cscart_product_features_values f, cscart_product_feature_variant_descriptions d
            WHERE f.feature_id = 39
              AND f.variant_id = d.variant_id
            GROUP BY d.variant
            ORDER BY d.variant
        SQL));

        if ($rows->isEmpty()) {
            $rows = collect(DB::connection('timeshop')->select(<<<'SQL'
                SELECT d.variant_id as id, d.variant_id as kod, d.variant as name
                FROM cscart_product_feature_variant_descriptions d
                WHERE EXISTS (
                    SELECT 1
                    FROM cscart_product_features_values f
                    WHERE f.feature_id = 39
                      AND f.variant_id = d.variant_id
                )
                ORDER BY d.variant
            SQL));
        }

        return $rows->map(fn (object $row): array => [
            'id' => (string) $row->id,
            'kod' => (string) $row->kod,
            'name' => (string) $row->name,
        ])->values();
    }

    public function products(string $search = '', string $categoryId = '', string $sort = 'popular', int $page = 1, int $perPage = 100): array
    {
        $query = DB::connection('timeshop')->table('cscart_product_descriptions as p')
            ->join('cscart_products as cp', 'cp.product_id', '=', 'p.product_id')
            ->join('cscart_product_prices as pp', 'pp.product_id', '=', 'cp.product_id')
            ->leftJoin('cscart_images_links as il', function ($join): void {
                $join->on('il.object_id', '=', 'cp.product_id')
                    ->where('il.object_type', '=', 'product')
                    ->where('il.type', '=', 'M');
            })
            ->leftJoin('cscart_images as img', 'img.image_id', '=', 'il.image_id')
            ->leftJoin('cscart_images as detailed', 'detailed.image_id', '=', 'il.detailed_id')
            ->where('p.lang_code', 'ua')
            ->selectRaw(<<<'SQL'
                cp.product_id,
                cp.product_code as kod,
                cp.articule as article,
                p.product as name,
                IFNULL((
                    SELECT f.variant_id
                    FROM cscart_product_features_values f
                    WHERE f.feature_id = 39
                      AND f.lang_code = "ua"
                      AND f.product_id = p.product_id
                    LIMIT 1
                ), 216) as grp,
                ROUND(pp.price) as price_out,
                ROUND(COALESCE(NULLIF(cp.price_out_old, 0), NULLIF(cp.list_price, 0), pp.price)) as old_price,
                COALESCE(detailed.image_id, img.image_id) as image_id,
                COALESCE(detailed.image_path, img.image_path) as image_path
            SQL);

        if ($categoryId !== '') {
            $query->having('grp', '=', $categoryId);
        }

        if ($search !== '') {
            $needle = '%' . mb_strtolower($search) . '%';
            $query->where(function ($where) use ($needle): void {
                $where->whereRaw('LOWER(p.product) LIKE ?', [$needle])
                    ->orWhereRaw('LOWER(cp.product_code) LIKE ?', [$needle])
                    ->orWhereRaw('CAST(cp.product_id AS CHAR) LIKE ?', [$needle])
                    ->orWhereRaw('CAST(cp.articule AS CHAR) LIKE ?', [$needle]);
            });
        }

        match ($sort) {
            'price_asc' => $query->orderBy('price_out')->orderBy('name'),
            'price_desc' => $query->orderByDesc('price_out')->orderBy('name'),
            'new' => $query->orderByDesc('cp.timestamp')->orderBy('name'),
            default => $query->orderBy('name'),
        };

        $rows = $query
            ->offset(($page - 1) * $perPage)
            ->limit($perPage + 1)
            ->get();

        $hasMore = $rows->count() > $perPage;
        $rows = $hasMore ? $rows->take($perPage)->values() : $rows->values();

        return [
            'products' => $rows->map(fn (object $row): array => $this->formatProduct($row))->all(),
            'has_more' => $hasMore,
        ];
    }

    public function findProduct(string|int $productId): ?array
    {
        $row = DB::connection('timeshop')->table('cscart_product_descriptions as p')
            ->join('cscart_products as cp', 'cp.product_id', '=', 'p.product_id')
            ->join('cscart_product_prices as pp', 'pp.product_id', '=', 'cp.product_id')
            ->leftJoin('cscart_images_links as il', function ($join): void {
                $join->on('il.object_id', '=', 'cp.product_id')
                    ->where('il.object_type', '=', 'product')
                    ->where('il.type', '=', 'M');
            })
            ->leftJoin('cscart_images as img', 'img.image_id', '=', 'il.image_id')
            ->leftJoin('cscart_images as detailed', 'detailed.image_id', '=', 'il.detailed_id')
            ->where('p.lang_code', 'ua')
            ->where('cp.product_id', (int) $productId)
            ->selectRaw(<<<'SQL'
                cp.product_id,
                cp.product_code as kod,
                cp.articule as article,
                p.product as name,
                IFNULL((
                    SELECT f.variant_id
                    FROM cscart_product_features_values f
                    WHERE f.feature_id = 39
                      AND f.lang_code = "ua"
                      AND f.product_id = p.product_id
                    LIMIT 1
                ), 216) as grp,
                ROUND(pp.price) as price_out,
                ROUND(COALESCE(NULLIF(cp.price_out_old, 0), NULLIF(cp.list_price, 0), pp.price)) as old_price,
                COALESCE(detailed.image_id, img.image_id) as image_id,
                COALESCE(detailed.image_path, img.image_path) as image_path
            SQL)
            ->first();

        return $row ? $this->formatProduct($row) : null;
    }

    public function ensureLocalProduct(string|int $productId): ?Product
    {
        $remote = $this->findProduct($productId);

        if (! $remote) {
            return null;
        }

        $externalId = (string) $remote['external_id'];
        $sku = 'timeshop-' . $externalId;
        $name = (string) ($remote['title'] ?: ('Timeshop #' . $externalId));
        $price = (float) ($remote['price'] ?? 0);
        $oldPrice = (float) ($remote['old_price'] ?? 0);

        $product = Product::query()->firstOrNew(['sku' => $sku]);

        if (! $product->exists) {
            $product->slug = $this->uniqueSlug('timeshop-' . $externalId);
        }

        $product->fill([
            'title' => [
                'uk' => $name,
                'ru' => $name,
                'en' => $name,
            ],
            'short_name' => $name,
            'description' => [
                'uk' => '',
                'ru' => '',
                'en' => '',
            ],
            'price' => $price,
            'old_price' => $oldPrice > $price ? $oldPrice : null,
            'main_image' => $this->absoluteImageUrl((string) ($remote['image'] ?? '')),
            'code2' => $externalId,
            'in_stock' => true,
            'quantity' => 999,
            'is_imported' => true,
        ]);

        $product->save();

        return $product;
    }

    protected function formatProduct(object $row): array
    {
        $price = (float) ($row->price_out ?? 0);
        $oldPrice = (float) ($row->old_price ?? 0);

        return [
            'id' => 'timeshop:' . (string) $row->product_id,
            'external_id' => (string) $row->product_id,
            'title' => (string) ($row->name ?? ''),
            'description' => trim('Код: ' . (string) ($row->kod ?? '') . ((string) ($row->article ?? '') !== '' ? ' · Артикул: ' . (string) $row->article : '')),
            'image' => $this->imageUrl($row->image_id ?? null, $row->image_path ?? null),
            'has_variants' => false,
            'price' => $price,
            'old_price' => $oldPrice > $price ? $oldPrice : 0,
            'discount_percent' => $oldPrice > $price && $price > 0 ? (int) round((($oldPrice - $price) / $oldPrice) * 100) : null,
            'is_new' => false,
            'is_hit' => false,
            'is_promo' => $oldPrice > $price,
            'is_vegan' => false,
            'is_product_of_day' => false,
            'is_spicy' => false,
            'unit' => (string) ($row->kod ?? 'Порція'),
            'variants' => [],
        ];
    }

    protected function imageUrl(mixed $imageId, mixed $imagePath): ?string
    {
        $path = trim((string) $imagePath);

        if ($path === '') {
            return null;
        }

        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://') || str_starts_with($path, '//')) {
            return str_starts_with($path, '//') ? ('https:' . $path) : $path;
        }

        $baseUrl = rtrim((string) config('services.timeshop.asset_url', ''), '/');
        $folder = max(0, (int) floor(((int) $imageId) / 1000));
        $relative = 'images/detailed/' . $folder . '/' . ltrim($path, '/');

        return $baseUrl !== '' ? ($baseUrl . '/' . $relative) : ('/' . $relative);
    }

    protected function absoluteImageUrl(string $url): string
    {
        $url = trim($url);

        if ($url === '' || str_starts_with($url, 'http://') || str_starts_with($url, 'https://')) {
            return $url;
        }

        if (str_starts_with($url, '//')) {
            return 'https:' . $url;
        }

        return url($url);
    }

    protected function uniqueSlug(string $base): string
    {
        $slug = Str::slug($base) ?: Str::random(8);
        $candidate = $slug;
        $i = 2;

        while (Product::query()->where('slug', $candidate)->exists()) {
            $candidate = $slug . '-' . $i;
            $i++;
        }

        return $candidate;
    }
}
