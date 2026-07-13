<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', config('app.name', 'MyAdmin'))</title>
    @stack('head')
</head>
<body class="antialiased text-gray-900">
    <main>
        @yield('content')
    </main>
    @stack('scripts')
</body>
</html>
