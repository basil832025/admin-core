@php
    $record = $getRecord();
    $path = $record->main_image_small ?: $record->main_image;
    $url = $path
        ? (preg_match('~^https?://~i', $path)
            ? $path
            : \Illuminate\Support\Facades\Storage::disk('public')->url($path))
        : null;
    $mainPath = $record->main_image;
    $fallbackUrl = $mainPath && $mainPath !== $path
        ? (preg_match('~^https?://~i', $mainPath)
            ? $mainPath
            : \Illuminate\Support\Facades\Storage::disk('public')->url($mainPath))
        : null;
@endphp

<div x-data="{ failed: @js(!$url), triedFallback: false }" style="width:52px;height:52px;border-radius:8px;overflow:hidden;background:#f1f5f9;display:flex;align-items:center;justify-content:center;color:#94a3b8">
    @if($url)
        <img src="{{ $url }}" alt="" loading="lazy" width="52" height="52" x-show="!failed"
             x-on:error="if (!triedFallback && @js($fallbackUrl)) { triedFallback = true; $el.src = @js($fallbackUrl); } else { failed = true; }"
             style="width:100%;height:100%;object-fit:cover" />
    @endif
    <svg x-show="failed" @if($url) x-cloak @endif width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" role="img" aria-label="Фото відсутнє">
        <rect x="3" y="3" width="18" height="18" rx="3" />
        <circle cx="8" cy="8" r="1.5" />
        <path d="m3 17 5-5 4 4 3-3 6 6" stroke-linecap="round" stroke-linejoin="round" />
    </svg>
</div>
