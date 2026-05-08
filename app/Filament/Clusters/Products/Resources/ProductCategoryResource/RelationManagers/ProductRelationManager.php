<?php

namespace App\Filament\Clusters\Products\Resources\ProductCategoryResource\RelationManagers;

use App\Models\Setting;
use App\Models\Shop\Product;
use Filament\Forms\Form;
use Filament\Tables\Columns\TextInputColumn;
use Filament\Tables\Table;
use App\Filament\Clusters\Products\Resources\ProductResource;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Database\Eloquent\Builder;

class ProductRelationManager extends RelationManager
{
    protected static string $relationship = 'products'; // метод связи в модели Category

    protected function getTableQuery(): Builder
    {
        $categoryId = (int) $this->getOwnerRecord()->getKey();

        return Product::query()
            ->where(function (Builder $query) use ($categoryId): void {
                $query->where('category_id', $categoryId)
                    ->orWhereHas('categories', fn (Builder $categories) => $categories->whereKey($categoryId))
                    ->orWhereHas('parent', function (Builder $parent) use ($categoryId): void {
                        $parent->where('category_id', $categoryId)
                            ->orWhereHas('categories', fn (Builder $categories) => $categories->whereKey($categoryId));
                    });
            });
    }

    public function form(Form $form): Form
    {
        return ProductResource::form($form);
    }

    public function table(Table $table): Table
    {
        $defaultLocale = Setting::value('default_language_code') ?: config('app.locale');

        return $table
            ->modifyQueryUsing(function (Builder $query): Builder {
                return $query
                    ->orderByRaw('COALESCE(parent_id, id) asc')
                    ->orderByRaw('CASE WHEN parent_id IS NULL THEN 0 ELSE 1 END asc')
                    ->orderBy('sort')
                    ->orderBy('id');
            })
            ->recordClasses(fn (Product $record): string => $record->parent_id === null
                ? 'category-product-parent-row'
                : 'category-product-child-row'
            )
            ->columns([
                TextColumn::make('title')
                    ->label(__('product.columns.title'))->sortable()->searchable()
                    ->getStateUsing(function (\App\Models\Shop\Product $record, TextColumn $column, $livewire) use ($defaultLocale) {
                        if (!empty($record->short_name)) {
                            return $record->short_name;
                        }
                        //  dd($lang);
                        return $record->getTranslation('title', $defaultLocale);

                    })
                    //->getStateUsing(fn ($record) => $record->getTranslation('title', $defaultLocale))
                    ->searchable(),

                TextInputColumn::make('price')
                    ->type('number')   // HTML5 number
                    ->step('0.01')
                    ->rules(['numeric','min:0']) // валидация на сохранение
                    ->alignRight()
                    ->label(__('product.columns.price'))
                    ->sortable(),

                TextInputColumn::make('old_price')
                    ->type('number')
                    ->step('0.01')
                    ->rules(['nullable', 'numeric', 'min:0'])
                    ->alignRight()
                    ->label(__('product.fields.old_price'))
                    ->sortable(),

                TextInputColumn::make('discount_percent')
                    ->type('number')
                    ->step('1')
                    ->rules(['nullable', 'numeric', 'min:0', 'max:99.99'])
                    ->alignRight()
                    ->label('Скидка %')
                    ->getStateUsing(function (Product $record): ?float {
                        $oldPrice = (float) ($record->old_price ?? 0);
                        $price = (float) ($record->price ?? 0);

                        if ($oldPrice <= 0 || $price <= 0 || $oldPrice <= $price) {
                            return null;
                        }

                        return round((($oldPrice - $price) / $oldPrice) * 100, 2);
                    })
                    ->updateStateUsing(function (Product $record, $state): ?float {
                        $discountPercent = (float) ($state ?? 0);

                        if ($discountPercent <= 0) {
                            $existingOldPrice = (float) ($record->old_price ?? 0);

                            if ($existingOldPrice > 0) {
                                $record->price = round($existingOldPrice);
                            }

                            $record->old_price = null;
                            $record->save();

                            return null;
                        }

                        $currentPrice = (float) ($record->price ?? 0);
                        $existingOldPrice = (float) ($record->old_price ?? 0);
                        $basePrice = ($existingOldPrice > 0 && $existingOldPrice > $currentPrice)
                            ? $existingOldPrice
                            : $currentPrice;

                        if ($basePrice <= 0) {
                            return null;
                        }

                        $record->old_price = round($basePrice);
                        $record->price = round($basePrice * (1 - ($discountPercent / 100)));
                        $record->save();

                        return round((($record->old_price - $record->price) / $record->old_price) * 100, 2);
                    }),

                TextColumn::make('sku')
                    ->label(__('product.columns.sku'))
                    ->searchable(),

                Tables\Columns\IconColumn::make('in_stock')
                    ->label(__('product.columns.in_stock'))
                    ->boolean(),

                TextColumn::make('quantity')
                    ->label(__('product.columns.quantity')),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->after(function ($record, $data) {
                        $record->syncFromFormState($data);
                    }),
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->after(function ($record, $data) {
                        $record->syncFromFormState($data);
                    }),
                Tables\Actions\DeleteAction::make(),
            ])
            ->groupedBulkActions([
                Tables\Actions\DeleteBulkAction::make(),
            ]);
    }

    protected function afterSave(): void
    {
        $this->record->syncFromFormState($this->form->getState());
    }
}
