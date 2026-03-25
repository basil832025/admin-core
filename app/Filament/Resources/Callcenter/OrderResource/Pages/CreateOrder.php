<?php

namespace App\Filament\Resources\Callcenter\OrderResource\Pages;

use App\Filament\Resources\Callcenter\OrderResource\Concerns\HasHistoryOrderActions;
use App\Filament\Resources\Callcenter\OrderResource\Concerns\HasMenuCatalogActions;
use App\Filament\Resources\Callcenter\OrderResource;
use App\Models\Shop\ClientAddress;
use App\Services\OrderPricing;
use Filament\Notifications\Notification;
use Filament\Actions\Action;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Cache;

class CreateOrder extends CreateRecord
{
    use HasHistoryOrderActions;
    use HasMenuCatalogActions;

    protected static string $resource = OrderResource::class;

    public function mount(): void
    {
        parent::mount();
        $this->applyBinotelTokenPrefill();
    }

    protected function getHeaderActions(): array
    {
        return [
            $this->getCancelFormAction()
                ->label(__('order.actions.cancel'))
                ->color('warning')
                ->url($this->getResource()::getUrl('index')),

            $this->openMenuCatalogAction(),

            Action::make('createTop')
                ->label(__('order.actions.create'))
                ->color('primary')
                ->action('create'),
        ];
    }

    protected function getFormActions(): array
    {
        return [];
    }

    protected function afterCreate(): void
    {
        $this->syncClientAddressCoordinatesFromOrder();
        app(OrderPricing::class)->recalc($this->record);
        $this->record->recalculateTotalPrice();
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        if (isset($data['items']) && is_array($data['items'])) {
            $data['items'] = array_values(array_filter($data['items'], function ($item): bool {
                $row = is_array($item) ? $item : (array) $item;

                return (int) ($row['product_id'] ?? 0) > 0
                    && (int) ($row['qty'] ?? 0) > 0;
            }));
        }

        $addr = $data['address'] ?? null;
        $select = $data['selected_address_id'] ?? null;
        $clientId = $data['clients_id'] ?? null;

        if ($addr && $clientId) {
            $addr = $this->normalizeAddressCoordinates($addr);

            if ((string) $select === '-1' || empty($select)) {
                $new = ClientAddress::create($addr + ['client_id' => $clientId]);
                $data['client_address_id'] = $new->id;
            } elseif (is_numeric($select)) {
                if ($existing = ClientAddress::find((int) $select)) {
                    $existing->update($addr);
                    $data['client_address_id'] = $existing->id;
                }
            }
        }

        unset($data['address'], $data['selected_address_id']);
        unset($data['id'], $data['number']);

        return $data;
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

    protected function applyBinotelTokenPrefill(): void
    {
        $token = trim((string) request()->query('bt', ''));
        if ($token === '') {
            return;
        }

        $payload = Cache::get('binotel_bt:' . $token);
        if (! is_array($payload)) {
            Notification::make()
                ->warning()
                ->title('Дані дзвінка не знайдено')
                ->body('Токен Binotel невалідний або прострочений.')
                ->send();
            return;
        }

        $clientId = isset($payload['client_id']) ? (int) $payload['client_id'] : 0;
        $sourceId = isset($payload['source_id']) ? (int) $payload['source_id'] : 0;

        $baseState = [
            'incoming_phone' => (string) ($payload['phone'] ?? ''),
            'client_phone_view' => (string) ($payload['phone'] ?? ''),
            'source_id' => $sourceId > 0 ? $sourceId : null,
        ];

        if ($clientId > 0) {
            $state = array_merge($this->data ?? [], $baseState, [
                'clients_id' => $clientId,
            ]);

            $this->form->fill($state);

            Notification::make()
                ->success()
                ->title('Знайдено клієнта')
                ->body(($payload['client_name'] ?? 'Клієнт') . ' · ' . ($payload['phone'] ?? ''))
                ->send();

            return;
        }

        Notification::make()
            ->info()
            ->title('Клієнта не знайдено')
            ->body('Номер: ' . ($payload['phone'] ?? '—') . '. Створіть клієнта та замовлення вручну.')
            ->send();

        $state = array_merge($this->data ?? [], $baseState, [
            'clients_id' => null,
        ]);

        $this->form->fill($state);
    }
}
