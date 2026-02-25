@php
    $record = $getRecord();
    $label = $statusLabel ?? '—';
    $colors = is_array($statusColors ?? null) ? $statusColors : ['bg' => '#E5E7EB', 'text' => '#374151'];
    $bg = $colors['bg'] ?? '#E5E7EB';
    $text = $colors['text'] ?? '#374151';
    $style = "background: {$bg}; color: {$text};";
@endphp

<button
    type="button"
    class="group w-full text-left rounded-lg border border-slate-200 bg-white px-1.5 py-1 transition hover:border-primary-300 hover:bg-slate-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/40"
    wire:click.stop.prevent="mountTableAction('statuses', '{{ $record->getKey() }}')"
    x-on:click.stop.prevent
    x-on:mousedown.stop.prevent
    title="{{ __('order.actions.statuses') }}"
>
    <span
        class="inline-flex w-full items-center justify-between rounded-md px-2 py-1 text-xs font-semibold"
        style="<?php echo e($style); ?>"
    >
        <span class="truncate">{{ $label }}</span>
        <span class="ml-2 text-[10px] opacity-80">▼</span>
    </span>
</button>
