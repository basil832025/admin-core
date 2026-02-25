<?php

namespace App\Filament\Resources\Shop\OrderResource\Pages;

use App\Models\Shop\ClientAddress;
use Filament\Actions\DeleteAction;
use App\Filament\Resources\Shop\OrderResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use App\Filament\Resources\Shop\OrderResource\Widgets\OrderActivityWidget;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use App\Enums\OrderStatus;
use App\Models\Shop\Order;
use App\Services\OrderPricing;
class EditOrder extends EditRecord
{
    public ?string $pendingStatus = null;
    public ?string $prevStatus    = null;

    protected static string $resource = OrderResource::class;

 /*   protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }*/
    protected function getHeaderActions(): array
    {
        return [
            $this->getCancelFormAction()
                ->label(__('order.actions.cancel'))
                ->color('warning')
                // можно задать куда вести, по умолчанию вернёт на index-роту
                ->url($this->getResource()::getUrl('index')),

            $this->getSaveFormAction()
                ->label(__('order.actions.save'))
                //   ->icon(Heroicons::class, 'outline-save') // указываем класс Filament\Icons\Heroicons
                ->formId('form'), // <-- должно совпадать с ID вашей формы
            DeleteAction::make()
                ->label(__('order.actions.delete')),
        ];
    }

    protected function getFormActions(): array
    {
        return [];
    }
    protected function getFooterWidgets(): array
    {
        return [
        //    OrderActivityWidget::class,   // покажется над формой
        ];
    }
    public function syncAddressOnSave(array $data): array
    {
        $addr   = $data['address'] ?? null;               // поля формы address.*
        $select = $data['selected_address_id'] ?? null;   // '-1' = новый адрес
        $clientId = $data['clients_id'] ?? $this->record->clients_id ?? null;

        if ($addr && $clientId) {
            if ((string) $select === '-1' || empty($select)) {
                // создать новый адрес
                $new = ClientAddress::create($addr + ['client_id' => $clientId]);
                $data['client_address_id'] = $new->id;
            } elseif (is_numeric($select)) {
                // обновить выбранный существующий
                if ($existing = ClientAddress::find((int) $select)) {
                    $existing->update($addr);
                    $data['client_address_id'] = $existing->id;
                }
            }
        }

        // эти поля не нужны для mass-assign на Order
        unset($data['address'], $data['selected_address_id']);

        return $data;
    }
    protected function mutateFormDataBeforeSave(array $data): array
    {
        return $this->syncAddressOnSave($data);
    }
 /*   protected function mutateFormDataBeforeFill(array $data): array
    {
        // гарантируем массив
        $data['address'] = is_array($data['address'] ?? null) ? $data['address'] : [];

        // гарантируем все ключи, чтобы entangle не падал
        $data['address'] += [
            'street_place_id'   => null,
            'street'            => null,
            'house'             => null,
            'apartment'         => null,
            'intercom'          => null,
            'floor'             => null,
            'entrance'          => null,
            'zip'               => null,
            'city'              => 'Київ',
            'country'           => null,
            'note'              => null,
            'type'              => null,
            'is_private_house'  => false,
            'latitude'          => null,
            'longitude'         => null,
            'formatted_address' => null,
        ];

        return $data;
    }*/
    protected function afterSave(): void
    {
        $this->record->recalculateTotalPrice();
        app(OrderPricing::class)->recalc($this->record);
    }

}
