<?php

namespace App\Http\Controllers\Api;

use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Models\Shop\Order;
use App\Models\Shop\OrderItem;
use App\Models\Shop\Product;
use App\Models\Shop\ProductCategory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class KioskController extends Controller
{
    private const EXCLUDED_CATEGORY_SLUG_PREFIX = 'src-pirogovaya';

    public function categories(Request $request): JsonResponse
    {
        $locale = $this->resolveLocale($request);

        $categories = ProductCategory::query()
            ->where('is_visible', true)
            ->where('slug', 'not like', self::EXCLUDED_CATEGORY_SLUG_PREFIX.'%')
            ->whereHas('products', function ($query): void {
                $query->whereNull('parent_id')
                    ->where('in_stock', true)
                    ->whereHas('mainCategory', function ($categoryQuery): void {
                        $categoryQuery->where('slug', 'not like', self::EXCLUDED_CATEGORY_SLUG_PREFIX.'%');
                    });
            })
            ->orderBy('order')
            ->orderBy('id')
            ->get()
            ->map(function (ProductCategory $category) use ($locale): array {
                return [
                    'id' => (int) $category->id,
                    'slug' => (string) ($category->slug ?? ''),
                    'name' => $this->translate($category, 'title', $locale),
                ];
            })
            ->values();

        return response()->json([
            'ok' => true,
            'categories' => $categories,
        ]);
    }

    public function products(Request $request): JsonResponse
    {
        $locale = $this->resolveLocale($request);
        $categoryId = (int) $request->integer('category_id', 0);
        $search = trim((string) $request->query('q', ''));

        $products = Product::query()
            ->select(['id', 'title', 'short_name', 'description', 'price', 'main_image', 'category_id', 'sort'])
            ->whereNull('parent_id')
            ->where('in_stock', true)
            ->whereHas('mainCategory', function ($query): void {
                $query->where('slug', 'not like', self::EXCLUDED_CATEGORY_SLUG_PREFIX.'%');
            })
            ->when($categoryId > 0, fn ($query) => $query->where('category_id', $categoryId))
            ->when($search !== '', function ($query) use ($search, $locale): void {
                $needle = mb_strtolower($search);
                $query->where(function ($w) use ($needle, $locale): void {
                    $w->whereRaw(
                        "LOWER(CASE WHEN JSON_VALID(title) THEN JSON_UNQUOTE(JSON_EXTRACT(title, '$.\"{$locale}\"')) ELSE title END) LIKE ?",
                        ["%{$needle}%"]
                    )
                        ->orWhereRaw('LOWER(short_name) LIKE ?', ["%{$needle}%"]);
                });
            })
            ->orderBy('sort')
            ->orderBy('id')
            ->limit(300)
            ->get()
            ->map(function (Product $product) use ($locale): array {
                return [
                    'id' => (int) $product->id,
                    'category_id' => (int) ($product->category_id ?? 0),
                    'title' => $this->productTitle($product, $locale),
                    'description' => trim(strip_tags($this->translate($product, 'description', $locale))),
                    'image' => $product->main_image_url,
                    'price' => (float) ($product->price ?? 0),
                ];
            })
            ->values();

        return response()->json([
            'ok' => true,
            'products' => $products,
        ]);
    }

    public function storeOrder(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer', 'min:1'],
            'items.*.qty' => ['required', 'integer', 'min:1', 'max:99'],
            'customer_name' => ['nullable', 'string', 'max:120'],
            'customer_phone' => ['nullable', 'string', 'max:30'],
            'payment_method' => ['nullable', 'in:card,cash,pos'],
            'comment' => ['nullable', 'string', 'max:500'],
        ]);

        $items = collect($validated['items']);
        $productIds = $items->pluck('product_id')->map(fn ($id) => (int) $id)->unique()->values();

        $products = Product::query()
            ->select(['id', 'price', 'in_stock', 'title', 'short_name', 'sku'])
            ->whereIn('id', $productIds)
            ->get()
            ->keyBy('id');

        $invalidIds = $productIds->filter(function (int $id) use ($products): bool {
            $product = $products->get($id);

            return ! $product || ! $product->in_stock;
        })->values();

        if ($invalidIds->isNotEmpty()) {
            return response()->json([
                'ok' => false,
                'message' => 'Some products are unavailable.',
                'unavailable_product_ids' => $invalidIds,
            ], 422);
        }

        $payment = match ($validated['payment_method'] ?? 'card') {
            'cash' => 2,
            'pos' => 9,
            default => 1,
        };

        $order = DB::transaction(function () use ($items, $products, $validated, $payment): Order {
            $order = Order::create([
                'status' => OrderStatus::New,
                'currency' => 'UAH',
                'shipping_method' => 'kiosk',
                'self_pickup' => true,
                'as_soon_possible' => true,
                'payment' => $payment,
                'notes' => $validated['comment'] ?? null,
                'short_name' => $validated['customer_name'] ?? null,
            ]);

            $subtotal = 0.0;

            foreach ($items as $item) {
                $product = $products->get((int) $item['product_id']);
                $qty = (int) $item['qty'];
                $unitPrice = (float) ($product->price ?? 0);
                $lineTotal = $unitPrice * $qty;
                $subtotal += $lineTotal;

                OrderItem::create([
                    'shop_order_id' => $order->id,
                    'product_id' => (int) $product->id,
                    'sku' => $product->sku,
                    'qty' => $qty,
                    'currency' => 'UAH',
                    'unit_price' => $unitPrice,
                    'subtotal' => $lineTotal,
                    'total' => $lineTotal,
                    'product_snapshot' => [
                        'title' => $product->short_name ?: $this->translate($product, 'title', 'ru'),
                        'price' => $unitPrice,
                    ],
                ]);
            }

            $order->update([
                'subtotal' => $subtotal,
                'total_price' => $subtotal,
                'grand_total' => $subtotal,
                'discount_total' => 0,
                'shipping_total' => 0,
                'tax_total' => 0,
                'sale_prc' => 0,
                'sale_sum' => 0,
                'total_price_sale' => $subtotal,
                'notes' => trim(implode(' | ', array_filter([
                    $validated['comment'] ?? null,
                    ! empty($validated['customer_phone']) ? 'Phone: '.$validated['customer_phone'] : null,
                ]))),
            ]);

            return $order->fresh('items');
        });

        return response()->json([
            'ok' => true,
            'order' => [
                'id' => (int) $order->id,
                'number' => (string) ($order->number ?? ''),
                'status' => $order->status?->value ?? (string) $order->status,
                'items_count' => (int) $order->items->sum('qty'),
                'total' => (float) ($order->grand_total ?? $order->total_price ?? 0),
                'currency' => (string) ($order->currency ?? 'UAH'),
            ],
        ], 201);
    }

    private function resolveLocale(Request $request): string
    {
        $locale = strtolower((string) $request->query('locale', 'ru'));

        return in_array($locale, ['uk', 'ru', 'en'], true) ? $locale : 'ru';
    }

    private function translate(Model $model, string $field, string $locale): string
    {
        if (method_exists($model, 'getTranslation')) {
            return (string) (
                $model->getTranslation($field, $locale, false)
                ?: $model->getTranslation($field, 'uk', false)
                ?: $model->getTranslation($field, 'ru', false)
                ?: $model->getTranslation($field, 'en', false)
                ?: ''
            );
        }

        return (string) ($model->{$field} ?? '');
    }

    private function productTitle(Product $product, string $locale): string
    {
        if (! empty($product->short_name)) {
            return (string) $product->short_name;
        }

        return $this->translate($product, 'title', $locale);
    }
}
