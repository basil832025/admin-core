@php
    use App\Enums\OrderStatus;

    $record = $record ?? null;
    $recordKey = $recordKey ?? $record?->getKey();
    $stage = $record?->stage;
    $stageEnum = $stage instanceof OrderStatus ? $stage : (filled($stage) ? OrderStatus::from($stage) : null);
    $orderKitchenNote = trim((string) ($record?->order?->kitchen_note ?? ''));
@endphp

@if ($recordKey)
    @if($orderKitchenNote !== '')
        <div class="mb-3 rounded-md" style="background:#fee2e2;color:#b91c1c;padding:8px 10px;">
            {{ $orderKitchenNote }}
        </div>
    @endif

    <div class="flex flex-wrap items-center gap-2">
        @if (in_array($stageEnum, [OrderStatus::Processing, OrderStatus::Filling], true))
            <x-filament::button
                type="button"
                size="sm"
                color="teal"
                icon="heroicon-m-beaker"
                wire:click.stop.prevent="replaceMountedTableAction('to_filling', '{{ $recordKey }}')"
            >
                {{ __('kitchen_ticket.actions.to_filling') }}
            </x-filament::button>
        @endif

        @if (in_array($stageEnum, [OrderStatus::Processing, OrderStatus::Filling, OrderStatus::Molding], true))
            <x-filament::button
                type="button"
                size="sm"
                color="indigo"
                icon="heroicon-m-puzzle-piece"
                wire:click.stop.prevent="replaceMountedTableAction('to_molding', '{{ $recordKey }}')"
            >
                {{ __('kitchen_ticket.actions.to_molding') }}
            </x-filament::button>
        @endif

        @if (in_array($stageEnum, [OrderStatus::Processing, OrderStatus::Filling, OrderStatus::Molding, OrderStatus::Baking], true))
            <x-filament::button
                type="button"
                size="sm"
                color="orange"
                icon="heroicon-m-fire"
                wire:click.stop.prevent="replaceMountedTableAction('to_baking', '{{ $recordKey }}')"
            >
                {{ __('kitchen_ticket.actions.to_baking') }}
            </x-filament::button>

            <x-filament::button
                type="button"
                size="sm"
                color="success"
                icon="heroicon-m-check"
                wire:click.stop.prevent="replaceMountedTableAction('to_prepared', '{{ $recordKey }}')"
            >
                {{ __('kitchen_ticket.actions.to_prepared') }}
            </x-filament::button>
        @endif
    </div>
@endif
