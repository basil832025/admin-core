@props(['url'])

@php
    $slotContent = trim((string) $slot);
    $defaultAppName = trim((string) config('app.name'));
    $project = (string) config('project.name', '3piroga');
    $theme = (string) config('project.theme', $project);
    $isSevia = in_array($project, ['duxi', 'sevia'], true) || in_array($theme, ['duxi', 'sevia'], true);
    $assetBaseUrl = rtrim((string) (config('app.asset_url') ?: config('liqpay.public_base_url') ?: config('app.url')), '/');
    $logoPath = $isSevia
        ? 'vendor/frontend-sevia/images/logo.png'
        : 'vendor/frontend-3piroga/images/logo.svg';
    $logoUrl = $assetBaseUrl . '/' . $logoPath;
    $logoAlt = $isSevia ? 'Sevia' : st('header.logo_alt', 'Три пироги');
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
