<?php

declare(strict_types=1);

use App\Models\Shop\Product;
use App\Models\Shop\ProductCategory;
use Illuminate\Contracts\Console\Kernel;

date_default_timezone_set('UTC');

const BASE_URL = 'https://3piroga.ua';
const FEED_FILE = 'feed_ua.xml';
const FEED_LOCALE = 'uk';
const GOOGLE_PRODUCT_CATEGORY_ID = '5408';
const PRODUCT_TYPE = 'Їжа > Випічка > Пироги';
const BRAND = '3piroga';

const SIZE_CHARACTERISTIC_SLUGS = [
    'rozmir-pirogiv',
    'rozmiri-insi',
];

const WEIGHT_CHARACTERISTIC_SLUGS = [
    'vaga',
    'vaga-grami',
    'vaga-setiv',
];

bootstrapLaravel();
generateXml();

function bootstrapLaravel(): void
{
    require_once __DIR__ . '/vendor/autoload.php';

    $app = require_once __DIR__ . '/bootstrap/app.php';
    $app->make(Kernel::class)->bootstrap();
}

/**
 * @return array<int, array<string, string>>
 */
function fetchProductsFromDatabase(): array
{
    $feedVersion = gmdate('Ymd');

    $categoriesById = ProductCategory::query()
        ->select(['id', 'slug'])
        ->pluck('slug', 'id');

    $parents = Product::query()
        ->select(['id', 'title', 'description', 'short_desc', 'slug', 'main_image', 'category_id', 'price', 'in_stock', 'is_imported'])
        ->whereNull('parent_id')
        ->where('in_stock', 1)
        ->where(function ($q): void {
            $q->whereNull('is_imported')->orWhere('is_imported', 0);
        })
        ->whereHas('mainCategory', function ($q): void {
            $q->where('slug', 'not like', 'src-%-import');
        })
        ->with([
            'children' => function ($q): void {
                $q->select(['id', 'title', 'description', 'short_desc', 'slug', 'main_image', 'parent_id', 'price', 'in_stock', 'is_imported'])
                    ->where('in_stock', 1)
                    ->where(function ($subQ): void {
                        $subQ->whereNull('is_imported')->orWhere('is_imported', 0);
                    })
                    ->orderBy('sort')
                    ->orderBy('id');
            },
            'productCharacteristicValues.characteristic:id,slug',
            'productCharacteristicValues.characteristicValue:id,value',
            'children.productCharacteristicValues.characteristic:id,slug',
            'children.productCharacteristicValues.characteristicValue:id,value',
        ])
        ->orderBy('sort')
        ->orderBy('id')
        ->get();

    $products = [];
    foreach ($parents as $parent) {
        $categorySlug = $categoriesById->get($parent->category_id);
        if (!is_string($categorySlug) || $categorySlug === '' || $parent->slug === null || $parent->slug === '') {
            continue;
        }

        $variantPool = collect([$parent])->merge($parent->children ?? collect())
            ->filter(static function (Product $p): bool {
                return (int) ($p->in_stock ?? 0) === 1
                    && ((int) ($p->is_imported ?? 0) === 0);
            })
            ->values();

        $pricedVariants = $variantPool
            ->filter(static fn (Product $p): bool => is_numeric((string) $p->price) && (float) $p->price > 0)
            ->sortBy(static fn (Product $p): float => (float) $p->price)
            ->values();

        if ($pricedVariants->isEmpty()) {
            continue;
        }

        /** @var Product $minPriceVariant */
        $minPriceVariant = $pricedVariants->first();

        $title = pickLocalizedField($parent, 'title', FEED_LOCALE);
        if ($title === '') {
            continue;
        }

        $description = pickLocalizedField($parent, 'short_desc', FEED_LOCALE);
        if ($description === '') {
            $description = pickLocalizedField($parent, 'description', FEED_LOCALE);
        }
        $description = trim(strip_tags($description));

        $imagePath = trim((string) ($minPriceVariant->main_image ?: $parent->main_image));
        if ($imagePath === '') {
            continue;
        }

        $imageLink = appendQueryParam(resolveImageUrl($imagePath), 'v', $feedVersion);
        $link = buildProductUrl($categorySlug, (string) $parent->slug);

        $size = extractCharacteristicText($minPriceVariant, SIZE_CHARACTERISTIC_SLUGS, FEED_LOCALE);
        $weightText = extractCharacteristicText($minPriceVariant, WEIGHT_CHARACTERISTIC_SLUGS, FEED_LOCALE);
        if ($weightText === '') {
            $weightText = '0';
        }

        $slugLast = (string) $parent->slug;
        $productId = mb_substr('ua_' . $slugLast, 0, 50);

        $products[] = [
            'id' => $productId,
            'title' => $title,
            'link' => $link,
            'image_link' => $imageLink,
            'description' => $description,
            'brand' => BRAND,
            'condition' => 'new',
            'availability' => 'in stock',
            'price' => number_format((float) $minPriceVariant->price, 2, '.', '') . ' UAH',
            'size' => $size,
            'weight' => $weightText,
            'google_product_category' => GOOGLE_PRODUCT_CATEGORY_ID,
            'product_type' => PRODUCT_TYPE,
        ];
    }

    return $products;
}

function pickLocalizedField(Product $product, string $field, string $locale): string
{
    $raw = $product->getAttributes()[$field] ?? null;

    if (is_string($raw) && $raw !== '') {
        $decoded = json_decode($raw, true);
        if (is_array($decoded)) {
            $value = (string) ($decoded[$locale] ?? $decoded['uk'] ?? $decoded['ru'] ?? $decoded['en'] ?? reset($decoded) ?? '');
            return trim($value);
        }

        return trim($raw);
    }

    $value = $product->{$field} ?? null;
    if (is_array($value)) {
        return trim((string) ($value[$locale] ?? $value['uk'] ?? $value['ru'] ?? $value['en'] ?? reset($value) ?? ''));
    }

    return trim((string) $value);
}

function buildProductUrl(string $categorySlug, string $productSlug): string
{
    return rtrim(BASE_URL, '/') . '/' . ltrim($categorySlug, '/') . '/' . ltrim($productSlug, '/');
}

function resolveImageUrl(string $path): string
{
    if (preg_match('~^https?://~i', $path) === 1) {
        return $path;
    }

    return rtrim(BASE_URL, '/') . '/storage/' . ltrim($path, '/');
}

/**
 * @param array<int, string> $characteristicSlugs
 */
function extractCharacteristicText(Product $product, array $characteristicSlugs, string $locale): string
{
    $rows = $product->productCharacteristicValues ?? collect();

    foreach ($rows as $row) {
        $slug = $row->characteristic?->slug;
        if (!is_string($slug) || !in_array($slug, $characteristicSlugs, true)) {
            continue;
        }

        $fromValue = extractValueFromCharacteristicValue($row->characteristicValue, $locale);
        if ($fromValue !== '') {
            return $fromValue;
        }

        $fromText = trim((string) ($row->value_text ?? ''));
        if ($fromText !== '') {
            return $fromText;
        }

        if ($row->value_number !== null) {
            return trim((string) $row->value_number);
        }
    }

    return '';
}

function extractValueFromCharacteristicValue(mixed $characteristicValue, string $locale): string
{
    if ($characteristicValue === null) {
        return '';
    }

    $value = $characteristicValue->value ?? null;

    if (is_array($value)) {
        return trim((string) ($value[$locale] ?? $value['uk'] ?? $value['ru'] ?? $value['en'] ?? reset($value) ?? ''));
    }

    if (is_string($value) && $value !== '') {
        $decoded = json_decode($value, true);
        if (is_array($decoded)) {
            return trim((string) ($decoded[$locale] ?? $decoded['uk'] ?? $decoded['ru'] ?? $decoded['en'] ?? reset($decoded) ?? ''));
        }

        return trim($value);
    }

    return '';
}

function appendQueryParam(string $url, string $key, string $value): string
{
    $separator = str_contains($url, '?') ? '&' : '?';
    return $url . $separator . rawurlencode($key) . '=' . rawurlencode($value);
}

/**
 * @param array<int, array<string, string>> $products
 */
function writeXml(array $products): void
{
    $rss = new SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><rss version="2.0" xmlns:g="http://base.google.com/ns/1.0"></rss>');
    $channel = $rss->addChild('channel');
    $channel->addChild('title', '3 Пирога — Каталог товарів (UA)');
    $channel->addChild('link', BASE_URL);
    $channel->addChild('description', 'Фід товарів з сайту 3piroga.ua (UA)');

    foreach ($products as $product) {
        $item = $channel->addChild('item');
        $item->addChild('id', xml($product['id']), 'http://base.google.com/ns/1.0');
        $item->addChild('title', xml($product['title']), 'http://base.google.com/ns/1.0');
        $item->addChild('description', xml($product['description']), 'http://base.google.com/ns/1.0');
        $item->addChild('link', xml($product['link']), 'http://base.google.com/ns/1.0');
        $item->addChild('image_link', xml($product['image_link']), 'http://base.google.com/ns/1.0');
        $item->addChild('availability', $product['availability'], 'http://base.google.com/ns/1.0');
        $item->addChild('condition', $product['condition'], 'http://base.google.com/ns/1.0');
        $item->addChild('brand', xml($product['brand']), 'http://base.google.com/ns/1.0');
        $item->addChild('price', $product['price'], 'http://base.google.com/ns/1.0');
        $item->addChild('google_product_category', $product['google_product_category'], 'http://base.google.com/ns/1.0');
        $item->addChild('product_type', xml($product['product_type']), 'http://base.google.com/ns/1.0');
        $item->addChild('identifier_exists', 'false', 'http://base.google.com/ns/1.0');

        $weight = preg_replace('/[^0-9.,]/u', '', $product['weight']) ?? '';
        $weight = str_replace(',', '.', trim($weight));
        if ($weight === '') {
            $weight = '0';
        }

        $item->addChild('shipping_weight', $weight . ' g', 'http://base.google.com/ns/1.0');
    }

    $dom = new DOMDocument('1.0', 'UTF-8');
    $dom->preserveWhiteSpace = false;
    $dom->formatOutput = true;
    $dom->loadXML($rss->asXML() ?: '');

    $outputFile = __DIR__ . DIRECTORY_SEPARATOR . FEED_FILE;
    file_put_contents($outputFile, $dom->saveXML());

    echo 'XML-файл ' . $outputFile . ' оновлено: ' . gmdate('c') . PHP_EOL;
    echo 'Товарів у фіді: ' . count($products) . PHP_EOL;
}

function xml(string $value): string
{
    return htmlspecialchars($value, ENT_XML1 | ENT_COMPAT, 'UTF-8');
}

function generateXml(): void
{
    $products = fetchProductsFromDatabase();
    writeXml($products);
}
