<?php

namespace App\Filament\Clusters\Products\Resources\ProductCategoryResource\Pages;

use App\Filament\Clusters\Products;
use App\Filament\Clusters\Products\Resources\ProductCategoryResource;

use Filament\Forms\Components\TextInput;
use Illuminate\Support\Str;
use SolutionForest\FilamentTree\Actions\ViewAction;
use SolutionForest\FilamentTree\Actions\EditAction;
use SolutionForest\FilamentTree\Actions\DeleteAction;
use SolutionForest\FilamentTree\Actions;
use SolutionForest\FilamentTree\Concern;

use Filament\Pages\Actions\CreateAction;
//use SolutionForest\FilamentTree\Pages\TreePage;
//use SolutionForest\FilamentTree\Pages\TreePage as BasePage;
use SolutionForest\FilamentTree\Resources\Pages\TreePage as BasePage;
use SolutionForest\FilamentTree\Support\Utils;
use Illuminate\Database\Eloquent\Model;
use Filament\Pages\Actions\Action;
use App\Models\Setting;
use Illuminate\Support\Arr;
//use SolutionForest\FilamentTree\Widgets\LocaleSwitcher;
use SolutionForest\FilamentTree\Concern\TreeRecords\Translatable;
use Filament\Actions\LocaleSwitcher;
use Filament\Pages\SubNavigationPosition;
class ProductCategoryTree extends BasePage
{
  //  use Translatable;
    //  use ListRecords\Concerns\Translatable;
    protected static string $resource = ProductCategoryResource::class;
    protected bool $enableTreeTitle = true;
    protected static ?string $cluster = Products::class;
    protected static string $model = \App\Models\Shop\ProductCategory::class;
    protected static SubNavigationPosition $subNavigationPosition = SubNavigationPosition::Top;
    protected static ?int $navigationSort = 1;
    //  protected static string $view = 'filament-tree::pages.resource.tree';
    // можна вивидити в вспливаючому вікні ті поля які треба
    /* protected function getFormSchema(): array
     {
         return [
             TextInput::make('title'),
         ];
     }*/

    protected function getActions(): array
    {
        return [
            LocaleSwitcher::make(),
            CreateAction::make()
                ->label('Добавить категорию'),
            //  LocaleSwitcher::make(),
        ];
    }

    public function getTranslatableLocales(): array
    {

        $locales = \App\Models\Language::where('active', true)
            ->orderBy('position')
            ->pluck('code')
            ->toArray();

        // Если текущая локаль не входит в список — установим первую по приоритету
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
    public static function getNavigationLabel(): string
    {
        return 'Дерево категорий';
    }
    public  function getTitle(): string
    {
        return 'Категории товаров';
    }
    protected function getTreeRecordSubtitleColumnName(): ?string
    {
        return 'slug';
    }
    // вывод в дереве или tittle или доп кнокпок
    public function getTreeRecordTitle(?Model $record = null): string
    {
        if (! $record) return '';

        $locale = \App\Models\Setting::value('default_language_code') ?: app()->getLocale();
        $column = method_exists($record, 'determineTitleColumnName') ? $record->determineTitleColumnName() : 'title';

        $raw = $record->{$column};

        // вытаскиваем строку из переводимого поля
        if (is_array($raw)) {
            $title = $raw[$locale] ?? reset($raw) ?? '-';
        } else {
            // если есть метод getTranslation — используем его, иначе берём как есть
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

    //убрать это меню слева
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


    /*  public static function shouldRegisterNavigation(): bool
      {
          return static::canAccess();
      }*/

// чтобы не в модальном окне открывалось а как страница
    protected function getTreeActions(): array
    {
        return [
            //   ViewAction::make(),
            EditAction::make()
                ->url(fn ($record) => ProductCategoryResource::getUrl('edit', ['record' => $record]))
                ->openUrlInNewTab(false) // можно поставить true — если хочешь в новой вкладке
                ->label('Редактировать')
                ->icon('heroicon-m-pencil-square'),
            //   DeleteAction::make(),
        ];
    }
    protected function getTreeRecordDetailsUsingHtml(): bool
    {
        return true;
    }

}
