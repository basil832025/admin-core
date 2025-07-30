<?php

namespace App\Filament\Clusters\Products\Resources\ProductResource\Pages;

use App\Filament\Clusters\Products\Resources\ProductResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use App\Models\Product;
use  App\Models\ProductImage;
class EditProduct extends EditRecord
{
    protected static string $resource = ProductResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
    protected function mutateFormDataBeforeFill(array $data): array
    {
            $this->record->loadMissing('characteristicValues');

        return $data;
    }
    protected function handleRecordUpdate($record, array $data): Product
    {
       // dd($this->form->getState());
        // Обновим модель
        $record->update($data);

        // Очистим старые изображения
        $record->images()->delete();
        // Очистить старые значения
        \App\Models\ProductCharacteristicValue::where('product_id', $record->id)->delete();
     //    dd($data);
        foreach ($data['images'] ?? [] as $image) {
            // Сохраняем файл в диск `public` в директорию products
            if (is_string($image)) {
                // Уже сохранённый файл
                $path = $image;
            } else {
                // Новый файл — загружаем в диск
                $path = $image->store('products', 'public');
            }
           //     dd($path);
            // Сохраняем относительный путь в БД
            $record->images()->create([
                'path' => $path,
            ]);
        }
      //  dd(request()->all());
      //  dd($data);
     //   dd($data['characteristics']);
        foreach ($data['characteristics'] ?? [] as $characteristicId => $value) {
            $char = \App\Models\Characteristic::find($characteristicId);
            if (!$char) {
                continue;
            }

            $values = is_array($value) ? $value : [$value];

            foreach ($values as $valKey => $valData) {
                $entry = [
                    'product_id' => $record->id,
                    'characteristic_id' => $characteristicId,
                ];
                $price=0;
                if (!empty($data['characteristics_price'][$characteristicId][$valKey])){
                    $price = $data['characteristics_price'][$characteristicId][$valKey];

                }
               // dd($valData,$value,$characteristicId,$price,$data);
                switch ($char->field_type) {
                    case 'checkbox':
                        if (is_bool($valData)){
                            if ($valData)
                                $entry['characteristic_value_id'] = (int) $valKey;
                        }

                        else
                            $entry['characteristic_value_id'] = (int) $valData;
                        $entry['price_modifier'] = $price;

                        break;
                    case 'multiselect':
                    case 'radio':
                    case 'select':
                        // 🧠 $valKey = characteristic_value_id
                        // 🧠 $valData может быть либо int, либо массив ['modifier' => 123]

                               $entry['price_modifier'] = $price;
                            $entry['characteristic_value_id'] = (int) $valData;
                          break;

                    case 'text':
                    case 'textarea':
                    case 'color':
                    case 'file':
                        $entry['value_text'] = $valData;
                        break;

                    case 'number':
                    case 'decimal':
                        $entry['value_number'] = is_numeric($valData) ? (float) $valData : null;
                        break;

                    case 'datetime':
                        $entry['value_datetime'] = \Carbon\Carbon::parse($valData);
                        break;

                    default:
                        $entry['value_text'] = $valData;
                        break;
                }
                if (
                    array_key_exists('characteristic_value_id', $entry)
                    && (
                        !$entry['characteristic_value_id']
                        || !\App\Models\CharacteristicValue::where('id', $entry['characteristic_value_id'])->exists()
                    )
                ) {
                    continue;
                }
               // dd($entry);
                \App\Models\ProductCharacteristicValue::updateOrCreate(
                    [
                        'product_id' => $entry['product_id'],
                        'characteristic_id' => $entry['characteristic_id'],
                        'characteristic_value_id' => $entry['characteristic_value_id'] ?? null,
                    ],
                    [
                        'value_text' => $entry['value_text'] ?? null,
                        'value_number' => $entry['value_number'] ?? null,
                        'value_datetime' => $entry['value_datetime'] ?? null,
                        'price_modifier' => $entry['price_modifier'] ?? null,
                    ]
                );
            }

        }


      //  dd($record);
        return $record;
    }
}
