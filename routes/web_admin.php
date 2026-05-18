<?php

use App\Http\Controllers\Integrations\BinotelWebhookController;
use Illuminate\Support\Facades\Route;

Route::get('/admin/switch-locale/{locale}', function (string $locale) {
    // Получаем список разрешенных языков из БД или используем дефолтные
    $allowed = [];
    try {
        if (\Illuminate\Support\Facades\Schema::hasTable('bs_languages')) {
            $allowed = \App\Models\Language::where('active', true)
                ->pluck('code')
                ->map(fn($c) => strtolower($c))
                ->toArray();
        }
    } catch (\Exception $e) {
        // Если таблицы нет, используем дефолтные
    }

    // Если языков нет, используем дефолтные
    if (empty($allowed)) {
        $allowed = ['ru', 'uk', 'en'];
    }

    // Проверяем, что локаль разрешена
    $locale = strtolower($locale);
    if (!in_array($locale, $allowed, true)) {
        $locale = config('app.locale', 'ru');
    }

    // Сохраняем в сессии под отдельным ключом для админки
    session(['admin_locale' => $locale]);

    // Устанавливаем локаль приложения
    app()->setLocale($locale);

    // Устанавливаем локаль для Carbon (даты)
    if (class_exists(\Carbon\Carbon::class)) {
        \Carbon\Carbon::setLocale($locale);
    }

    // Редиректим обратно в админку, используя сохраненный URL из сессии
    $previousUrl = session('admin_previous_url');
    $referer = request()->header('referer');

    // Приоритет 1: Если referer содержит /admin, используем его
    if ($referer && str_contains($referer, '/admin')) {
        return redirect($referer);
    }

    // Приоритет 2: Если есть сохраненный URL админки, используем его
    if ($previousUrl && str_contains($previousUrl, '/admin')) {
        return redirect($previousUrl);
    }

    // Приоритет 3: Иначе редиректим на главную админки
    return redirect('/admin');
})
    ->name('admin.switch-locale')
    ->middleware(['web', 'auth:admin']);         // доступ только залогиненному в админке

Route::get('/admin/callcenter/clients/phone-suggestions', function (\Illuminate\Http\Request $request) {
    $query = preg_replace('/\D+/', '', (string) $request->query('q', ''));

    if (strlen($query) < 3) {
        return response()->json([]);
    }

    $clients = \App\Models\Shop\Client::query()
        ->select('id', 'name', 'phone')
        ->whereRaw("REGEXP_REPLACE(phone, '[^0-9]', '') LIKE ?", ["{$query}%"])
        ->orderBy('name')
        ->limit(20)
        ->get()
        ->map(function (\App\Models\Shop\Client $client): array {
            return [
                'id' => (int) $client->id,
                'phone' => (string) ($client->phone ?? ''),
                'name' => (string) ($client->name ?? ''),
            ];
        })
        ->values();

    return response()->json($clients);
})
    ->name('admin.callcenter.phone-suggestions')
    ->middleware(['web', 'auth:admin']);

Route::get('/admin/integrations/binotel/incoming-call/next', [BinotelWebhookController::class, 'nextIncomingCall'])
    ->name('admin.integrations.binotel.incoming-call.next')
    ->middleware(['web', 'auth:admin']);

Route::post('/admin/print-templates/ckeditor-upload', function (\Illuminate\Http\Request $request) {
    $validated = $request->validate([
        'upload' => ['required', 'file', 'image', 'max:5120'],
    ]);

    /** @var \Illuminate\Http\UploadedFile $file */
    $file = $validated['upload'];
    $path = $file->store('settings/print-templates', 'public');

    return response()->json([
        'url' => \Illuminate\Support\Facades\Storage::disk('public')->url($path),
    ]);
})
    ->name('admin.print-templates.ckeditor-upload')
    ->middleware(['web', 'auth:admin']);

Route::get('/admin/callcenter/courier-comment/next', function () {
    $orders = \App\Models\Callcenter\Order::query()
        ->whereNotNull('courier_comment')
        ->where('courier_comment', '!=', '')
        ->where(function ($query): void {
            $query->whereNull('courier_comment_read_at')
                ->orWhereColumn('courier_comment_read_at', '<', 'courier_comment_changed_at');
        })
        ->orderBy('courier_comment_changed_at')
        ->limit(30)
        ->get();

    $comments = $orders
        ->map(function (\App\Models\Callcenter\Order $order): array {
            $comment = trim((string) $order->courier_comment);

            return [
                'order_id' => (int) $order->id,
                'order_number' => (string) ($order->number ?? $order->id),
                'text' => $comment,
                'updated_at' => optional($order->courier_comment_changed_at ?? $order->updated_at)?->toDateTimeString(),
                'signature' => sha1($order->id . '|' . $comment),
                'edit_url' => url('/admin/callcenter/orders/' . $order->id . '/edit'),
            ];
        })
        ->filter(fn (array $item): bool => $item['text'] !== '')
        ->values();

    return response()->json([
        'comments' => $comments,
    ]);
})
    ->name('admin.callcenter.courier-comment.next')
    ->middleware(['web', 'auth:admin']);

Route::post('/admin/callcenter/courier-comment/mark-read', function (\Illuminate\Http\Request $request) {
    $data = $request->validate([
        'ids' => ['required', 'array'],
        'ids.*' => ['integer', 'min:1'],
    ]);

    \App\Models\Callcenter\Order::query()
        ->whereIn('id', $data['ids'])
        ->update([
            'courier_comment_read_at' => now(),
        ]);

    return response()->json(['ok' => true]);
})
    ->name('admin.callcenter.courier-comment.mark-read')
    ->middleware(['web', 'auth:admin']);

Route::post('/admin/client-addresses/{address}/coords', function (\Illuminate\Http\Request $request, \App\Models\Shop\ClientAddress $address) {
    $payload = $request->validate([
        'latitude' => ['required', 'numeric'],
        'longitude' => ['required', 'numeric'],
        'formatted_address' => ['nullable', 'string', 'max:255'],
        'street_place_id' => ['nullable', 'string', 'max:255'],
    ]);

    \Log::info('Admin address coords update: request', [
        'address_id' => $address->id,
        'client_id' => $address->client_id,
        'existing_latitude' => $address->latitude,
        'existing_longitude' => $address->longitude,
        'latitude' => $payload['latitude'],
        'longitude' => $payload['longitude'],
        'has_formatted_address' => ! empty($payload['formatted_address'] ?? null),
        'has_street_place_id' => ! empty($payload['street_place_id'] ?? null),
    ]);

    $hasExistingCoords = ! empty($address->latitude) && ! empty($address->longitude);
    $force = (bool) $request->boolean('force', false);

    if ($hasExistingCoords && ! $force) {
        \Log::info('Admin address coords update: skipped (existing coords protected)', [
            'address_id' => $address->id,
            'existing_latitude' => $address->latitude,
            'existing_longitude' => $address->longitude,
        ]);

        return response()->json([
            'ok' => true,
            'skipped' => true,
            'reason' => 'existing_coords',
        ]);
    }

    $address->update([
        'latitude' => (float) $payload['latitude'],
        'longitude' => (float) $payload['longitude'],
        'formatted_address' => $payload['formatted_address'] ?? $address->formatted_address,
        'street_place_id' => $payload['street_place_id'] ?? $address->street_place_id,
    ]);

    \Log::info('Admin address coords update: saved', [
        'address_id' => $address->id,
        'saved_latitude' => $address->fresh()?->latitude,
        'saved_longitude' => $address->fresh()?->longitude,
    ]);

    return response()->json(['ok' => true]);
})
    ->name('admin.client-addresses.coords')
    ->middleware(['web', 'auth:admin']);

Route::get('/admin/callcenter/menu-catalog', function (\Illuminate\Http\Request $request) {
    $search = trim((string) $request->query('q', ''));
    $sort = trim((string) $request->query('sort', 'popular'));
    $page = max(1, (int) $request->query('page', 1));
    $perPage = 100;
    $categoryIdRaw = trim((string) $request->query('category_id', ''));
    $localCategoryId = (int) $categoryIdRaw;
    $sourceIdRaw = (string) $request->query('source_id', $request->query('order_source_id', '0'));
    $selectedSourceId = is_numeric($sourceIdRaw) ? (int) $sourceIdRaw : 0;
    $locales = \App\Models\Setting::getActiveLocales();

    if (empty($locales)) {
        $locales = ['uk', 'ru', 'en'];
    }

    $mainSiteName = (string) (\App\Models\Setting::value('site_name') ?: 'Основной сайт');
    $sources = collect([
        [
            'id' => 0,
            'name' => $mainSiteName,
        ],
    ])->merge(
        \App\Models\Callcenter\Source::query()
            ->where('is_active', true)
            ->where('sync_enabled', true)
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (\App\Models\Callcenter\Source $source) => [
                'id' => (int) $source->id,
                'name' => (string) $source->name,
            ])
    )->values();

    if (! $sources->contains(fn (array $source): bool => (int) $source['id'] === $selectedSourceId)) {
        $selectedSourceId = 0;
    }

    $applySourceFilter = function (\Illuminate\Database\Eloquent\Builder $query) use ($selectedSourceId): void {
        if ($selectedSourceId === 0) {
            $query->whereNotExists(function ($subQuery): void {
                $subQuery->select(\Illuminate\Support\Facades\DB::raw(1))
                    ->from('bs_cc_source_products as csp')
                    ->whereColumn('csp.local_product_id', 'bs_products.id');
            });

            return;
        }

        $query->whereExists(function ($subQuery) use ($selectedSourceId): void {
            $subQuery->select(\Illuminate\Support\Facades\DB::raw(1))
                ->from('bs_cc_source_products as csp')
                ->whereColumn('csp.local_product_id', 'bs_products.id')
                ->where('csp.source_id', $selectedSourceId);
        });
    };

    $needle = '%' . addcslashes(mb_strtolower($search), '%_') . '%';

    $compactDescription = static function (?string $value): string {
        $text = trim((string) preg_replace('/\s+/u', ' ', strip_tags((string) $value)));

        if ($text === '') {
            return '';
        }

        return \Illuminate\Support\Str::limit($text, 260);
    };

    $resolveDiscountPercent = static function (float|int|null $price, float|int|null $oldPrice): ?int {
        $price = (float) ($price ?? 0);
        $oldPrice = (float) ($oldPrice ?? 0);

        if ($price <= 0 || $oldPrice <= 0 || $oldPrice <= $price) {
            return null;
        }

        return (int) round(((($oldPrice - $price) / $oldPrice) * 100));
    };

    $applyMenuSort = static function (\Illuminate\Database\Eloquent\Builder $query, string $sort): void {
        switch ($sort) {
            case 'price_asc':
                $query->orderBy('price', 'asc')->orderBy('sort', 'asc')->orderBy('id', 'asc');
                break;
            case 'price_desc':
                $query->orderBy('price', 'desc')->orderBy('sort', 'asc')->orderBy('id', 'asc');
                break;
            case 'discount_asc':
                $query->orderByRaw("CASE WHEN old_price IS NOT NULL AND old_price > 0 AND old_price > price THEN 0 ELSE 1 END ASC")
                    ->orderByRaw("CASE WHEN old_price IS NOT NULL AND old_price > 0 AND old_price > price THEN ((old_price - price) / old_price) * 100 ELSE 0 END ASC")
                    ->orderBy('sort', 'asc')
                    ->orderBy('id', 'asc');
                break;
            case 'discount_desc':
                $query->orderByRaw("CASE WHEN old_price IS NOT NULL AND old_price > 0 AND old_price > price THEN 0 ELSE 1 END ASC")
                    ->orderByRaw("CASE WHEN old_price IS NOT NULL AND old_price > 0 AND old_price > price THEN ((old_price - price) / old_price) * 100 ELSE 0 END DESC")
                    ->orderBy('sort', 'asc')
                    ->orderBy('id', 'asc');
                break;
            case 'new':
                $query->orderByDesc('is_new')->orderBy('created_at', 'desc')->orderBy('sort', 'asc')->orderBy('id', 'asc');
                break;
            case 'popular':
            default:
                $query->orderByDesc('is_hit')->orderBy('sort', 'asc')->orderBy('id', 'asc');
                break;
        }
    };

    $sortPayloadProducts = static function (\Illuminate\Support\Collection $items, string $sort) use ($resolveDiscountPercent) {
        $resolveBasePrice = static function (array $product): float {
            if (! empty($product['variants']) && is_array($product['variants'])) {
                $prices = collect($product['variants'])
                    ->map(fn (array $variant): float => (float) ($variant['price'] ?? 0))
                    ->filter(fn (float $price): bool => $price > 0)
                    ->values();

                if ($prices->isNotEmpty()) {
                    return (float) $prices->min();
                }
            }

            return (float) ($product['price'] ?? 0);
        };

        $resolveBaseDiscount = static function (array $product) use ($resolveDiscountPercent): int {
            $discounts = collect($product['variants'] ?? [])
                ->map(fn (array $variant): int => (int) ($variant['discount_percent'] ?? $resolveDiscountPercent($variant['price'] ?? 0, $variant['old_price'] ?? 0) ?? 0))
                ->filter(fn (int $discount): bool => $discount > 0)
                ->values();

            if ($discounts->isNotEmpty()) {
                return (int) $discounts->max();
            }

            return (int) (($product['discount_percent'] ?? $resolveDiscountPercent($product['price'] ?? 0, $product['old_price'] ?? 0)) ?? 0);
        };

        $resolveWeight = static function (array $product, string $key): int {
            $variantWeight = collect($product['variants'] ?? [])
                ->contains(fn (array $variant): bool => ! empty($variant[$key]));

            return (! empty($product[$key]) || $variantWeight) ? 1 : 0;
        };

        $sorted = match ($sort) {
            'price_asc' => $items->sortBy(fn (array $product) => [$resolveBasePrice($product), (string) ($product['title'] ?? '')]),
            'price_desc' => $items->sortByDesc(fn (array $product) => [$resolveBasePrice($product), (string) ($product['title'] ?? '')]),
            'discount_asc' => $items->sortBy(fn (array $product) => [
                $resolveBaseDiscount($product) > 0 ? 0 : 1,
                $resolveBaseDiscount($product),
                (string) ($product['title'] ?? ''),
            ]),
            'discount_desc' => $items->sortBy(fn (array $product) => [
                $resolveBaseDiscount($product) > 0 ? 0 : 1,
                -1 * $resolveBaseDiscount($product),
                (string) ($product['title'] ?? ''),
            ]),
            'new' => $items->sortByDesc(fn (array $product) => [$resolveWeight($product, 'is_new'), (string) ($product['title'] ?? '')]),
            default => $items->sortByDesc(fn (array $product) => [$resolveWeight($product, 'is_hit'), (string) ($product['title'] ?? '')]),
        };

        return $sorted->values();
    };

    $normalizeImportedImage = static function (?string $image, ?\App\Models\Callcenter\Source $source): ?string {
        $img = trim((string) $image);
        if ($img === '') {
            return null;
        }

        if (str_starts_with($img, '//')) {
            return 'https:' . $img;
        }

        $base = rtrim((string) ($source?->base_url ?? ''), '/');

        if (str_starts_with($img, 'http://') || str_starts_with($img, 'https://')) {
            $sourceHost = parse_url($base, PHP_URL_HOST);
            $imageHost = parse_url($img, PHP_URL_HOST);

            if ($base !== '' && $sourceHost && $imageHost && strcasecmp($sourceHost, $imageHost) !== 0) {
                if (str_contains(mb_strtolower($imageHost), 'pirogovaya.online')) {
                    $path = (string) parse_url($img, PHP_URL_PATH);
                    $query = (string) parse_url($img, PHP_URL_QUERY);

                    return $base . '/' . ltrim($path, '/') . ($query !== '' ? ('?' . $query) : '');
                }
            }

            return $img;
        }

        if ($base === '') {
            return $img;
        }

        return $base . '/' . ltrim($img, '/');
    };

    $fallbackImportedImage = static function (?\App\Models\Callcenter\SourceProduct $row): ?string {
        if (! $row) {
            return null;
        }

        $base = rtrim((string) ($row->source?->base_url ?? ''), '/');
        if ($base === '') {
            return null;
        }

        $imageId = trim((string) ($row->external_parent_id ?: $row->external_id));
        if ($imageId === '') {
            return null;
        }

        return $base . '/images/catalog_products/' . $imageId . '.1.b.png';
    };

    $hasMore = false;

    if ($selectedSourceId > 0) {
        $categories = \App\Models\Callcenter\SourceCategory::query()
            ->where('source_id', $selectedSourceId)
            ->where('external_id', '!=', '__fallback__')
            ->whereIn('external_id', function ($query) use ($selectedSourceId): void {
                $query->select('external_category_id')
                    ->from('bs_cc_source_products')
                    ->where('source_id', $selectedSourceId)
                    ->whereNotNull('external_category_id')
                    ->groupBy('external_category_id');
            })
            ->orderBy('id')
            ->get(['external_id', 'title'])
            ->map(function (\App\Models\Callcenter\SourceCategory $category): array {
                $title = $category->title;
                $name = is_array($title)
                    ? (string) ($title['uk'] ?? $title['ru'] ?? $title['en'] ?? $category->external_id)
                    : (string) ($title ?: $category->external_id);

                return [
                    'id' => (string) $category->external_id,
                    'name' => $name,
                ];
            })
            ->values();

        $productsRows = \App\Models\Callcenter\SourceProduct::query()
            ->with([
                'localProduct:id,title,short_desc,description,main_image,parent_id,price,old_price,in_stock,is_new,is_hit,is_promo,is_vegan,is_product_of_day,is_spicy',
                'localProduct.parent:id,main_image',
                'source:id,base_url',
            ])
            ->where('source_id', $selectedSourceId)
            ->whereNotNull('local_product_id')
            ->whereHas('localProduct', fn ($q) => $q->where('in_stock', 1))
            ->when($categoryIdRaw !== '', fn ($q) => $q->where('external_category_id', $categoryIdRaw))
            ->when($search !== '', function ($q) use ($search): void {
                $needle = '%' . mb_strtolower($search) . '%';
                $q->whereRaw('LOWER(title) LIKE ?', [$needle]);
            })
            ->get();

        $productsPayload = $productsRows
            ->groupBy(fn (\App\Models\Callcenter\SourceProduct $row) => $row->external_parent_id ?: $row->external_id)
            ->map(function ($rows) use ($compactDescription, $normalizeImportedImage, $fallbackImportedImage, $resolveDiscountPercent) {
                $resolveImportedDescription = function (?\App\Models\Shop\Product $localProduct, \App\Models\Callcenter\SourceProduct $sourceProduct) use ($compactDescription): string {
                    $locale = app()->getLocale();

                    $payloadDescription = data_get($sourceProduct->payload, 'description');
                    if (! is_array($payloadDescription) || empty($payloadDescription)) {
                        $payloadDescription = data_get($sourceProduct->payload, 'text');
                    }

                    if (is_array($payloadDescription)) {
                        $payloadDescription = (string) ($payloadDescription[$locale]
                            ?? $payloadDescription['uk']
                            ?? $payloadDescription['ua']
                            ?? $payloadDescription['ru']
                            ?? $payloadDescription['en']
                            ?? '');
                    }

                    $payloadDescription = $compactDescription((string) $payloadDescription);
                    if ($payloadDescription !== '') {
                        return $payloadDescription;
                    }

                    $localDescription = '';
                    if ($localProduct) {
                        $localShortDescription = trim(strip_tags((string) ($localProduct->short_desc ?? '')));

                        if ($localShortDescription !== '') {
                            return $compactDescription($localShortDescription);
                        }

                        $localDescription = method_exists($localProduct, 'getTranslation')
                            ? (string) ($localProduct->getTranslation('description', $locale, false)
                                ?: $localProduct->getTranslation('description', 'uk', false)
                                ?: $localProduct->getTranslation('description', 'ru', false)
                                ?: $localProduct->getTranslation('description', 'en', false)
                                ?: '')
                            : (string) ($localProduct->description ?? '');
                    }

                    if ($localDescription !== '') {
                        return $compactDescription($localDescription);
                    }

                    return '';
                };

                /** @var \App\Models\Callcenter\SourceProduct $first */
                $first = $rows->first();
                $firstLocalProduct = $first->localProduct;
                $hasVariants = $rows->count() > 1;
                $source = $first->source;

                $image = $rows->map(function (\App\Models\Callcenter\SourceProduct $row) use ($normalizeImportedImage, $source) {
                    $localImage = $normalizeImportedImage($row->localProduct?->main_image_url, $source);
                    $parentImage = $normalizeImportedImage($row->localProduct?->parent?->main_image_url, $source);
                    $payloadImage = $normalizeImportedImage(is_string(data_get($row->payload, 'img')) ? (string) data_get($row->payload, 'img') : null, $source);

                    return $localImage ?: ($parentImage ?: $payloadImage);
                })->first(fn (?string $candidate): bool => is_string($candidate) && $candidate !== '');

                if (! $image) {
                    $image = $fallbackImportedImage($first);
                }

                if (! $hasVariants) {
                    $single = $rows->first();
                    $singleLocal = $single->localProduct;
                    $singleId = (int) ($single->local_product_id ?? 0);
                    $fallbackImageId = (string) ($single->external_parent_id ?: $single->external_id ?: '');

                    return [
                        'id' => $singleId,
                        'title' => (string) ($single->title ?: ($singleLocal?->title ?? 'Без названия')),
                        'description' => $resolveImportedDescription($singleLocal, $single),
                        'image' => $image,
                        'image_fallback_id' => $fallbackImageId,
                        'source_base_url' => (string) ($source?->base_url ?? ''),
                        'has_variants' => false,
                        'price' => (float) ($single->price ?? $singleLocal?->price ?? 0),
                        'old_price' => (float) ($singleLocal?->old_price ?? 0),
                        'discount_percent' => $resolveDiscountPercent($single->price ?? $singleLocal?->price ?? 0, $singleLocal?->old_price ?? 0),
                        'is_new' => (bool) ($singleLocal?->is_new ?? false),
                        'is_hit' => (bool) ($singleLocal?->is_hit ?? false),
                        'is_promo' => (bool) ($singleLocal?->is_promo ?? false),
                        'is_vegan' => (bool) ($singleLocal?->is_vegan ?? false),
                        'is_product_of_day' => (bool) ($singleLocal?->is_product_of_day ?? false),
                        'is_spicy' => (bool) ($singleLocal?->is_spicy ?? false),
                        'unit' => $single->size_label ?: \App\Filament\Resources\Callcenter\OrderResource\Concerns\HasMenuCatalogActions::resolveMenuUnitLabel($singleId),
                        'variants' => [],
                    ];
                }

                $variants = $rows
                    ->map(function (\App\Models\Callcenter\SourceProduct $row) use ($resolveDiscountPercent): array {
                        $localProductId = (int) ($row->local_product_id ?? 0);

                        return [
                            'id' => $localProductId,
                            'title' => (string) ($row->title ?: ($row->localProduct?->title ?? 'Вариант')),
                            'price' => (float) ($row->price ?? $row->localProduct?->price ?? 0),
                            'old_price' => (float) ($row->localProduct?->old_price ?? 0),
                            'discount_percent' => $resolveDiscountPercent($row->price ?? $row->localProduct?->price ?? 0, $row->localProduct?->old_price ?? 0),
                            'is_new' => (bool) ($row->localProduct?->is_new ?? false),
                            'is_hit' => (bool) ($row->localProduct?->is_hit ?? false),
                            'is_promo' => (bool) ($row->localProduct?->is_promo ?? false),
                            'is_vegan' => (bool) ($row->localProduct?->is_vegan ?? false),
                            'is_product_of_day' => (bool) ($row->localProduct?->is_product_of_day ?? false),
                            'is_spicy' => (bool) ($row->localProduct?->is_spicy ?? false),
                            'unit' => (string) ($row->size_label ?: \App\Filament\Resources\Callcenter\OrderResource\Concerns\HasMenuCatalogActions::resolveMenuUnitLabel($localProductId)),
                        ];
                    })
                    ->filter(fn (array $variant): bool => (int) ($variant['id'] ?? 0) > 0)
                    ->values();

                return [
                    'id' => (int) ($first->local_product_id ?? 0),
                    'title' => (string) ($first->title ?: ($firstLocalProduct?->title ?? 'Без названия')),
                    'description' => $resolveImportedDescription($firstLocalProduct, $first),
                    'image' => $image,
                    'image_fallback_id' => (string) ($first->external_parent_id ?: $first->external_id ?: ''),
                    'source_base_url' => (string) ($source?->base_url ?? ''),
                    'has_variants' => $variants->isNotEmpty(),
                    'price' => 0,
                    'old_price' => 0,
                    'discount_percent' => null,
                    'is_new' => (bool) ($firstLocalProduct?->is_new ?? false),
                    'is_hit' => (bool) ($firstLocalProduct?->is_hit ?? false),
                    'is_promo' => (bool) ($firstLocalProduct?->is_promo ?? false),
                    'is_vegan' => (bool) ($firstLocalProduct?->is_vegan ?? false),
                    'is_product_of_day' => (bool) ($firstLocalProduct?->is_product_of_day ?? false),
                    'is_spicy' => (bool) ($firstLocalProduct?->is_spicy ?? false),
                    'unit' => '',
                    'variants' => $variants->all(),
                ];
            })
            ->pipe(fn ($items) => $sortPayloadProducts($items, $sort));

        $totalProducts = $productsPayload->count();
        $productsPayload = $productsPayload
            ->slice(($page - 1) * $perPage, $perPage)
            ->values();
        $hasMore = $totalProducts > ($page * $perPage);
    } else {
        $specialCategoryId = match ($categoryIdRaw) {
            'special:promo' => 'special:promo',
            'special:new' => 'special:new',
            'special:hit' => 'special:hit',
            default => null,
        };

        $baseLocalProductsQuery = static function () use ($applySourceFilter) {
            $query = \App\Models\Shop\Product::query()
                ->whereNull('parent_id')
                ->where('in_stock', 1)
                ->where(function ($w): void {
                    $w->whereNull('is_imported')
                        ->orWhere('is_imported', false);
                });

            $applySourceFilter($query);

            return $query;
        };

        $specialCategories = collect([
            [
                'id' => 'special:promo',
                'name' => function_exists('st') ? st('menu.promotions', 'Акційні пропозиції') : 'Акційні пропозиції',
                'apply' => static function ($query): void {
                    $query->where('is_promo', 1);
                },
            ],
            [
                'id' => 'special:new',
                'name' => function_exists('st') ? st('menu.news', 'Новинки') : 'Новинки',
                'apply' => static function ($query): void {
                    $query->where('is_new', 1);
                },
            ],
            [
                'id' => 'special:hit',
                'name' => function_exists('st') ? st('menu.hits', 'Хіти') : 'Хіти',
                'apply' => static function ($query): void {
                    $query->where('is_hit', 1);
                },
            ],
        ])->filter(function (array $special) use ($baseLocalProductsQuery): bool {
            $query = $baseLocalProductsQuery();
            ($special['apply'])($query);

            return $query->exists();
        })->map(fn (array $special): array => [
            'id' => $special['id'],
            'name' => $special['name'],
        ])->values();

        $locale = app()->getLocale();

        $categories = $specialCategories->concat(\App\Models\Shop\ProductCategory::query()
            ->where('slug', 'not like', 'src-%-import')
            ->whereHas('products', function ($q) use ($applySourceFilter): void {
                $q->where('in_stock', 1);
                $q->where(function ($w): void {
                    $w->whereNull('is_imported')
                        ->orWhere('is_imported', false);
                });
                $applySourceFilter($q);
            })
            ->get(['id', 'title'])
            ->map(fn (\App\Models\Shop\ProductCategory $cat) => [
                'id' => (int) $cat->id,
                'name' => (string) (
                    $cat->getTranslation('title', $locale, false)
                    ?: $cat->getTranslation('title', 'uk', false)
                    ?: $cat->getTranslation('title', 'ru', false)
                    ?: (is_string($cat->title) ? $cat->title : json_encode($cat->title, JSON_UNESCAPED_UNICODE))
                ),
            ])
            ->values())->values();

        $productsQuery = \App\Models\Shop\Product::query()
            ->select(['id', 'title', 'short_name', 'short_desc', 'description', 'price', 'old_price', 'main_image', 'parent_id', 'category_id', 'in_stock', 'is_home', 'is_promo', 'is_new', 'is_hit', 'is_vegan', 'is_product_of_day', 'is_spicy', 'sort', 'created_at'])
            ->whereNull('parent_id')
            ->where('in_stock', 1)
            ->where(function ($w): void {
                $w->whereNull('is_imported')
                    ->orWhere('is_imported', false);
            });

        $applySourceFilter($productsQuery);

        $productsQuery = $productsQuery
            ->when($specialCategoryId !== null, function ($q) use ($specialCategoryId): void {
                match ($specialCategoryId) {
                    'special:promo' => $q->where('is_promo', 1),
                    'special:new' => $q->where('is_new', 1),
                    'special:hit' => $q->where('is_hit', 1),
                    default => null,
                };
            })
            ->when($specialCategoryId === null && $localCategoryId > 0, fn ($q) => $q->where('category_id', $localCategoryId))
            ->when($search !== '', function ($q) use ($needle, $locales): void {
                $q->where(function ($w) use ($needle, $locales): void {
                    foreach ($locales as $loc) {
                        $w->orWhereRaw(
                            "LOWER(CASE WHEN JSON_VALID(`title`) THEN JSON_UNQUOTE(JSON_EXTRACT(`title`, '$.\"{$loc}\"')) ELSE `title` END) LIKE ?",
                            [$needle]
                        );

                        $w->orWhereRaw(
                            "LOWER(CASE WHEN JSON_VALID(`short_name`) THEN JSON_UNQUOTE(JSON_EXTRACT(`short_name`, '$.\"{$loc}\"')) ELSE `short_name` END) LIKE ?",
                            [$needle]
                        );
                    }

                    $w->orWhereRaw('LOWER(`title`) LIKE ?', [$needle])
                        ->orWhereRaw('LOWER(`short_name`) LIKE ?', [$needle]);

                    $w->orWhereRaw('LOWER(`slug`) LIKE ?', [$needle])
                        ->orWhereRaw('LOWER(`sku`) LIKE ?', [$needle]);

                    $w->orWhereExists(function ($query) use ($needle) {
                        $query->select(\Illuminate\Support\Facades\DB::raw(1))
                            ->from('bs_product_characteristic_value')
                            ->whereColumn('bs_product_characteristic_value.product_id', 'bs_products.id')
                            ->whereRaw('LOWER(bs_product_characteristic_value.value_text) LIKE ?', [$needle]);
                    });

                    $w->orWhereExists(function ($query) use ($needle) {
                        $query->select(\Illuminate\Support\Facades\DB::raw(1))
                            ->from('bs_products AS child_products')
                            ->join('bs_product_characteristic_value', 'child_products.id', '=', 'bs_product_characteristic_value.product_id')
                            ->whereColumn('child_products.parent_id', 'bs_products.id')
                            ->whereRaw('LOWER(bs_product_characteristic_value.value_text) LIKE ?', [$needle]);
                    });

                    foreach ($locales as $loc) {
                        $w->orWhereExists(function ($query) use ($needle, $loc) {
                            $query->select(\Illuminate\Support\Facades\DB::raw(1))
                                ->from('bs_product_characteristic_value')
                                ->join('bs_characteristic_values', 'bs_product_characteristic_value.characteristic_value_id', '=', 'bs_characteristic_values.id')
                                ->whereColumn('bs_product_characteristic_value.product_id', 'bs_products.id')
                                ->whereRaw(
                                    "LOWER(CASE WHEN JSON_VALID(bs_characteristic_values.value) THEN JSON_UNQUOTE(JSON_EXTRACT(bs_characteristic_values.value, '$.\"{$loc}\"')) ELSE bs_characteristic_values.value END) LIKE ?",
                                    [$needle]
                                );
                        });
                    }

                    foreach ($locales as $loc) {
                        $w->orWhereExists(function ($query) use ($needle, $loc) {
                            $query->select(\Illuminate\Support\Facades\DB::raw(1))
                                ->from('bs_products AS child_products')
                                ->join('bs_product_characteristic_value', 'child_products.id', '=', 'bs_product_characteristic_value.product_id')
                                ->join('bs_characteristic_values', 'bs_product_characteristic_value.characteristic_value_id', '=', 'bs_characteristic_values.id')
                                ->whereColumn('child_products.parent_id', 'bs_products.id')
                                ->whereRaw(
                                    "LOWER(CASE WHEN JSON_VALID(bs_characteristic_values.value) THEN JSON_UNQUOTE(JSON_EXTRACT(bs_characteristic_values.value, '$.\"{$loc}\"')) ELSE bs_characteristic_values.value END) LIKE ?",
                                    [$needle]
                                );
                        });
                    }

                    $w->orWhereExists(function ($query) use ($needle, $locales) {
                        $query->select(\Illuminate\Support\Facades\DB::raw(1))
                            ->from('bs_products AS child_products')
                            ->whereColumn('child_products.parent_id', 'bs_products.id')
                            ->where(function ($cw) use ($needle, $locales): void {
                                foreach ($locales as $loc) {
                                    $cw->orWhereRaw(
                                        "LOWER(CASE WHEN JSON_VALID(child_products.title) THEN JSON_UNQUOTE(JSON_EXTRACT(child_products.title, '$.\"{$loc}\"')) ELSE child_products.title END) LIKE ?",
                                        [$needle]
                                    );

                                    $cw->orWhereRaw(
                                        "LOWER(CASE WHEN JSON_VALID(child_products.short_name) THEN JSON_UNQUOTE(JSON_EXTRACT(child_products.short_name, '$.\"{$loc}\"')) ELSE child_products.short_name END) LIKE ?",
                                        [$needle]
                                    );
                                }

                                $cw->orWhereRaw('LOWER(child_products.title) LIKE ?', [$needle])
                                    ->orWhereRaw('LOWER(child_products.short_name) LIKE ?', [$needle]);

                                $cw->orWhereRaw('LOWER(child_products.slug) LIKE ?', [$needle])
                                    ->orWhereRaw('LOWER(child_products.sku) LIKE ?', [$needle]);
                            });
                    });
                });
            })
            ->with(['children' => function ($q) {
                $q->select(['id', 'title', 'short_desc', 'description', 'price', 'old_price', 'main_image', 'parent_id', 'in_stock', 'is_promo', 'is_new', 'is_hit', 'is_vegan', 'is_product_of_day', 'is_spicy'])
                    ->where('in_stock', 1)
                    ->orderBy('sort')
                    ->orderBy('id');
            }]);

        $applyMenuSort($productsQuery, $sort);

        $products = $productsQuery
            ->offset(($page - 1) * $perPage)
            ->limit($perPage + 1)
            ->get();

        $hasMore = $products->count() > $perPage;
        if ($hasMore) {
            $products = $products->take($perPage)->values();
        }

        $productsPayload = $products->map(function (\App\Models\Shop\Product $product) use ($compactDescription, $resolveDiscountPercent) {
            $locale = app()->getLocale();
            $variants = $product->children ?? collect();
            $hasVariants = $variants->isNotEmpty();

            $productShortDescription = trim(strip_tags((string) ($product->short_desc ?? '')));

            $productDescription = method_exists($product, 'getTranslation')
                ? (string) ($product->getTranslation('description', $locale, false)
                    ?: $product->getTranslation('description', 'uk', false)
                    ?: $product->getTranslation('description', 'ru', false)
                    ?: $product->getTranslation('description', 'en', false)
                    ?: '')
                : (string) ($product->description ?? '');

            $productDescription = $productShortDescription !== ''
                ? $productShortDescription
                : $productDescription;

            $variantsPayload = [];

            if ($hasVariants) {
                $variantsPayload = collect([
                    [
                        'id' => (int) $product->id,
                        'title' => (string) ($product->display_name ?? $product->title ?? ''),
                        'description' => $compactDescription($productDescription),
                        'price' => (float) ($product->price ?? 0),
                        'old_price' => (float) ($product->old_price ?? 0),
                        'discount_percent' => $resolveDiscountPercent($product->price ?? 0, $product->old_price ?? 0),
                        'is_promo' => (bool) ($product->is_promo ?? false),
                        'is_new' => (bool) ($product->is_new ?? false),
                        'is_hit' => (bool) ($product->is_hit ?? false),
                        'is_vegan' => (bool) ($product->is_vegan ?? false),
                        'is_product_of_day' => (bool) ($product->is_product_of_day ?? false),
                        'is_spicy' => (bool) ($product->is_spicy ?? false),
                        'unit' => \App\Filament\Resources\Callcenter\OrderResource\Concerns\HasMenuCatalogActions::resolveMenuUnitLabel((int) $product->id),
                    ],
                ])->merge($variants->map(function (\App\Models\Shop\Product $variant) use ($compactDescription, $resolveDiscountPercent) {
                    $locale = app()->getLocale();
                    $variantShortDescription = trim(strip_tags((string) ($variant->short_desc ?? '')));
                    $variantDescription = method_exists($variant, 'getTranslation')
                        ? (string) ($variant->getTranslation('description', $locale, false)
                            ?: $variant->getTranslation('description', 'uk', false)
                            ?: $variant->getTranslation('description', 'ru', false)
                            ?: $variant->getTranslation('description', 'en', false)
                            ?: '')
                        : (string) ($variant->description ?? '');

                    $variantDescription = $variantShortDescription !== ''
                        ? $variantShortDescription
                        : $variantDescription;

                    return [
                        'id' => (int) $variant->id,
                        'title' => (string) ($variant->display_name ?? $variant->title ?? ''),
                        'description' => $compactDescription($variantDescription),
                        'price' => (float) ($variant->price ?? 0),
                        'old_price' => (float) ($variant->old_price ?? 0),
                        'discount_percent' => $resolveDiscountPercent($variant->price ?? 0, $variant->old_price ?? 0),
                        'is_promo' => (bool) ($variant->is_promo ?? false),
                        'is_new' => (bool) ($variant->is_new ?? false),
                        'is_hit' => (bool) ($variant->is_hit ?? false),
                        'is_vegan' => (bool) ($variant->is_vegan ?? false),
                        'is_product_of_day' => (bool) ($variant->is_product_of_day ?? false),
                        'is_spicy' => (bool) ($variant->is_spicy ?? false),
                        'unit' => \App\Filament\Resources\Callcenter\OrderResource\Concerns\HasMenuCatalogActions::resolveMenuUnitLabel((int) $variant->id),
                    ];
                }))
                    ->unique('id')
                    ->values()
                    ->all();
            }

            return [
                'id' => (int) $product->id,
                'title' => (string) ($product->display_name ?? $product->title ?? ''),
                'description' => $compactDescription($productDescription),
                'image' => $product->main_image_url,
                'price' => (float) ($product->price ?? 0),
                'old_price' => (float) ($product->old_price ?? 0),
                'discount_percent' => $resolveDiscountPercent($product->price ?? 0, $product->old_price ?? 0),
                'is_new' => (bool) ($product->is_new ?? false),
                'is_hit' => (bool) ($product->is_hit ?? false),
                'is_promo' => (bool) ($product->is_promo ?? false),
                'is_vegan' => (bool) ($product->is_vegan ?? false),
                'is_product_of_day' => (bool) ($product->is_product_of_day ?? false),
                'is_spicy' => (bool) ($product->is_spicy ?? false),
                'has_variants' => $hasVariants,
                'unit' => \App\Filament\Resources\Callcenter\OrderResource\Concerns\HasMenuCatalogActions::resolveMenuUnitLabel((int) $product->id),
                'variants' => $variantsPayload,
            ];
        })->values();
    }

    return response()->json([
        'sources' => $sources,
        'selected_source_id' => $selectedSourceId,
        'categories' => $categories,
        'products' => $productsPayload,
        'page' => $page,
        'per_page' => $perPage,
        'has_more' => $hasMore,
    ]);
})
    ->name('admin.callcenter.menu-catalog')
    ->middleware(['web', 'auth:admin']);

Route::get('/admin/callcenter/synced-catalog/data', function (\Illuminate\Http\Request $request) {
    $sourceId = (int) $request->query('source_id', 0);
    $directory = (string) $request->query('directory', 'catalog');
    $search = trim((string) $request->query('q', ''));
    $categoryId = trim((string) $request->query('category_id', ''));

    $sources = \App\Models\Callcenter\Source::query()
        ->where('is_active', true)
        ->where('sync_enabled', true)
        ->orderBy('name')
        ->get(['id', 'name', 'last_catalog_synced_at'])
        ->map(fn (\App\Models\Callcenter\Source $source) => [
            'id' => (int) $source->id,
            'name' => (string) $source->name,
            'last_catalog_synced_at' => $source->last_catalog_synced_at?->toDateTimeString(),
            'last_catalog_synced_at_label' => $source->last_catalog_synced_at
                ? $source->last_catalog_synced_at->format('d.m.Y H:i:s')
                : '—',
        ])
        ->values();

    if ($sources->isEmpty()) {
        return response()->json([
            'sources' => [],
            'selected_source_id' => 0,
            'categories' => [],
            'products' => [],
            'clients' => [],
        ]);
    }

    if (! $sources->contains(fn (array $source): bool => (int) $source['id'] === $sourceId)) {
        $sourceId = (int) $sources->first()['id'];
    }

    if ($directory === 'clients') {
        $clients = \App\Models\Callcenter\SourceClient::query()
            ->where('source_id', $sourceId)
            ->when($search !== '', function ($query) use ($search): void {
                $needle = '%' . $search . '%';
                $query->where(function ($w) use ($needle): void {
                    $w->where('name', 'like', $needle)
                        ->orWhere('external_phone', 'like', $needle)
                        ->orWhere('email', 'like', $needle);
                });
            })
            ->orderByDesc('id')
            ->limit(300)
            ->get(['id', 'name', 'external_phone', 'email', 'local_client_id'])
            ->map(fn (\App\Models\Callcenter\SourceClient $client) => [
                'id' => (int) $client->id,
                'name' => (string) ($client->name ?? ''),
                'phone' => (string) ($client->external_phone ?? ''),
                'email' => (string) ($client->email ?? ''),
                'local_client_id' => $client->local_client_id ? (int) $client->local_client_id : null,
            ])
            ->values();

        return response()->json([
            'sources' => $sources,
            'selected_source_id' => $sourceId,
            'clients' => $clients,
            'categories' => [],
            'products' => [],
        ]);
    }

    $categories = \App\Models\Callcenter\SourceCategory::query()
        ->where('source_id', $sourceId)
        ->whereIn('external_id', function ($query) use ($sourceId): void {
            $query->select('external_category_id')
                ->from('bs_cc_source_products')
                ->where('source_id', $sourceId)
                ->whereNotNull('external_category_id')
                ->groupBy('external_category_id');
        })
        ->orderBy('id')
        ->get(['external_id', 'title'])
        ->map(function (\App\Models\Callcenter\SourceCategory $category): array {
            $title = $category->title;
            $name = is_array($title)
                ? (string) ($title['uk'] ?? $title['ru'] ?? $title['en'] ?? $category->external_id)
                : (string) ($title ?: $category->external_id);

            return [
                'id' => (string) $category->external_id,
                'name' => $name,
            ];
        })
        ->values();

    $productsRows = \App\Models\Callcenter\SourceProduct::query()
        ->with('localProduct:id,title,short_name,main_image,price')
        ->where('source_id', $sourceId)
        ->when($categoryId !== '', fn ($q) => $q->where('external_category_id', $categoryId))
        ->when($search !== '', function ($q) use ($search): void {
            $needle = '%' . mb_strtolower($search) . '%';
            $q->whereRaw('LOWER(title) LIKE ?', [$needle]);
        })
        ->orderBy('external_parent_id')
        ->orderBy('id')
        ->get();

    $products = $productsRows
        ->groupBy(fn (\App\Models\Callcenter\SourceProduct $row) => $row->external_parent_id ?: $row->external_id)
        ->map(function ($rows) {
            /** @var \App\Models\Callcenter\SourceProduct $first */
            $first = $rows->first();
            $hasVariants = $rows->count() > 1;

            $image = $first->localProduct?->main_image_url
                ?: (is_string(data_get($first->payload, 'img')) ? (string) data_get($first->payload, 'img') : null);

            if (! $hasVariants) {
                $single = $rows->first();
                $singlePrice = (float) ($single->price ?? $single->localProduct?->price ?? 0);

                return [
                    'id' => (int) $single->id,
                    'external_parent_id' => (string) ($single->external_parent_id ?: $single->external_id),
                    'title' => (string) ($single->title ?: 'Без названия'),
                    'image' => $image,
                    'has_variants' => false,
                    'price' => $singlePrice,
                    'unit' => (string) ($single->size_label ?: ''),
                    'variants' => [],
                ];
            }

            $variants = $rows->map(function (\App\Models\Callcenter\SourceProduct $row): array {
                return [
                    'id' => (int) $row->id,
                    'title' => (string) ($row->title ?: 'Вариант'),
                    'price' => (float) ($row->price ?? $row->localProduct?->price ?? 0),
                    'unit' => (string) ($row->size_label ?: ''),
                ];
            })->values();

            return [
                'id' => (int) $first->id,
                'external_parent_id' => (string) ($first->external_parent_id ?: $first->external_id),
                'title' => (string) ($first->title ?: 'Без названия'),
                'image' => $image,
                'has_variants' => true,
                'price' => 0,
                'unit' => '',
                'variants' => $variants,
            ];
        })
        ->values();

    return response()->json([
        'sources' => $sources,
        'selected_source_id' => $sourceId,
        'categories' => $categories,
        'products' => $products,
        'clients' => [],
    ]);
})
    ->name('admin.callcenter.synced-catalog.data')
    ->middleware(['web', 'auth:admin']);

Route::get('/admin/clear-cache', function () {
    $catalogCacheVersion = app(\App\Services\CatalogCacheService::class)->bump();
    $cleared = [];

    // Получаем активные языки динамически
    $locales = [];
    try {
        if (\Illuminate\Support\Facades\Schema::hasTable('bs_languages')) {
            $locales = \App\Models\Language::where('active', true)
                ->pluck('code')
                ->map(fn($c) => strtolower($c))
                ->toArray();
        }
    } catch (\Exception $e) {
        // Если таблицы нет, используем дефолтные
        $locales = ['uk', 'en', 'ru'];
    }

    // Если языков нет, используем дефолтные
    if (empty($locales)) {
        $locales = ['uk', 'en', 'ru'];
    }

    // Очищаем кеш категорий для всех языков
    foreach ($locales as $locale) {
        $key1 = "product_categories_{$locale}";
        $key2 = "product_categories_all_{$locale}";
        if (cache()->forget($key1)) $cleared[] = $key1;
        if (cache()->forget($key2)) $cleared[] = $key2;
    }

    // Очищаем кеш языков
    if (cache()->forget('active_languages_map')) {
        $cleared[] = 'active_languages_map';
    }

    // Очищаем весь кеш приложения (опционально, можно закомментировать если не нужно)
    // cache()->flush();

    $count = count($cleared);

    if ($count > 0) {
        session()->flash('notification', [
            'type' => 'success',
            'title' => 'Кеш очищен',
            'body' => "Успешно очищено {$count} ключей кеша. Версия кеша каталога: {$catalogCacheVersion}",
        ]);
    } else {
        session()->flash('notification', [
            'type' => 'info',
            'title' => 'Кеш пуст',
            'body' => "Нет кешированных данных для очистки. Версия кеша каталога: {$catalogCacheVersion}",
        ]);
    }

    return back();
})
    ->name('admin.clear-cache')
    ->middleware(['web', 'auth']);
// API для определения зоны доставки (используется в админке)
Route::post('/api/delivery-zone/resolve', [\App\Http\Controllers\Admin\DeliveryZoneController::class, 'resolveZone'])
    ->name('api.delivery-zone.resolve')
    ->middleware(['web', 'auth:admin']);

Route::get('/admin/site-template-overrides/{record}/preview', function (\App\Models\SiteTemplateOverride $record, \App\Services\SiteTemplates\TemplatePreviewFactory $previewFactory) {
    $user = auth('admin')->user();

    abort_unless($user && method_exists($user, 'hasRole') && $user->hasRole(config('shield.super_admin.name', 'super_admin')), 403);

    $body = (string) ($record->override_body ?: $record->original_snapshot ?: '');

    try {
        $html = \Illuminate\Support\Facades\Blade::render($body, $previewFactory->make((string) $record->key), deleteCachedView: true);
    } catch (\Throwable $e) {
        $html = '<div style="padding:12px;border-radius:10px;background:#fef2f2;color:#991b1b;border:1px solid #fecaca;">'
            . '<div style="font-weight:700;margin-bottom:6px;">Не удалось построить предпросмотр</div>'
            . '<div>' . e($e->getMessage()) . '</div>'
            . '</div>';
    }

    return response()->view('filament.site-templates.preview-page-standalone', [
        'title' => 'Предпросмотр: ' . $record->title,
        'html' => $html,
    ]);
})
    ->name('admin.site-template-overrides.preview')
    ->middleware(['web', 'auth:admin']);
