@php
    /** @var \App\Models\Callcenter\Order|null $record */
@endphp

@if ($record)
    @livewire(
        \App\Filament\Resources\Callcenter\OrderResource\Widgets\OrderActivityWidget::class,
        ['record' => $record],
        key('callcenter-order-activity-'.$record->getKey())
    )
@else
    <x-filament::section :heading="__('order.journal.heading')">
        <div class="text-sm text-gray-500">{{ __('order.journal.empty_message') }}</div>
    </x-filament::section>
@endif
