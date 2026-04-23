@props(['url'])

@php
    $slotContent = trim((string) $slot);
    $defaultAppName = trim((string) config('app.name'));
    $logoUrl = rtrim((string) config('app.url'), '/') . '/images/logo.svg';
    $logoAlt = st('header.logo_alt', 'Три пироги');
@endphp

<tr>
<td class="header">
<a href="{{ $url }}" style="display: inline-block;">
@if ($slotContent === '' || $slotContent === 'Laravel' || $slotContent === $defaultAppName)
<img src="{{ $logoUrl }}" class="logo" alt="{{ $logoAlt }}" style="max-height: 56px; width: auto;">
@else
{!! $slot !!}
@endif
</a>
</td>
</tr>
