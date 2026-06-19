<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    protected $table = 'bs_settings';
    protected $fillable = [
        'site_name',
        'logo_path',
        'favicon_path',
        'phone',
        'email',
        'social_links',
        'admin_color_scheme',
        'admin_settings' ,
        'default_language_code',
        'cart_auth_method',
    ];
    // Если соцссылки сохраняются как JSON
    protected $casts = [
        'social_links' => 'array',
        'admin_settings'  => 'array',
    ];

    public static function current(): self
    {
        return static::query()->first() ?? static::query()->create([
            'site_name' => config('app.name', 'Laravel'),
            'default_language_code' => config('app.locale', 'uk'),
            'admin_color_scheme' => 'primary',
            'admin_settings' => [],
            'cart_auth_method' => 'phone_password_sms',
        ]);
    }
    /** быстро получить JSON-настройку: Setting::admin('sidebar.collapsible_on_desktop', true) */
    public static function admin(?string $key = null, mixed $default = null): mixed
    {
        /** @var array|null $arr */
        $arr = optional(static::query()->select('admin_settings')->first())->admin_settings ?? [];
        return $key === null ? $arr : data_get($arr, $key, $default);
    }

    /** опционально: аккуратно записать часть JSON (для сидеров/команд) */
    public static function putAdmin(string $key, mixed $value): void
    {
        $row = static::current();
        $data = $row->admin_settings ?? [];
        data_set($data, $key, $value);
        $row->admin_settings = $data;
        $row->save();
    }
    public static function getActiveLocales(): array
    {
        return Language::where('active', true)
            ->orderBy('position')
            ->pluck('code')
            ->map(fn($c) => strtolower($c))
            ->toArray();
    }

}
