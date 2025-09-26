<!DOCTYPE html>
<html lang="uk">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Доставка осетинських пирогів')</title>
    @vite(['resources/css/app.css','resources/js/app.js'])
    <script defer src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('favicon.png') }}">
    <link rel="icon" type="image/png" sizes="16x16" href="{{ asset('favicon-16x16.png') }}">
    <link rel="apple-touch-icon" sizes="180x180" href="{{ asset('apple-touch-icon.png') }}">
</head>
<body class="antialiased  text-gray-900">

{{-- Header --}}
@include('partials.header')

<main class="container mx-auto mt-8 ">
    <x-menu-drawer event="open-mobile-menu" />
    @yield('content')
</main>

{{-- Footer --}}
@include('partials.footer')
<x-ui.scroll-top />
</body>
</html>
