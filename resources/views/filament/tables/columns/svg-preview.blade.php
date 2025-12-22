@php
    $svg = trim($getRecord()->svg_code ?? '');

    // убираем XML-пролог и DOCTYPE (они мешают инлайн-вставке)
    $svg = preg_replace('/<\?xml[^>]*\?>/i', '', $svg);
    $svg = preg_replace('/<!DOCTYPE[^>]*>/i', '', $svg);

    $isSvg = $svg !== '' && \Illuminate\Support\Str::startsWith(ltrim($svg), '<svg');
@endphp

@if ($isSvg)
    <div class="w-12 h-12 overflow-hidden flex items-center justify-center [&_svg]:w-full [&_svg]:h-full">
        {!! $svg !!}
    </div>
@elseif($getRecord()->file_path)
    <img src="{{ asset($getRecord()->file_path) }}" alt="" width="48" height="48">
@else
    <span class="text-gray-400">—</span>
@endif
