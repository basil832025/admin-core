@php
    /** @var \Livewire\Component $this */
    $record = method_exists($this, 'getRecord') ? $this->getRecord() : null;
@endphp

@if ($record)
    @livewire(
    \App\Filament\Resources\Shop\OrderResource\Widgets\OrderActivityWidget::class,
    ['record' => $record],
    key('order-activity-'.$record->getKey())
    )
@else
    <x-filament::section :heading="__('order.journal.heading')">
        <div class="text-sm text-gray-500">{{ __('order.journal.empty_message') }}</div>
    </x-filament::section>
@endif
