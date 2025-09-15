<!DOCTYPE html>
<html lang="uk">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Доставка осетинських пирогів')</title>
    @vite(['resources/css/app.css','resources/js/app.js'])
    <script defer src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script>

</head>
<body class="antialiased  text-gray-900">

{{-- Header --}}
@include('partials.header')

<main class="container mx-auto mt-8 ">
    @yield('content')
</main>

{{-- Footer --}}
@include('partials.footer')

</body>
</html>
