<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <script>
        // Обновляем CSRF токен при загрузке страницы
        document.addEventListener('DOMContentLoaded', function() {
            const metaToken = document.querySelector('meta[name="csrf-token"]');
            if (metaToken) {
                const newToken = metaToken.getAttribute('content');
                if (newToken) {
                    document.querySelectorAll('input[name="_token"]').forEach(input => {
                        input.value = newToken;
                    });
                }
            }
        });

        // Периодически обновляем CSRF токен (каждые 30 минут)
        setInterval(function() {
            fetch('/csrf-token', {
                method: 'GET',
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                credentials: 'same-origin'
            })
            .then(response => response.json())
            .then(data => {
                if (data.token) {
                    const metaToken = document.querySelector('meta[name="csrf-token"]');
                    if (metaToken) {
                        metaToken.setAttribute('content', data.token);
                    }
                    document.querySelectorAll('input[name="_token"]').forEach(input => {
                        input.value = data.token;
                    });
                }
            })
            .catch(() => {
                // Игнорируем ошибки при обновлении токена
            });
        }, 30 * 60 * 1000); // 30 минут

        window.isGuestCheckout = {{ auth()->check() ? 'false' : 'true' }};

        // Защита от ошибок с undefined key в обработчиках клавиатуры
        // Обертываем обработчики событий перед инициализацией Alpine.js
        const originalAddEventListener = EventTarget.prototype.addEventListener;
        EventTarget.prototype.addEventListener = function(type, listener, options) {
            if (type === 'keydown' || type === 'keyup' || type === 'keypress') {
                const wrappedListener = function(e) {
                    // Если event.key undefined - создаем безопасный объект
                    if (!e || !('key' in e)) {
                        Object.defineProperty(e, 'key', {
                            value: '',
                            writable: false,
                            enumerable: true,
                            configurable: false
                        });
                    }
                    try {
                        return listener.call(this, e);
                    } catch (err) {
                        // Игнорируем ошибки, связанные с undefined key
                        if (err.message && err.message.includes('length')) {
                            return;
                        }
                        throw err;
                    }
                };
                return originalAddEventListener.call(this, type, wrappedListener, options);
            }
            return originalAddEventListener.call(this, type, listener, options);
        };
    </script>
    <title>@yield('title', 'Доставка осетинських пирогів')</title>

    @php
        // SEO: canonical + hreflang for uk (no prefix), ru/en (prefixed)
        $locale = app()->getLocale();
        $host = request()->getSchemeAndHttpHost();

        $path = (string) request()->getPathInfo();
        $normalizedPath = preg_replace('#^/(ru|en)(?=/|$)#i', '', $path);
        $normalizedPath = is_string($normalizedPath) && $normalizedPath !== '' ? $normalizedPath : '/';

        $ukUrl = $host . $normalizedPath;
        $ruUrl = $host . '/ru' . ($normalizedPath === '/' ? '' : $normalizedPath);
        $enUrl = $host . '/en' . ($normalizedPath === '/' ? '' : $normalizedPath);

        $canonicalUrl = match ($locale) {
            'ru' => $ruUrl,
            'en' => $enUrl,
            default => $ukUrl,
        };
    @endphp

    <link rel="canonical" href="{{ $canonicalUrl }}" />
    <link rel="alternate" hreflang="uk" href="{{ $ukUrl }}" />
    <link rel="alternate" hreflang="ru" href="{{ $ruUrl }}" />
    <link rel="alternate" hreflang="en" href="{{ $enUrl }}" />
    <link rel="alternate" hreflang="x-default" href="{{ $ukUrl }}" />

    <meta name="description" content="@yield('meta_description', '')">
    @hasSection('meta_keywords')
        <meta name="keywords" content="@yield('meta_keywords')">
    @endif
    <meta name="robots" content="@yield('meta_robots', 'index, follow')">

    <meta property="og:type" content="@yield('og_type', 'website')">
    <meta property="og:title" content="@yield('og_title', trim($__env->yieldContent('title', '')))">
    <meta property="og:description" content="@yield('og_description', trim($__env->yieldContent('meta_description', '')))">
    <meta property="og:url" content="{{ $canonicalUrl }}">
    @hasSection('og_image')
        <meta property="og:image" content="@yield('og_image')">
    @endif

    <meta name="twitter:card" content="@yield('twitter_card', 'summary_large_image')">
    <meta name="twitter:title" content="@yield('twitter_title', trim($__env->yieldContent('og_title', '')))">
    <meta name="twitter:description" content="@yield('twitter_description', trim($__env->yieldContent('og_description', '')))">
    @hasSection('twitter_image')
        <meta name="twitter:image" content="@yield('twitter_image')">
    @else
        @if($__env->yieldContent('og_image', '') !== '')
            <meta name="twitter:image" content="@yield('og_image')">
        @endif
    @endif

    @stack('head')

    @vite(['resources/css/app.css','resources/js/app.js'])
    <script>
        // Глобальная переменная для Google Maps API ключа
        window.GOOGLE_MAPS_API_KEY = '{{ config("services.google_maps.key") }}';
        window.APP_LOCALE = '{{ app()->getLocale() }}';
    </script>

    <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('favicon.png') }}">
    <link rel="icon" type="image/png" sizes="16x16" href="{{ asset('favicon-16x16.png') }}">
    <link rel="apple-touch-icon" sizes="180x180" href="{{ asset('apple-touch-icon.png') }}">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Pacifico&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.css" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <!-- локали -->
    <script src="https://cdn.jsdelivr.net/npm/flatpickr/dist/l10n/ru.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr/dist/l10n/uk.js"></script>
    <!-- Google Tag Manager -->
    <script>(function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':
    new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],
    j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src=
    'https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);
    })(window,document,'script','dataLayer','GTM-5R7VLG9');</script>
    <!-- End Google Tag Manager -->
</head>
<body data-page="@yield('page','')" class="antialiased text-gray-900 overflow-x-hidden">
<!-- Google Tag Manager (noscript) -->
<noscript><iframe src="https://www.googletagmanager.com/ns.html?id=GTM-5R7VLG9"
height="0" width="0" style="display:none;visibility:hidden"></iframe></noscript>
<!-- End Google Tag Manager (noscript) -->
@include('components.auth.modal')
{{-- Header --}}
@include('partials.header')

<main class="container mx-auto mt-4 ">
    <x-menu-drawer event="open-mobile-menu" />
    @yield('content')
</main>

{{-- Footer --}}
@include('partials.footer')
<x-ui.scroll-top />
<script src="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.js"></script>
@stack('scripts')

</body>
</html>
