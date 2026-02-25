<?php

namespace App\Filament\Resources\Callcenter\OrderResource\Pages;

use App\Filament\Resources\Callcenter\OrderResource\Concerns\HasHistoryOrderActions;
use App\Filament\Resources\Callcenter\OrderResource\Concerns\HasMenuCatalogActions;
use App\Filament\Resources\Callcenter\OrderResource;
use App\Models\Shop\ClientAddress;
use App\Services\OrderPricing;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditOrder extends EditRecord
{
    use HasHistoryOrderActions;
    use HasMenuCatalogActions;

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
                ->url($this->getResource()::getUrl('index')),

            $this->openMenuCatalogAction(),

            $this->getSaveFormAction()
                ->label(__('order.actions.save'))
                ->formId('form'),
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
            $addr = $this->normalizeAddressCoordinates($addr);

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
        $this->syncClientAddressCoordinatesFromOrder();
        $this->record->recalculateTotalPrice();
        app(OrderPricing::class)->recalc($this->record);
    }

    protected function normalizeAddressCoordinates(array $address): array
    {
        foreach (['latitude', 'longitude'] as $key) {
            $raw = $address[$key] ?? null;

            if ($raw === null || $raw === '') {
                continue;
            }

            $normalized = (float) str_replace(',', '.', (string) $raw);
            $address[$key] = $normalized !== 0.0 ? $normalized : $raw;
        }

        return $address;
    }

    protected function syncClientAddressCoordinatesFromOrder(): void
    {
        if (! $this->record?->client_address_id) {
            return;
        }

        $orderAddress = (array) ($this->record->address ?? []);
        $lat = $orderAddress['latitude'] ?? null;
        $lng = $orderAddress['longitude'] ?? null;

        if (! $lat || ! $lng) {
            return;
        }

        $clientAddress = ClientAddress::find($this->record->client_address_id);

        if (! $clientAddress) {
            return;
        }

        $clientAddress->update([
            'latitude' => (float) $lat,
            'longitude' => (float) $lng,
            'formatted_address' => $orderAddress['formatted_address'] ?? $clientAddress->formatted_address,
            'street_place_id' => $orderAddress['street_place_id'] ?? $clientAddress->street_place_id,
        ]);
    }

}
