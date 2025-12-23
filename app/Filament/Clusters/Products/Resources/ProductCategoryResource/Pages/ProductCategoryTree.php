<?php

namespace App\Filament\Clusters\Products\Resources\ProductCategoryResource\Pages;

use App\Filament\Clusters\Products;
use App\Filament\Clusters\Products\Resources\ProductCategoryResource;
use Filament\Pages\Actions\CreateAction;
use SolutionForest\FilamentTree\Resources\Pages\TreePage as BasePage;
use Illuminate\Database\Eloquent\Model;
use Filament\Actions\LocaleSwitcher;
use Filament\Pages\SubNavigationPosition;
use Illuminate\Support\Facades\Gate;
use App\Models\Shop\ProductCategory;

class ProductCategoryTree extends BasePage
{
    protected static string $resource = ProductCategoryResource::class;

    protected bool $enableTreeTitle = true;

    protected static ?string $cluster = Products::class;

    protected static string $model = \App\Models\Shop\ProductCategory::class;

    protected static SubNavigationPosition $subNavigationPosition = SubNavigationPosition::Top;

    protected static ?int $navigationSort = 1;

    /** Локализация левой навигации */
    public static function getNavigationLabel(): string
    {
        return __('category.tree.nav_label'); // «Дерево категорій»
    }

    /** Крошка №1 (кластер) — важна! */
    public static function getClusterBreadcrumb(): string
    {
        return __('product.nav.cluster'); // «Продукти / Продукты / Products»
    }

    /** Последняя крошка для этой страницы */
    public function getBreadcrumb(): string
    {
        return __('category.tree.breadcrumb'); // «Категорії товарів»
    }

    /** Заголовок H1 */
    public function getTitle(): string
    {
        return __('category.tree.title'); // «Категорії товарів»
    }

    protected function getActions(): array
    {
        return [
            LocaleSwitcher::make(),
            CreateAction::make()
                ->label(__('category.actions.create')), // «Додати категорію»
        ];
    }

    public function getTranslatableLocales(): array
    {
        $locales = \App\Models\Language::where('active', true)
            ->orderBy('position')
            ->pluck('code')
            ->toArray();

        if (! in_array(app()->getLocale(), $locales)) {
            app()->setLocale($locales[0]);
        }

        return $locales;
    }

    public function mount(): void
    {
        $defaultLocale = \App\Models\Language::where('active', true)
            ->orderBy('position')
            ->value('code');

        app()->setLocale($defaultLocale);
    }

    protected function hasEditAction(): bool
    {
        return true;
    }

    protected function getTreeRecordSubtitleColumnName(): ?string
    {
        return 'slug';
    }

    /** Текст узла в дереве */
    public function getTreeRecordTitle(?Model $record = null): string
    {
        if (! $record) return '';

        $locale = \App\Models\Setting::value('default_language_code') ?: app()->getLocale();
        $column = method_exists($record, 'determineTitleColumnName') ? $record->determineTitleColumnName() : 'title';

        $raw = $record->{$column};

        if (is_array($raw)) {
            $title = $raw[$locale] ?? reset($raw) ?? '-';
        } else {
            $title = method_exists($record, 'getTranslation')
                ? ($record->getTranslation($column, $locale) ?? (string) $raw ?? '-')
                : ((string) ($raw ?? '-'));
        }

        $status = $record->is_visible ? '✅' : '❌';

        return "{$title} | {$status}";
    }

    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }

    protected function hasTreeRecordDetails(): bool
    {
        return true;
    }

    protected function hasDeleteAction(): bool
    {
        return false;
    }

    protected function hasViewAction(): bool
    {
        return false;
    }

    public static function canAccess(): bool
    {
        $u = auth('admin')->user();
        if (! $u) return false;

        // Проверяем, что это User модель, а не Client
        if (!$u instanceof \App\Models\User) {
            return false;
        }

        if (method_exists($u, 'hasRole') && $u->hasRole(config('shield.super_admin.name', 'super_admin'))) {
            return true;
        }
        if ($u->can('viewAny', ProductCategory::class)) {
            return true;
        }

        return $u->hasAnyPermission([
            'view_any_product_category',
            'view_product_category',
            'view_any_product::category',
            'view_product::category',
        ]);
    }

    protected function authorizeAccess(): void
    {
        abort_unless(static::canAccess(), 403);
    }

    /** Кнопки возле узлов дерева */
    protected function getTreeActions(): array
    {
        return [
            \SolutionForest\FilamentTree\Actions\EditAction::make()
                ->url(fn ($record) => ProductCategoryResource::getUrl('edit', ['record' => $record]))
                ->openUrlInNewTab(false)
                ->label(__('category.actions.edit')) // «Редагувати»
                ->icon('heroicon-m-pencil-square'),
        ];
    }

    protected function getTreeRecordDetailsUsingHtml(): bool
    {
        return true;
    }
}
