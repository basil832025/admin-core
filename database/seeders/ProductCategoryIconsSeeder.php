<?php

namespace Database\Seeders;

use App\Services\CatalogCacheService;
use App\Support\ProductCategoryIcons;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

class ProductCategoryIconsSeeder extends Seeder
{
    /** @var array<string, array{icon: string, color: string}> */
    public const ASSIGNMENTS = [
        'stock-positions' => ['icon' => 'heroicon-o-tag', 'color' => '#F59E0B'],
        'pies' => ['icon' => 'custom:pies', 'color' => '#F97316'],
        'kombo-nabor' => ['icon' => 'custom:combo', 'color' => '#F97316'],
        'tasting-sets' => ['icon' => 'custom:tasting-set', 'color' => '#F59E0B'],
        'napitki' => ['icon' => 'custom:drinks', 'color' => '#0EA5E9'],
        'sousu-k-pirogam' => ['icon' => 'custom:sauces', 'color' => '#F43F5E'],
        'desertu' => ['icon' => 'custom:cakes', 'color' => '#EC4899'],
        'dish' => ['icon' => 'custom:dishes', 'color' => '#64748B'],
        'surnue-pirogi' => ['icon' => 'custom:cheese', 'color' => '#F97316'],
        'myasnue-pirogi' => ['icon' => 'custom:meat', 'color' => '#F43F5E'],
        'postnue-pirogi' => ['icon' => 'custom:vegan', 'color' => '#10B981'],
        'sladkie-pirogi' => ['icon' => 'custom:sweets', 'color' => '#D946EF'],
        'tradicionnue' => ['icon' => 'custom:traditional', 'color' => '#F97316'],
    ];

    public function run(): void
    {
        if (config('project.name') !== '3piroga') {
            throw new RuntimeException('ProductCategoryIconsSeeder разрешено запускати лише для APP_PROJECT=3piroga.');
        }
        if (! Schema::hasColumns('bs_product_categories', ['icon', 'icon_color'])) {
            throw new RuntimeException('Спочатку виконайте міграції полів icon та icon_color.');
        }

        foreach (ProductCategoryIconSvgLibrary::ICONS as $name => $svg) {
            if (ProductCategoryIcons::valid("custom:{$name}") !== "custom:{$name}") {
                $installed = ProductCategoryIcons::installCode($svg, $name, replace: true);
                if ($installed !== "custom:{$name}") {
                    throw new RuntimeException("Не вдалося встановити SVG-іконку custom:{$name}.");
                }
            }
        }

        foreach (self::ASSIGNMENTS as $slug => $settings) {
            if (ProductCategoryIcons::valid($settings['icon']) !== $settings['icon']) {
                throw new RuntimeException("Іконка {$settings['icon']} для {$slug} відсутня або некоректна.");
            }
        }

        $found = DB::table('bs_product_categories')
            ->whereIn('slug', array_keys(self::ASSIGNMENTS))
            ->pluck('slug')
            ->all();
        $missing = array_values(array_diff(array_keys(self::ASSIGNMENTS), $found));

        DB::transaction(function (): void {
            foreach (self::ASSIGNMENTS as $slug => $settings) {
                DB::table('bs_product_categories')->where('slug', $slug)->update([
                    'icon' => $settings['icon'],
                    'icon_color' => $settings['color'],
                ]);
            }
        });

        app(CatalogCacheService::class)->bump();
        $this->command?->info('Іконки та кольори категорій 3piroga синхронізовано.');
        if ($missing !== []) {
            $this->command?->warn('Не знайдені категорії: ' . implode(', ', $missing));
        }
    }
}
