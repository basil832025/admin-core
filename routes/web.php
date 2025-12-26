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
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;


Route::get('/', function () {
    return view('welcome');
});

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
    
    // Сохраняем в сессии
    session(['locale' => $locale]);
    
    // Устанавливаем локаль приложения
    app()->setLocale($locale);
    
    // Устанавливаем локаль для Carbon (даты)
    if (class_exists(\Carbon\Carbon::class)) {
        \Carbon\Carbon::setLocale($locale);
    }
    
    return back();
})
    ->name('admin.switch-locale')
    ->middleware(['web','auth']);         // доступ только залогиненному

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
Route::post('/checkout/apply-coupon', [CheckoutController::class, 'applyCoupon'])->name('checkout.apply-coupon');
Route::post('/checkout/promo', [CheckoutController::class, 'updatePromo'])
    ->name('checkout.promo');
Route::get('/checkout/{order}/pay/liqpay', [CheckoutController::class, 'payLiqPay'])
    ->name('checkout.pay.liqpay');
Route::get('/filter', [CatalogController::class, 'filter'])
    ->name('catalog.filter');
Route::post('/liqpay/callback', [LiqPayController::class, 'callback'])
    ->name('liqpay.callback')
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
});

Route::middleware(['web'])->group(function () {
  //  Route::redirect('/favorites', '/', 302)->name('favorites.index');
    Route::redirect('/orders', '/', 302)->name('orders.index');
    Route::get('/orders/history', fn() => 'Orders history stub')->name('orders.history');
    Route::get('/bonuses', fn() => 'Bonuses stub')->name('bonuses.index');
  //  Route::redirect('/profile', '/', 302)->name('profile.show');
    Route::redirect('/addresses', '/profile/addresses', 302)->name('addresses.index');
    // ВАЖНО: правильные имена для аутентификации
    Route::redirect('/login', '/', 302)->name('login');   // если страницы логина пока нет
    // либо так: Route::view('/login', 'pages.stub')->name('login')->defaults('title','Увійти');

    Route::middleware('guest')->group(function () {
        Route::get('/auth/redirect/{provider}', [ClientAuthController::class,'redirect'])
            ->whereIn('provider',['google','facebook','apple'])->name('auth.redirect');

        Route::get('/auth/callback/{provider}', [ClientAuthController::class,'callback'])
            ->whereIn('provider',['google','facebook','apple'])->name('auth.callback');

       Route::post('/auth/register', [ClientAuthController::class,'register'])->name('auth.register');
        Route::post('/auth/login',    [ClientAuthController::class,'login'])->name('auth.login');

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


