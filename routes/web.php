<?php

use Illuminate\Support\Facades\Route;

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
