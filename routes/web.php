<?php

use App\Http\Controllers\Auth\ClientAuthController;
use App\Http\Controllers\Auth\PasswordResetSmsController;
use App\Http\Controllers\Auth\PhoneRegisterController;
use App\Http\Controllers\Auth\ProfileController;
use App\Http\Controllers\Front\CartController;
use App\Http\Controllers\Front\BlogController;
use App\Http\Controllers\Front\CatalogController;
use App\Http\Controllers\Front\CheckoutController;
use App\Http\Controllers\Front\FavoriteController;
use App\Http\Controllers\Front\PageController;
use App\Http\Controllers\Front\ProductReviewController;
use App\Http\Controllers\Front\ReviewController;
use App\Http\Controllers\Front\SearchController;
use App\Models\Pages;
use App\Models\BlogCategory;
use App\Models\Shop\Product;
use App\Models\Shop\ProductCategory;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Front\HomeController;
use App\Http\Controllers\Front\ProductController;
use App\Http\Controllers\Front\MenuController;
use Illuminate\Support\Str;
use App\Http\Controllers\Front\LiqPayController;
use App\Http\Controllers\Integrations\BinotelWebhookController;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;


Route::get('/', function () {
    return view('welcome');
});

// Роут для получения актуального CSRF токена (для периодического обновления)
Route::get('/csrf-token', function () {
    return response()->json([
        'token' => csrf_token()
    ]);
})->name('csrf.token');

Route::get('/lang/{locale}', function (string $locale) {
    $allowed = ['uk','ru','en'];             // список поддерживаемых
    abort_unless(in_array($locale, $allowed, true), 404);

    session(['locale' => $locale]);          // запоминаем в сессии
    app()->setLocale($locale);               // чтобы применилось сразу на редиректе
   //     dd($locale);
    // опц.: доп. сохранение в cookie, чтобы помнить язык между сессиями
    return back(status: 303)->cookie('locale', $locale, 60 * 24 * 365);
})->name('lang.switch');


Route::get('/debug/session', function () {
    session(['_dbg' => now()->toDateTimeString()]);
    return [
        'session_id'     => session()->getId(),
        'has_cookie'     => request()->hasCookie(config('session.cookie')),
        'dbg_value'      => session('_dbg'),
        'guard_user_id'  => auth()->id(),
        'cookie_name'    => config('session.cookie'),
        'cookie_domain'  => config('session.domain'),
        'cookie_secure'  => config('session.secure'),
    ];
})->middleware('web');


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
                'localProduct:id,title,short_desc,description,main_image,parent_id,price,in_stock',
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
            ->orderBy('external_parent_id')
            ->orderBy('id')
            ->get();

        $productsPayload = $productsRows
            ->groupBy(fn (\App\Models\Callcenter\SourceProduct $row) => $row->external_parent_id ?: $row->external_id)
            ->map(function ($rows) use ($compactDescription, $normalizeImportedImage, $fallbackImportedImage) {
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
                        'unit' => $single->size_label ?: \App\Filament\Resources\Callcenter\OrderResource\Concerns\HasMenuCatalogActions::resolveMenuUnitLabel($singleId),
                        'variants' => [],
                    ];
                }

                $variants = $rows
                    ->map(function (\App\Models\Callcenter\SourceProduct $row): array {
                        $localProductId = (int) ($row->local_product_id ?? 0);

                        return [
                            'id' => $localProductId,
                            'title' => (string) ($row->title ?: ($row->localProduct?->title ?? 'Вариант')),
                            'price' => (float) ($row->price ?? $row->localProduct?->price ?? 0),
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
                    'unit' => '',
                    'variants' => $variants->all(),
                ];
            })
            ->values();
    } else {
        $categories = \App\Models\Shop\ProductCategory::query()
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
                'name' => (string) $cat->name,
            ])
            ->values();

        $productsQuery = \App\Models\Shop\Product::query()
            ->select(['id', 'title', 'short_name', 'short_desc', 'description', 'price', 'main_image', 'parent_id', 'category_id', 'in_stock'])
            ->whereNull('parent_id')
            ->where('in_stock', 1)
            ->where(function ($w): void {
                $w->whereNull('is_imported')
                    ->orWhere('is_imported', false);
            });

        $applySourceFilter($productsQuery);

        $products = $productsQuery
            ->when($localCategoryId > 0, fn ($q) => $q->where('category_id', $localCategoryId))
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
                        ->orWhereRaw('LOWER(`code2`) LIKE ?', [$needle])
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
                                    ->orWhereRaw('LOWER(child_products.code2) LIKE ?', [$needle])
                                    ->orWhereRaw('LOWER(child_products.sku) LIKE ?', [$needle]);
                            });
                    });
                });
            })
            ->with(['children' => function ($q) {
                $q->select(['id', 'title', 'short_desc', 'description', 'price', 'main_image', 'parent_id', 'in_stock'])
                    ->where('in_stock', 1)
                    ->orderBy('sort')
                    ->orderBy('id');
            }])
            ->orderBy('sort')
            ->orderBy('id')
            ->limit(120)
            ->get();

        $productsPayload = $products->map(function (\App\Models\Shop\Product $product) use ($compactDescription) {
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
                        'unit' => \App\Filament\Resources\Callcenter\OrderResource\Concerns\HasMenuCatalogActions::resolveMenuUnitLabel((int) $product->id),
                    ],
                ])->merge($variants->map(function (\App\Models\Shop\Product $variant) use ($compactDescription) {
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
            'body' => "Успешно очищено {$count} ключей кеша",
        ]);
    } else {
        session()->flash('notification', [
            'type' => 'info',
            'title' => 'Кеш пуст',
            'body' => 'Нет кешированных данных для очистки',
        ]);
    }

    return back();
})
    ->name('admin.clear-cache')
    ->middleware(['web', 'auth']);


Route::get('/search', [SearchController::class, 'index'])->name('search');                 // полная страница результатов
Route::get('/search/suggest', [SearchController::class, 'suggest'])->name('search.suggest'); // ajax-подсказки


Route::get('/', [HomeController::class, 'index'])->name('home');
// Страницы
Route::get('/cart', [CartController::class, 'page'])->name('cart.page');
Route::get('/checkout', [CheckoutController::class, 'index'])->name('checkout');
Route::post('/checkout', [CheckoutController::class, 'submit'])->name('checkout.submit');
Route::post('/checkout/save-form-data', [CheckoutController::class, 'saveFormData'])->name('checkout.save-form-data');
Route::post('/checkout/apply-coupon', [CheckoutController::class, 'applyCoupon'])->name('checkout.apply-coupon');
Route::post('/checkout/promo', [CheckoutController::class, 'updatePromo'])
    ->name('checkout.promo');
Route::post('/checkout/check-promo-conditions', [CheckoutController::class, 'checkPromoConditionsAjax'])
    ->name('checkout.check-promo-conditions');
Route::get('/checkout/{order}/pay/liqpay', [CheckoutController::class, 'payLiqPay'])
    ->name('checkout.pay.liqpay');
Route::post('/checkout/{order}/pay/liqpay/email', [CheckoutController::class, 'saveLiqPayEmail'])
    ->name('checkout.pay.liqpay.email');
Route::get('/filter', [CatalogController::class, 'filter'])
    ->name('catalog.filter');
Route::post('/liqpay/callback', [LiqPayController::class, 'callback'])
    ->name('liqpay.callback')
    ->withoutMiddleware([VerifyCsrfToken::class]);

Route::post('/integrations/binotel/call-settings', [BinotelWebhookController::class, 'callSettings'])
    ->name('integrations.binotel.call-settings')
    ->withoutMiddleware([VerifyCsrfToken::class]);

Route::post('/integrations/binotel/call-completed', [BinotelWebhookController::class, 'callCompleted'])
    ->name('integrations.binotel.call-completed')
    ->withoutMiddleware([VerifyCsrfToken::class]);
Route::get('/test/liqpay-status/{order}', function ($orderId) {
    $publicKey  = env('LIQPAY_PUBLIC_KEY');
    $privateKey = env('LIQPAY_PRIVATE_KEY');

    $liqpay = new LiqPay($publicKey, $privateKey);

    $res = $liqpay->api("request", [
        'action'    => 'status',
        'version'   => 3,
        'order_id'  => $orderId,
    ]);

    dd($res);
});
// страница “Спасибо”
Route::get('/checkout/success/{order}', [CheckoutController::class, 'success'])
    ->name('checkout.success');
Route::post('/checkout/success/{order}/send-email', [CheckoutController::class, 'sendOrderToEmail'])
    ->name('checkout.success.send-email');

// API для определения зоны доставки (используется в админке)
Route::post('/api/delivery-zone/resolve', [\App\Http\Controllers\Admin\DeliveryZoneController::class, 'resolveZone'])
    ->name('api.delivery-zone.resolve')
    ->middleware(['web', 'auth:admin']);
Route::post('/cart/add',    [CartController::class, 'add'])->name('cart.add');
Route::post('/cart/remove', [CartController::class, 'remove'])->name('cart.remove');
Route::post('/cart/clear',  [CartController::class, 'clear'])->name('cart.clear');
Route::get('/cart/info',    [CartController::class, 'info'])->name('cart.info');
Route::get('/cart/sidebar', [CartController::class, 'sidebar'])->name('cart.sidebar');

Route::post('/favorite/{product}', [FavoriteController::class, 'toggle'])
    ->name('favorite.toggle'); // без auth!

// страницу избранного можно оставить публичной…
Route::get('/favorites', [FavoriteController::class, 'index'])
    ->name('favorites.index');
Route::get('/favorites/info', [FavoriteController::class, 'info'])
    ->name('favorites.info');
Route::middleware(['web', 'auth'])->group(function () {
    Route::get('/profile', function () {
        return view('pages.profile.index', [
            'user' => auth()->user(),   // ← передаём в Blade
        ]);
    })->name('profile.index');

    Route::put('/profile', [ProfileController::class, 'update'])
        ->name('profile.update');

    // Адреса доставки
    Route::resource('profile/addresses', \App\Http\Controllers\Front\ClientAddressController::class)
        ->parameters(['addresses' => 'address'])
        ->names([
            'index' => 'profile.addresses.index',
            'create' => 'profile.addresses.create',
            'store' => 'profile.addresses.store',
            'edit' => 'profile.addresses.edit',
            'update' => 'profile.addresses.update',
            'destroy' => 'profile.addresses.destroy',
        ]);

    // Обновление координат сохранённого адреса (используется из checkout.js)
    Route::post('profile/addresses/{address}/coords', [\App\Http\Controllers\Front\ClientAddressController::class, 'updateCoords'])
        ->name('profile.addresses.update-coords');

    // Бонусы
    Route::get('/profile/bonus', function () {
        return view('pages.profile.bonuses.index');
    })->name('profile.bonuses.index');

    // История заказов
    Route::get('/profile/orders', function () {
        return view('pages.profile.orders.index');
    })->name('profile.orders.index');

    Route::get('/profile/orders/{order}', function ($orderId) {
        $user = auth()->user();

        // Проверяем, что пользователь авторизован
        if (!$user) {
            abort(403, 'User not authenticated');
        }

        // Находим заказ с явной проверкой принадлежности
        $order = \App\Models\Shop\Order::where('id', $orderId)
            ->where('clients_id', $user->id)
            ->first();

        // Если заказ не найден или не принадлежит пользователю
        if (!$order) {
            abort(403, 'Order not found or access denied');
        }

        return view('pages.profile.orders.show', compact('order'));
    })->name('profile.orders.show');

    // Повторить заказ
    Route::post('/profile/orders/{order}/repeat', [\App\Http\Controllers\Front\OrderController::class, 'repeat'])
        ->name('profile.orders.repeat');
});

Route::middleware(['web'])->group(function () {
  //  Route::redirect('/favorites', '/', 302)->name('favorites.index');
    Route::redirect('/orders', '/profile/orders', 302)->name('orders.index');
    Route::redirect('/orders/history', '/profile/orders', 302)->name('orders.history');
    Route::redirect('/bonuses', '/profile/bonus', 302)->name('bonuses.index');
  //  Route::redirect('/profile', '/', 302)->name('profile.show');
    Route::redirect('/addresses', '/profile/addresses', 302)->name('addresses.index');
    // ВАЖНО: правильные имена для аутентификации
    Route::redirect('/login', '/', 302)->name('login');   // если страницы логина пока нет
    // либо так: Route::view('/login', 'pages.stub')->name('login')->defaults('title','Увійти');

    Route::middleware('guest')->group(function () {
        Route::get('/auth', [ClientAuthController::class,'show'])->name('auth.show');

        Route::get('/auth/redirect/{provider}', [ClientAuthController::class,'redirect'])
            ->whereIn('provider',['google','facebook','apple'])->name('auth.redirect');

        Route::get('/auth/callback/{provider}', [ClientAuthController::class,'callback'])
            ->whereIn('provider',['google','facebook','apple'])->name('auth.callback');

       Route::post('/auth/register', [ClientAuthController::class,'register'])->name('auth.register');
        Route::post('/auth/login',    [ClientAuthController::class,'login'])->name('auth.login');

        // Авторизация только по телефону + SMS (без пароля)
        Route::post('/auth/phone-sms/send-code', [ClientAuthController::class,'loginPhoneSms'])
            ->name('auth.phone-sms.send-code')->middleware('throttle:5,1');
        Route::post('/auth/phone-sms/verify', [ClientAuthController::class,'verifyPhoneSms'])
            ->name('auth.phone-sms.verify')->middleware('throttle:10,1');

        // Сохранение URL checkout для редиректа после авторизации
        Route::post('/auth/save-checkout-url', [ClientAuthController::class,'saveCheckoutUrl'])
            ->name('auth.save-checkout-url');

     /*    Route::post('/auth/sms/send',   [ClientAuthController::class,'sendSms'])->name('auth.sms.send');
        Route::post('/auth/sms/verify', [ClientAuthController::class,'verifySms'])->name('auth.sms.verify');
    */
    });
    Route::post('/auth/password/send-code', [PasswordResetSmsController::class, 'sendCode'])
        ->name('auth.password.sendCode');

    Route::post('/auth/password/verify', [PasswordResetSmsController::class, 'verify'])
        ->name('auth.password.verify');




    Route::post('/auth/register/send-code', [PhoneRegisterController::class, 'sendCode'])
        ->name('auth.register.send-code')->middleware('throttle:5,1');

    Route::post('/auth/register/verify', [PhoneRegisterController::class, 'verify'])
        ->name('auth.register.verify')->middleware('throttle:10,1');


    Route::post('/auth/logout', [ClientAuthController::class,'logout'])
        ->middleware('auth')
        ->name('logout');

   /* Route::post('/logout', function () {
        auth('web')->logout();
        request()->session()->invalidate();
        request()->session()->regenerateToken();
        return redirect('/');
    })->name('logout');*/


});
Route::get('/feedbacks', [ReviewController::class, 'index'])->name('reviews.index');
Route::post('/feedbacks', [ReviewController::class, 'store'])
    ->name('reviews.store'); // сюда шлёт модалка
/*
Route::get('/blog', [BlogController::class, 'index'])
    ->name('blog.index'); // дефолтная категория: blog
Route::get('/blog/{slug}', [BlogController::class, 'show'])
   // ->where('slug', '[A-Za-z0-9\-_]+')
    ->where('slug', '[^/]+')
    ->name('blog.show');
*/

Route::get('/{categorySlug}/{itemSlug}', function (string $categorySlug, string $itemSlug) {

    // 1) если это категория БЛОГА — показываем статью
    $blogCategory = BlogCategory::query()->where('slug', $categorySlug)->first();
    if ($blogCategory) {
        // нормализуем слаг статьи (дефисы вместо юникод-тире и т.п.)
        $orig = urldecode($itemSlug);
        $norm = Str::of($orig)->trim()->replace(['—','–','-'], '-')->__toString();

        return app(BlogController::class)->showInCategory($categorySlug, $norm);
    }

    // 2) иначе — это КАТЕГОРИЯ ТОВАРОВ
    $category = ProductCategory::query()->where('slug', $categorySlug)->first();
    if (! $category) {
        return response()->view('404', [], 404);
    }

    $product = Product::query()
        ->where('slug', $itemSlug)
        ->where('category_id', $category->id)
        ->first();

    if ($product) {
        return app(ProductController::class)->show($categorySlug, $itemSlug);
    }

    return response()->view('404', [], 404);
})
    ->where([
        'categorySlug' => '[A-Za-z0-9\-_]+',
        'itemSlug'     => '[^/]+', // поддержит юникод-тире и пр.
    ])
    ->name('product.show');
// === 2. Категории и страницы ===
Route::get('/pies', function () {
    $slug ='pies';
    return app(CatalogController::class)->show($slug);
})->name('catalog.index');

Route::get('/nas-blagodaryat', function () {
    $slug ='nas-blagodaryat';
    $page = Pages::query()->where('slug', $slug)->first();
    return app(PageController::class)->show($page);
})->name('blagodaryat.index');



/*Route::get('/blog/{categorySlug}', [BlogController::class, 'index'])
    ->where('categorySlug', '[A-Za-z0-9\-_]+')
    ->name('blog.category');*/

Route::get('/{slug}', function ($slug) {
    // сначала ищем в pages
    $page = Pages::query()->where('slug', $slug)->first();
    if ($page) {
        return app(PageController::class)->show($page);
    }

    // потом ищем категорию
    $category = ProductCategory::query()->where('slug', $slug)->first();
  //  dd($category);
    if ($category || $slug=='pies_hits'  || $slug=='pies_news') {

        return app(CatalogController::class)->show($slug);
    }
    // потом ищем блоги категории
    $BlogCategory = \App\Models\BlogCategory::query()->where('slug', $slug)->first();
    if ($BlogCategory) {
        return app(BlogController::class)->index($slug);
    }
    // если ни то, ни то — 404
    return response()->view('404', [], 404);
})->where('slug', '[A-Za-z0-9\-_]+'); // можно усложнить regex под твои слаги
/*
Route::get('/blog', [BlogController::class, 'index'])
    ->name('blog.index'); // дефолтная категория: blog
*/
/*Route::get('/blog/{slug}', [BlogController::class, 'show'])
    // ->where('slug', '[A-Za-z0-9\-_]+')
    ->where('slug', '[^/]+')
    ->name('blog.show');*/

Route::post('/products/{product}/reviews', [ProductReviewController::class, 'store'])
    ->name('product.reviews.store')
    ->middleware('throttle:5,1'); // антиспам

Route::post('/blog/comments', [BlogController::class, 'storeComment'])
    ->name('blog.comments.store')
    ->middleware('throttle:5,1'); // антиспам

Route::fallback(function () {
    return response()->view('404', [], 404);
});
