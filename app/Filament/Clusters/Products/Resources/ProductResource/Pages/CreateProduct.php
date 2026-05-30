<?php

namespace App\Filament\Clusters\Products\Resources\ProductResource\Pages;

use App\Models\Shop\ProductCharacteristicValue;
use App\Filament\Clusters\Products\Resources\ProductResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;
use App\Models\Shop\Product;
use  App\Models\Shop\ProductImage;
use App\Filament\Clusters\Products\Resources\ProductResource\Concerns\HasCharacteristicMeta;
use Illuminate\Support\Str;

// <— ВАЖНО: правильный namespace

class CreateProduct extends CreateRecord
{
   // use HasCharacteristicMeta; // <— подмешиваем методы + свойства
    protected static string $resource = ProductResource::class;

  /*  public function mount(): void
    {
        parent::mount();
        $this->categoryId = null;
        $this->charMeta   = [];
    }*/

    protected function mutateFormDataBeforeCreate(array $data): array
    {
      /*  $data['characteristics'] = $data['characteristics_payload'] ?? [];
        unset($data['characteristics_payload']);
*/
        if (blank($data['sku'] ?? null)) {
            $data['sku'] = ProductResource::nextAvailableSku();
        }

        return $data;
    }
    protected function beforeValidate(): void
    {
        // Сырое состояние без правил/кастов
    /*   dd('create.beforeValidate.raw', [
            'raw' => $this->form->getRawState(),
        ]);*/
    }
// В CreateProduct (страница ресурса)
    protected function mutateFormDataBeforeFill(array $data): array
    {
        /* $data['characteristics'] ??= []; // гарантируем существование */
        $data['legacy_consist_rows'] = [];

        return $data;
    }
    public static function getNavigationLabel(): string
    {
        return __('product.nav.navigation_label');
    }

    public function getTitle(): string
    {
        return __('product.pages.edit_title');
    }

    public function getHeading(): string
    {
        return __('product.pages.create_title');
    }

    public function getBreadcrumb(): string
    {
        return __('product.pages.create_breadcrumb');
    }
    public function getSaveButtonLabel(): string
    {
        return __('product.actions.save');
    }

    public function getCancelButtonLabel(): string
    {
        return __('product.actions.cancel');
    }

    public function getDeleteButtonLabel(): string
    {
        return __('product.actions.delete');
    }
    protected function afterValidate(): void
    {

    }
    protected function handleRecordCreation(array $data): Product
    {
        $legacyConsistRows = (array) ($data['legacy_consist_rows'] ?? []);
        unset($data['legacy_consist_rows']);

        $record = Product::create($data);
      //  dd('555');
        foreach ($data['images'] ?? [] as $imagePath) {
            $record->images()->create([
                'path' => $imagePath,
            ]);
        }
        $chars  = $data['characteristics']       ?? [];
        $prices = $data['characteristics_price'] ?? [];
        foreach ($chars as $key => $value) {
            // ключ "char_6" → 6 (или просто 6, если так пришло)
            $charId = is_numeric($key) ? (int) $key : (int) Str::after((string) $key, 'char_');
            if ($charId <= 0) {
                continue; // страховка от мусора
            }
            // допустимые value_id для этой характеристики (белый список)
            $validValueIds = \App\Models\Shop\CharacteristicValue::query()
                ->where('characteristic_id', $charId)
                ->pluck('id')
                ->map(fn ($v) => (int) $v)
                ->all();
            // значение → массив ID
            $vals = is_array($value) ? $value : [$value];

            // если прилетела булева мапа, собираем выбранные ключи
            if (! array_is_list($vals)) {
                $vals = collect($vals)->filter()->keys()->all();
            }

            // нормализуем к массиву целых > 0 и отфильтровываем невалидные
            $valIds = collect($vals)
                ->filter(fn ($v) => is_scalar($v) && preg_match('/^\d+$/', (string) $v))
                ->map(fn ($v) => (int) $v)
                ->filter(fn ($v) => $v > 0)
                ->intersect($validValueIds)       // ← критично: только существующие value_id
                ->unique()
                ->values()
                ->all();
            if (empty($valIds)) {
                // можно залогировать для диагностики
                logger()->warning('Skip empty characteristic values', ['charId' => $charId, 'raw' => $value]);
                continue;
            }

            foreach ($valIds as $valId) {
                $price = (float) data_get($prices, "{$charId}.{$valId}", 0);

                \App\Models\Shop\ProductCharacteristicValue::create([
                    'product_id'              => $record->id,
                    'characteristic_id'       => $charId,
                    'characteristic_value_id' => $valId,
                    'price_modifier'          => $price ?: null,
                ]);
            }
        }



/*
        foreach ($data['characteristics'] ?? [] as $characteristicId => $value) {
            $values = is_array($value) ? $value : [$value];
            // dd($values);
            foreach ($values as $val) {
                //     dd($characteristicId, $value, $val);
                ProductCharacteristicValue::create([
                    'product_id' => $record->id,
                    'characteristic_id' => $characteristicId,
                    'characteristic_value_id' => $val,
                ]);
            }
        }*/


        ProductResource::syncLegacyConsistForProduct((int) $record->id, $legacyConsistRows);

        return $record;
    }
}
