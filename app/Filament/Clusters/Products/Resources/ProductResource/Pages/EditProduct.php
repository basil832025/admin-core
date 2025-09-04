<?php

namespace App\Filament\Clusters\Products\Resources\ProductResource\Pages;

use Filament\Actions\DeleteAction;
use App\Filament\Clusters\Products\Resources\ProductResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use App\Models\Shop\Product;
use  App\Models\Shop\ProductImage;
class EditProduct extends EditRecord
{
    protected static string $resource = ProductResource::class;

    protected function getHeaderActions(): array
    {
        return [
            $this->getCancelFormAction()
                ->label('Отмена')
                ->color('warning')
                // можно задать куда вести, по умолчанию вернёт на index-роту
                ->url($this->getResource()::getUrl('index')),

            $this->getSaveFormAction()
                ->label('Сохранить')
                //   ->icon(Heroicons::class, 'outline-save') // указываем класс Filament\Icons\Heroicons
                ->formId('form'),
            DeleteAction::make(),
        ];
    }
    protected function mutateFormDataBeforeSave(array $data): array
    {
       /* $data['characteristics'] = $data['characteristics_payload'] ?? [];
        unset($data['characteristics_payload']);*/

        return $data;
    }
    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
    protected function mutateFormDataBeforeFill(array $data): array
    {
            $this->record->loadMissing('characteristicValues');
      //  dd($data);
        return $data;
    }
    protected function beforeValidate(): void
    {
        // Сырое состояние без правил/кастов
       // dd( $this->form->getRawState()        );
    }
    // после сохранения Работа с доп. логикой (связи, логи и т.п.)

    protected function handleRecordUpdate($record, array $data): Product
    {
        $record->update($data);
        $record->syncFromFormState($data);
        return $record;
    }
    // до сохранения Кастомное обновление модели
   /* protected function afterSave(): void
    {
        $record = $this->record;

        $chars  = (array) data_get($this->form->getState(), 'characteristics', []);
        $prices = (array) data_get($this->form->getState(), 'characteristics_price', []);

        $target = collect($chars)->flatMap(function ($vals, $charId) use ($prices, $record) {
            return collect((array) $vals)
                // берём только отмеченные и только ЧИСЛОВЫЕ ключи
                ->filter(function ($checked, $valueId) {
                    return (bool) $checked && is_numeric($valueId) && (int)$valueId > 0;
                })
                ->map(function ($checked, $valueId) use ($charId, $prices, $record) {
                    $valId = (int) $valueId;

                    $priceRaw = data_get($prices, "{$charId}.{$valId}");
                    $price    = is_numeric($priceRaw) ? (float) $priceRaw : null;

                    return [
                        'product_id'              => (int) $record->id,
                        'characteristic_id'       => (int) $charId,
                        'characteristic_value_id' => $valId,
                        'price_modifier'          => $price,
                        'created_at'              => now(),
                        'updated_at'              => now(),
                    ];
                })
                ->values();
        })->values();

        // удалить снятые
        $existing = \DB::table('product_characteristic_value')
            ->where('product_id', $record->id)
            ->get(['characteristic_id','characteristic_value_id']);

        $toDelete = $existing->reject(function ($row) use ($target) {
            return $target->contains(fn ($t) =>
                $t['characteristic_id'] === (int)$row->characteristic_id &&
                $t['characteristic_value_id'] === (int)$row->characteristic_value_id
            );
        });

        if ($toDelete->isNotEmpty()) {
            \DB::table('product_characteristic_value')
                ->where('product_id', $record->id)
                ->where(function ($q) use ($toDelete) {
                    foreach ($toDelete as $row) {
                        $q->orWhere(function ($q2) use ($row) {
                            $q2->where('characteristic_id', $row->characteristic_id)
                                ->where('characteristic_value_id', $row->characteristic_value_id);
                        });
                    }
                })
                ->delete();
        }

        if ($target->isNotEmpty()) {
            \DB::table('product_characteristic_value')->upsert(
                $target->all(),
                ['product_id', 'characteristic_id', 'characteristic_value_id'],
                ['price_modifier', 'updated_at']
            );
        }
    }*/


}
