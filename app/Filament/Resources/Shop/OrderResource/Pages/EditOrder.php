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
use App\Services\OrderZoneSyncService;
class EditOrder extends EditRecord
{
    public ?string $pendingStatus = null;
    public ?string $prevStatus    = null;
    private bool $zoneNeedsRefresh = false;

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

    public function mount(int|string $record): void
    {
        parent::mount($record);

        if (! $this->record?->exists) {
            return;
        }

        app(OrderZoneSyncService::class)->syncIfNeeded($this->record, false);
        $this->record->refresh();
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
        $this->zoneNeedsRefresh = false;

        if ($addr && $clientId) {
            $addr = $this->normalizeAddressCoordinates($addr);

            if ((string) $select === '-1' || empty($select)) {
                // создать новый адрес
                $new = ClientAddress::create($addr + ['client_id' => $clientId]);
                $data['client_address_id'] = $new->id;
                $this->zoneNeedsRefresh = true;
            } elseif (is_numeric($select)) {
                // обновить выбранный существующий
                if ($existing = ClientAddress::find((int) $select)) {
                    $this->zoneNeedsRefresh = $this->hasAddressChanged($existing, $addr)
                        || (int) $this->record->client_address_id !== (int) $existing->id;
                    $existing->update($addr);
                    $data['client_address_id'] = $existing->id;
                }
            }
        }

        if ($this->zoneNeedsRefresh) {
            $data['delivery_zone_id'] = null;
            $data['zone_resolution_method'] = null;
            $data['zone_resolved_at'] = null;
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
        app(OrderZoneSyncService::class)->syncIfNeeded($this->record, $this->zoneNeedsRefresh);
        $this->record->refresh();
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

    protected function hasAddressChanged(ClientAddress $existing, array $newData): bool
    {
        $fields = [
            'street',
            'house',
            'apartment',
            'city',
            'latitude',
            'longitude',
            'street_place_id',
            'formatted_address',
        ];

        foreach ($fields as $field) {
            $old = $existing->getAttribute($field);
            $new = $newData[$field] ?? null;

            if (in_array($field, ['latitude', 'longitude'], true)) {
                $old = $old !== null && $old !== '' ? round((float) $old, 7) : null;
                $new = $new !== null && $new !== '' ? round((float) $new, 7) : null;
            } else {
                $old = is_string($old) ? trim($old) : $old;
                $new = is_string($new) ? trim($new) : $new;
            }

            if ($old !== $new) {
                return true;
            }
        }

        return false;
    }

}
