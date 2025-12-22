{{-- resources/views/filament/tables/columns/characteristic-svg.blade.php --}}
@php
    /** @var \App\Models\Shop\Characteristic $record */
    $record = $getRecord();             // <-- ВАЖНО: вызываем замыкание
    $svg    = $record?->svgImage;       // связь belongsTo SvgImage
@endphp

@if($svg)
    @php
        $code = $svg->svg_normalized ?: $svg->svg_code;
        if (is_string($code)) {
            $code = preg_replace('/<svg\b(?![^>]*width=)/', '<svg width="20"', $code, 1);
            $code = preg_replace('/<svg\b(?![^>]*height=)/', '<svg height="20"', $code, 1);
        }
    @endphp
    <span class="inline-flex items-center justify-center w-6 h-6" style="color:#111827">
        {!! $code !!}
    </span>
@else
    <span class="text-gray-400">—</span>
@endif
