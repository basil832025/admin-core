@php
    use App\Enums\OrderStatus;
    use App\Models\Kitchen\KitchenTicket;

    $recordKey = $recordKey ?? null;
    $ticket = $recordKey ? KitchenTicket::query()->find($recordKey) : null;
    $stage = $ticket?->stage;
    $stageEnum = $stage instanceof OrderStatus
        ? $stage
        : (filled($stage) ? OrderStatus::from($stage) : null);

    $canFilling = in_array($stageEnum, [
        OrderStatus::Processing, OrderStatus::Filling,
    ], true);
    $canMolding = in_array($stageEnum, [
        OrderStatus::Processing, OrderStatus::Filling, OrderStatus::Molding,
    ], true);
    $canBaking = in_array($stageEnum, [
        OrderStatus::Processing, OrderStatus::Filling, OrderStatus::Molding, OrderStatus::Baking,
    ], true);
    $canPrepared = in_array($stageEnum, [
        OrderStatus::Processing, OrderStatus::Filling, OrderStatus::Molding, OrderStatus::Baking,
    ], true);
@endphp

<div class="flex flex-wrap items-center gap-2">
    @if ($recordKey)
        @if ($canFilling ?? false)
            <x-filament::button
                type="button"
                color="teal"
                icon="heroicon-m-beaker"
                wire:click.stop.prevent="moveTicketStage('{{ $recordKey }}', 'filling')"
            >
                {{ __('kitchen_ticket.actions.to_filling') }}
            </x-filament::button>
        @endif

        @if ($canMolding ?? false)
            <x-filament::button
                type="button"
                color="indigo"
                icon="heroicon-m-puzzle-piece"
                wire:click.stop.prevent="moveTicketStage('{{ $recordKey }}', 'molding')"
            >
                {{ __('kitchen_ticket.actions.to_molding') }}
            </x-filament::button>
        @endif

        @if ($canBaking ?? false)
            <x-filament::button
                type="button"
                color="orange"
                icon="heroicon-m-fire"
                wire:click.stop.prevent="moveTicketStage('{{ $recordKey }}', 'baking')"
            >
                {{ __('kitchen_ticket.actions.to_baking') }}
            </x-filament::button>
        @endif

        @if ($canPrepared ?? false)
            <x-filament::button
                type="button"
                color="success"
                icon="heroicon-m-check"
                x-on:click.stop.prevent="if (confirm(@js(__('kitchen_ticket.modals.confirm_prepared_description')))) { $wire.moveTicketStage('{{ $recordKey }}', 'prepared') }"
            >
                {{ __('kitchen_ticket.actions.to_prepared') }}
            </x-filament::button>
        @endif
    @endif

    <div class="ms-auto flex items-center gap-2">
        <x-filament::button type="submit" color="primary">
            {{ __('kitchen_ticket.actions.save') }}
        </x-filament::button>

        <x-filament::button
            type="button"
            color="gray"
            wire:click.stop.prevent="unmountTableAction()"
        >
            Закрити
        </x-filament::button>
    </div>
</div>
