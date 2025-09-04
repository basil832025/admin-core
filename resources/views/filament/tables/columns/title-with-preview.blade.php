@php
    /** @var \App\Models\Shop\Product $record */
    $record = $getRecord();

    // Текст (short_name -> перевод -> исходное)
    $defaultLocale = \App\Models\Setting::value('default_language_code') ?: config('app.locale');
    $text = $record->short_name
        ?: (is_array($record->title) ? $record->getTranslation('title', $defaultLocale) : $record->title);

    // URL главной картинки (FileUpload->directory('products/main') на public-диске)
    $url = $record->main_image
        ? (str_starts_with($record->main_image, 'http')
            ? $record->main_image
            : \Illuminate\Support\Facades\Storage::disk('public')->url($record->main_image))
        : null;
@endphp

<div
    x-data="{ show:false, x:0, y:0 }"
    @mouseenter="show = true"
    @mouseleave="show = false"
    @mousemove="x = $event.clientX; y = $event.clientY"
    class="inline-flex items-center gap-1"
>
    <span class="truncate max-w-[28rem] align-middle">{{ $text }}</span>

    @if ($url)
        {{-- Телепортируем превью в body, чтобы не мешали overflow родителей --}}
        <template x-teleport="body">
            <img
                x-cloak
                x-show="show"
                x-transition.opacity.scale.95
                :style="'position:fixed; left:'+(x+12)+'px; top:'+(y+12)+'px'"
                src="{{ $url }}"
                alt=""
                width="300px"
                class="w-56 h-56 object-cover rounded-xl shadow-xl ring-1 ring-black/10 bg-white z-[9999] pointer-events-none"
                loading="lazy"
            />
        </template>
    @endif
</div>
