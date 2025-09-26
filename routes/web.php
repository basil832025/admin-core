<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Front\HomeController;
use App\Http\Controllers\Front\ProductController;
Route::get('/', function () {
    return view('welcome');
});
Route::get('/admin/switch-locale/{locale}', function (string $locale) {
    session(['locale' => $locale]);   // запоминаем язык в сессии
    app()->setLocale($locale);        // применяем сразу
    return back();
})
    ->whereIn('locale', ['uk','en','ru'])
    ->name('admin.switch-locale')
    ->middleware(['web','auth']);         // доступ только залогиненному


Route::get('/', [HomeController::class, 'index'])->name('home');

// Страница товара по slug
Route::get('/product/{product:slug}', [ProductController::class, 'show'])
    ->name('product.show');

Route::get('/lang/{locale}', function (string $locale) {
    $allowed = ['uk','ru','en'];             // список поддерживаемых
    abort_unless(in_array($locale, $allowed, true), 404);

    session(['locale' => $locale]);          // запоминаем в сессии
    app()->setLocale($locale);               // чтобы применилось сразу на редиректе

    // опц.: доп. сохранение в cookie, чтобы помнить язык между сессиями
    return back(status: 303)->cookie('locale', $locale, 60 * 24 * 365);
})->name('lang.switch');

Route::middleware(['web'])->group(function () {
    Route::redirect('/favorites', '/', 302)->name('favorites.index');
    Route::redirect('/orders', '/', 302)->name('orders.index');
    Route::redirect('/bonus', '/', 302)->name('bonus.index');
    Route::redirect('/profile', '/', 302)->name('profile.show');
    Route::redirect('/addresses', '/', 302)->name('addresses.index');
    // ВАЖНО: правильные имена для аутентификации
    Route::redirect('/login', '/', 302)->name('login');   // если страницы логина пока нет
    // либо так: Route::view('/login', 'pages.stub')->name('login')->defaults('title','Увійти');

    Route::post('/logout', function () {
        auth('web')->logout();
        request()->session()->invalidate();
        request()->session()->regenerateToken();
        return redirect('/');
    })->name('logout');


});
