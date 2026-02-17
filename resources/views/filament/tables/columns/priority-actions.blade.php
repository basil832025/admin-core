@php
    use App\Enums\OrderStatus;
    use Filament\Support\Enums\ActionSize;

    $record = $getRecord();
    $recordKey = $recordKey ?? $record?->getKey();
    $stage = $record?->stage;
    $stageEnum = $stage instanceof OrderStatus
        ? $stage
        : (filled($stage) ? OrderStatus::from($stage) : null);
    $isPrepared = $stageEnum === OrderStatus::Prepared;
@endphp

@if ($recordKey === null)
    <span class="text-gray-400">—</span>
@elseif ($isPrepared)
    <span class="text-gray-400">—</span>
@else
    <div class="flex items-center justify-center gap-1 flex-nowrap">
        <div class="flex flex-col items-center gap-1">
            <x-filament::icon-button
                wire:click.stop.prevent="mountTableAction('priority_up', '{{ $recordKey }}')"
                icon="heroicon-m-arrow-up"
                color="gray"
                :size="ActionSize::Large"
                :tooltip="__('kitchen_ticket.actions.priority_up')"
            />

            <x-filament::icon-button
                wire:click.stop.prevent="mountTableAction('priority_down', '{{ $recordKey }}')"
                icon="heroicon-m-arrow-down"
                color="gray"
                :size="ActionSize::Large"
                :tooltip="__('kitchen_ticket.actions.priority_down')"
            />
        </div>

        <div class="flex items-center justify-center">
            <x-filament::icon-button
                wire:click.stop.prevent="mountTableAction('priority_set_urgent', '{{ $recordKey }}')"
                icon="heroicon-m-bolt"
                color="danger"
                :size="ActionSize::Large"
                :tooltip="__('kitchen_ticket.actions.priority_set_urgent')"
            />
        </div>

        <div class="flex flex-col items-center gap-1">
            <x-filament::icon-button
                wire:click.stop.prevent="mountTableAction('priority_set_normal', '{{ $recordKey }}')"
                icon="heroicon-m-minus"
                :color="[
                    300 => '#93C5FD',
                    400 => '#60A5FA',
                    500 => '#3B82F6',
                    600 => '#2563EB',
                ]"
                :size="ActionSize::Large"
                :tooltip="__('kitchen_ticket.actions.priority_set_normal')"
            />

            <x-filament::icon-button
                wire:click.stop.prevent="mountTableAction('priority_set_wait', '{{ $recordKey }}')"
                icon="heroicon-m-pause"
                :color="[
                    300 => '#FCD34D',
                    400 => '#FBBF24',
                    500 => '#F59E0B',
                    600 => '#D97706',
                ]"
                :size="ActionSize::Large"
                :tooltip="__('kitchen_ticket.actions.priority_set_wait')"
            />
        </div>
    </div>
@endif
