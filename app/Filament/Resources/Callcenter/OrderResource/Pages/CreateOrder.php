<?php

namespace App\Filament\Resources\Callcenter\OrderResource\Pages;

use App\Filament\Resources\Callcenter\OrderResource\Concerns\HasHistoryOrderActions;
use App\Filament\Resources\Callcenter\OrderResource\Concerns\HasMenuCatalogActions;
use App\Filament\Resources\Callcenter\OrderResource\Concerns\HasPromotionsActions;
use App\Filament\Resources\Callcenter\OrderResource;
use App\Enums\PaymentMethodEnum;
use App\Models\Shop\ClientAddress;
use App\Services\OrderPricing;
use Filament\Notifications\Notification;
use Filament\Actions\Action;
use Filament\Resources\Pages\CreateRecord;
use Filament\Support\Exceptions\Halt;
use Illuminate\Support\Facades\Cache;
use Throwable;

class CreateOrder extends CreateRecord
{
    use HasHistoryOrderActions;
    use HasMenuCatalogActions;
    use HasPromotionsActions;

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

            $this->openPromotionsAction(),

            Action::make('createTop')
                ->label(__('order.actions.create'))
                ->color('primary')
                ->extraAttributes([
                    'data-hotkey' => 'cc-save',
                    'data-hotkey-label' => 'Alt+S',
                ])
                ->action('create'),
        ];
    }

    protected function getFormActions(): array
    {
        return [];
    }

    public function create(bool $another = false): void
    {
        if ($this->isCreating) {
            return;
        }

        $this->isCreating = true;

        $this->authorizeAccess();

        try {
            $this->beginDatabaseTransaction();

            $this->callHook('beforeValidate');

            $data = $this->form->getState();

            $this->callHook('afterValidate');

            $data = $this->mutateFormDataBeforeCreate($data);

            $this->callHook('beforeCreate');

            $this->record = $this->handleRecordCreation($data);

            $this->form->model($this->getRecord())->saveRelationships();

            $this->callHook('afterCreate');
        } catch (Halt $exception) {
            $exception->shouldRollbackDatabaseTransaction()
                ? $this->rollBackDatabaseTransaction()
                : $this->commitDatabaseTransaction();

            $this->isCreating = false;

            return;
        } catch (Throwable $exception) {
            $this->rollBackDatabaseTransaction();

            $this->isCreating = false;

            throw $exception;
        }

        $this->commitDatabaseTransaction();

        $this->rememberData();

        $this->getCreatedNotification()?->send();

        if ($another) {
            $this->form->model($this->getRecord()::class);
            $this->record = null;

            $this->fillForm();

            $this->isCreating = false;

            return;
        }

        $this->redirect($this->getRedirectUrl(), navigate: false);
    }

    protected function afterCreate(): void
    {
        $this->syncClientAddressCoordinatesFromOrder();

        // Apply promo selected in create form (UI-only preview before create).
        $selected = (string) data_get($this->data ?? [], 'ui_selected_promo', 'none');
        $selected = $selected !== '' ? $selected : 'none';
        if ($this->record?->exists) {
            try {
                if ($selected === 'none') {
                    app(OrderPricing::class)->recalc($this->record);
                } else {
                    [$kind, $id] = explode(':', $selected, 2) + [null, null];
                    $id = (int) $id;
                    if ($kind === 'time') {
                        app(OrderPricing::class)->applyTimeExclusive($this->record, $id > 0 ? $id : null, 'single');
                    } elseif ($kind === 'fixed') {
                        app(OrderPricing::class)->applyFixedExclusive($this->record, $id > 0 ? $id : null, 'single');
                    } else {
                        app(OrderPricing::class)->recalc($this->record);
                    }
                }
            } catch (\Throwable) {
                app(OrderPricing::class)->recalc($this->record);
            }

            $this->record->recalculateTotalPrice();
        }
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['currency'] = (string) ($data['currency'] ?? 'UAH');

        if (! $this->isCashalotFiscalPayment($data['payment'] ?? null)) {
            $data['fiscalize_in_cashalot'] = false;
        }

        if (! (bool) data_get($this->data, 'date_order_manually_changed', false) && ! empty($data['dat'])) {
            $data['date_order'] = $data['dat'];
        }

        if ((bool) ($data['self_pickup'] ?? false)) {
            $data['shipping_method'] = in_array((string) ($data['shipping_method'] ?? ''), ['pickup', 'bolt', 'glovo'], true)
                ? (string) $data['shipping_method']
                : 'pickup';
            $data['shipping_price'] = 0;
            $data['shipping_total'] = 0;
        } else {
            $data['shipping_method'] = 'delivery';
        }

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

    private function isCashalotFiscalPayment(mixed $payment): bool
    {
        $value = $payment instanceof PaymentMethodEnum ? $payment->value : (int) $payment;

        return in_array($value, [
            PaymentMethodEnum::CASH->value,
            PaymentMethodEnum::POS->value,
        ], true);
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
