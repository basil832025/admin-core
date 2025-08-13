<?php

namespace App\Filament\Clusters\Products\Resources\ProductResource\Pages;

use App\Filament\Clusters\Products\Resources\ProductResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;
use App\Models\Shop\Product;
use  App\Models\Shop\ProductImage;
class CreateProduct extends CreateRecord
{
    protected static string $resource = ProductResource::class;
 /*   protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }*/
    protected function handleRecordCreation(array $data): Product
    {
        $record = Product::create($data);

        foreach ($data['images'] ?? [] as $imagePath) {
            $record->images()->create([
                'path' => $imagePath,
            ]);
        }

        foreach ($data['characteristics'] ?? [] as $characteristicId => $value) {
            $values = is_array($value) ? $value : [$value];
            // dd($values);
            foreach ($values as $val) {
                //     dd($characteristicId, $value, $val);
                \App\Models\Shop\ProductCharacteristicValue::create([
                    'product_id' => $record->id,
                    'characteristic_id' => $characteristicId,
                    'characteristic_value_id' => $val,
                ]);
            }
        }


        return $record;
    }
}
