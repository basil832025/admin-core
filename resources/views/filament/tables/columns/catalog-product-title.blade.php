@php
    $record = $getRecord();
    $locale = \App\Models\Setting::value('default_language_code') ?: config('app.locale');
    $text = $record->short_name ?: $record->getTranslation('title', $locale);
@endphp

<div title="{{ $text }}" style="width:clamp(220px,30vw,460px);max-width:100%;white-space:normal">
    <span style="display:-webkit-box;-webkit-box-orient:vertical;-webkit-line-clamp:2;overflow:hidden;overflow-wrap:anywhere;line-height:1.4">{{ $text }}</span>
</div>
