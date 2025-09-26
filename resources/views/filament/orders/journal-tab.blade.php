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
    <x-filament::section heading="Журнал операцій">
        <div class="text-sm text-gray-500">Журнал з’явиться після збереження замовлення.</div>
    </x-filament::section>
@endif
