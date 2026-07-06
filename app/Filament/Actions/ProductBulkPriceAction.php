<?php

namespace App\Filament\Actions;

use App\Filament\Clusters\Products\Resources\ProductResource;
use App\Models\Setting;
use App\Services\ProductBulkPriceService;
use App\Services\ProductPriceCalculator;
use Closure;
use Filament\Facades\Filament;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Notifications\Notification;
use Filament\Support\Enums\MaxWidth;
use Filament\Tables\Actions\Action as TableAction;
use Illuminate\Support\HtmlString;
use Throwable;

class ProductBulkPriceAction
{
    public static function makeTableAction(): TableAction
    {
        return TableAction::make('mass_price_change')
            ->label('Масова зміна цін')
            ->icon('heroicon-o-banknotes')
            ->visible(fn (): bool => ProductBulkPriceService::canManage(Filament::auth()->user()))
            ->form(static::form(
                fn (Get $get, $livewire): array => static::resolveTargetIds($livewire, [
                    'scope' => $get('scope'),
                    'category_ids' => $get('category_ids'),
                    'include_variants' => $get('include_variants'),
                ]),
                true,
            ))
            ->mountUsing(function (Form $form): void {
                $form->fill([
                    'scope' => ProductBulkPriceService::SCOPE_FILTERED,
                    'category_ids' => [],
                    'include_variants' => true,
                    'operation' => null,
                    'value' => null,
                    'rounding_precision' => 0,
                    'old_price_mode' => ProductPriceCalculator::KEEP_OLD_PRICE,
                ]);
            })
            ->requiresConfirmation()
            ->modalHeading('Масова зміна цін товарів')
            ->modalDescription('Перевірте область застосування і попередній перегляд. Зміна буде записана в журнал.')
            ->modalSubmitActionLabel('Застосувати')
            ->modalWidth(MaxWidth::SevenExtraLarge)
            ->action(function (array $data, $livewire): void {
                $user = Filament::auth()->user();
                abort_unless(ProductBulkPriceService::canManage($user), 403);

                try {
                    $service = app(ProductBulkPriceService::class);
                    $ids = static::resolveTargetIds($livewire, $data);
                    $scope = (string) ($data['scope'] ?? ProductBulkPriceService::SCOPE_FILTERED);
                    $batch = $service->apply($ids, $data, $user, [
                        'scope' => $scope,
                        'category_ids' => $scope === ProductBulkPriceService::SCOPE_CATEGORIES
                            ? array_values((array) ($data['category_ids'] ?? []))
                            : null,
                        'filters' => $scope === ProductBulkPriceService::SCOPE_FILTERED
                            ? [
                                'filters' => $livewire->tableFilters,
                                'search' => $livewire->tableSearch,
                                'column_searches' => $livewire->tableColumnSearches,
                            ]
                            : null,
                    ]);

                    Notification::make()
                        ->success()
                        ->title('Ціни змінено')
                        ->body("Оновлено товарів: {$batch->affected_count}.")
                        ->send();
                } catch (Throwable $exception) {
                    Notification::make()
                        ->danger()
                        ->title('Не вдалося змінити ціни')
                        ->body($exception->getMessage())
                        ->send();
                }
            });
    }

    /**
     * @param  Closure(Get, mixed): array<int>  $idsResolver
     */
    public static function form(Closure $idsResolver, bool $withScope): array
    {
        $schema = [];

        if ($withScope) {
            $schema[] = Select::make('scope')
                ->label('Область застосування')
                ->options(ProductBulkPriceService::scopeOptions())
                ->default(ProductBulkPriceService::SCOPE_FILTERED)
                ->required()
                ->native(false)
                ->live();

            $schema[] = Select::make('category_ids')
                ->label('Категорії товарів')
                ->multiple()
                ->options(function (): array {
                    $locale = Setting::value('default_language_code') ?: app()->getLocale();

                    return ProductResource::getCategoryFilterOptions($locale);
                })
                ->searchable()
                ->preload()
                ->required(fn (Get $get): bool => $get('scope') === ProductBulkPriceService::SCOPE_CATEGORIES)
                ->visible(fn (Get $get): bool => $get('scope') === ProductBulkPriceService::SCOPE_CATEGORIES)
                ->live();
        }

        $schema[] = Toggle::make('include_variants')
            ->label('Включити варіанти товарів')
            ->helperText('Увімкнено за замовчуванням. Вимкніть, щоб змінити тільки батьківські товари зі списку.')
            ->default(true)
            ->live();

        $schema[] = Grid::make(3)->schema([
            Select::make('operation')
                ->label('Операція')
                ->options(ProductPriceCalculator::operationOptions())
                ->required()
                ->native(false)
                ->live(),
            TextInput::make('value')
                ->label('Значення')
                ->numeric()
                ->minValue(0)
                ->rules(fn (Get $get): array => $get('operation') === ProductPriceCalculator::DECREASE_PERCENT
                    ? ['max:100']
                    : [])
                ->step(0.01)
                ->required()
                ->live(debounce: 400),
            Select::make('rounding_precision')
                ->label('Округлення ціни')
                ->options([
                    0 => 'До цілого (0 знаків)',
                    1 => '1 знак після коми',
                    2 => '2 знаки після коми',
                ])
                ->default(0)
                ->required()
                ->native(false)
                ->live(),
        ]);

        $schema[] = Radio::make('old_price_mode')
            ->label('Що робити зі старою ціною?')
            ->options(ProductPriceCalculator::oldPriceModeOptions())
            ->default(ProductPriceCalculator::KEEP_OLD_PRICE)
            ->required()
            ->live();

        $schema[] = Placeholder::make('price_change_preview')
            ->label('Попередній перегляд')
            ->content(function (Get $get, $livewire) use ($idsResolver): HtmlString {
                if (! $get('operation') || $get('value') === null || ! $get('old_price_mode')) {
                    return new HtmlString('<span class="text-sm text-gray-500">Оберіть операцію та введіть значення.</span>');
                }

                try {
                    $ids = $idsResolver($get, $livewire);
                    $preview = app(ProductBulkPriceService::class)->preview($ids, [
                        'operation' => $get('operation'),
                        'value' => $get('value'),
                        'old_price_mode' => $get('old_price_mode'),
                        'rounding_precision' => $get('rounding_precision'),
                    ]);
                } catch (Throwable $exception) {
                    return new HtmlString('<span class="text-sm text-danger-600">'.e($exception->getMessage()).'</span>');
                }

                return static::renderPreview($preview);
            })
            ->columnSpanFull();

        return $schema;
    }

    private static function renderPreview(array $preview): HtmlString
    {
        $count = (int) ($preview['count'] ?? 0);

        if ($count === 0) {
            return new HtmlString('<div class="rounded-lg border border-warning-300 bg-warning-50 p-3 text-sm text-warning-700">Товарів для зміни не знайдено.</div>');
        }

        $rows = collect($preview['rows'] ?? [])->map(function (array $row): string {
            return '<tr class="border-t">'
                .'<td class="px-2 py-2" style="width:40%; min-width:320px">'.e($row['label']).'</td>'
                .'<td class="px-2 py-2 text-right">'.static::money($row['old_price_value']).'</td>'
                .'<td class="px-2 py-2 text-right font-medium">'.static::money($row['new_price_value']).'</td>'
                .'<td class="px-2 py-2 text-right">'.static::money($row['old_old_price']).'</td>'
                .'<td class="px-2 py-2 text-right">'.static::money($row['new_old_price']).'</td>'
                .'<td class="px-2 py-2 text-right">'.static::percent($row['old_discount']).'</td>'
                .'<td class="px-2 py-2 text-right">'.static::percent($row['new_discount']).'</td>'
                .'</tr>';
        })->implode('');

        return new HtmlString(
            '<div class="space-y-3">'
            .'<div class="font-medium">Буде змінено унікальних товарів: '.number_format($count, 0, ',', ' ').'</div>'
            .'<div class="overflow-x-auto rounded-lg border">'
            .'<table class="min-w-full text-sm"><thead class="bg-gray-50"><tr>'
            .'<th class="px-2 py-2 text-left" style="width:40%; min-width:320px">Товар</th>'
            .'<th class="px-2 py-2 text-right">Ціна до</th>'
            .'<th class="px-2 py-2 text-right">Ціна після</th>'
            .'<th class="px-2 py-2 text-right">Стара до</th>'
            .'<th class="px-2 py-2 text-right">Стара після</th>'
            .'<th class="px-2 py-2 text-right">Знижка до</th>'
            .'<th class="px-2 py-2 text-right">Знижка після</th>'
            .'</tr></thead><tbody>'.$rows.'</tbody></table></div>'
            .'<div class="text-xs text-gray-500">Показано перші 5 товарів.</div>'
            .'</div>'
        );
    }

    private static function money(mixed $value): string
    {
        return $value === null ? '—' : number_format((float) $value, 2, ',', ' ');
    }

    private static function percent(mixed $value): string
    {
        return $value === null ? '0%' : number_format((float) $value, 2, ',', ' ').'%';
    }

    private static function resolveTargetIds($livewire, array $data): array
    {
        $service = app(ProductBulkPriceService::class);
        $scope = (string) ($data['scope'] ?? ProductBulkPriceService::SCOPE_FILTERED);
        $includeVariants = (bool) ($data['include_variants'] ?? true);

        return match ($scope) {
            ProductBulkPriceService::SCOPE_CATEGORIES => $service->idsForCategories(
                (array) ($data['category_ids'] ?? []),
                $includeVariants,
            ),
            ProductBulkPriceService::SCOPE_ALL => $service->idsForAll($includeVariants),
            ProductBulkPriceService::SCOPE_FILTERED => $service->idsFromQuery(
                $livewire->getFilteredTableQuery(),
                $includeVariants,
            ),
            default => [],
        };
    }
}
