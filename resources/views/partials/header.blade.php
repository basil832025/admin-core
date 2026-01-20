<header id="site-header" class="sticky top-0 z-[60] w-full bg-white">
    <div class="mx-auto w-full desk:w-[1343px] px-4 md:px-6">
    <div
        x-data="{}"
        @keydown.escape.window="$store.search.open = false"
        x-effect="document.body.classList.toggle('overflow-hidden', $store.search.open && window.matchMedia('(max-width: 1023px)').matches)"
        class="border-b border-black/10 pb-2 mx-auto desk:w-[1343px] desk:h-[76px] px-6 md:h-16"
    >
        <div class="flex items-center justify-between min-h-[68px]" @click.outside="$store.search.open = false">
            {{-- ЛЕВЫЙ МИНИ-БЛОК: бургер + логотип + телефон + язык --}}
            <div class="flex items-center md:gap-5 desk:gap-8 gap-4">
                {{-- burger --}}
                <button type="button"
                        x-data
                        @click="$dispatch('open-mobile-menu')"
                        class="inline-flex items-center justify-center w-6 h-10 rounded-lg hover:bg-gray-100"
                        aria-label="Меню">
                    <img src="{{ asset('images/menu.svg') }}" class="w-6 h-6" alt="">
                </button>

                {{-- logo --}}
                <a href="{{ url('/') }}" class="block md:gap-2 desk:gap-6" aria-label="Три Пироги — на главную">
                    <picture>
                        <source media="(min-width: 1250px)" srcset="{{ asset('images/logo.svg') }}">
                        <source media="(min-width: 768px)" srcset="{{ asset('images/logo_m.svg') }}">
                        <img src="{{ asset('images/logo_mob.svg') }}" alt="Три Пироги" decoding="async"
                             class="shrink-0 flex-none
           basis-[57px] max-w-none object-contain" fetchpriority="high">
                    </picture>
                </a>

                {{-- PHONE DROPDOWN --}}
                @php
                    $activePhone = $headerPhonePrimary['display'] ?? config('phones.default');
                    $phones =  $headerPhones ?? config('phones.list', []);
                    $telHref = fn($p) => 'tel:' . preg_replace('/[^\d+]/', '', $p);
                @endphp
                <details class="relative hidden md:block group">
                    <summary
                        class="inline-flex items-center gap-2.5 h-10 px-3 rounded-lg ring-1 ring-black/10 hover:bg-gray-50 cursor-pointer select-none
                        [&::-webkit-details-marker]:hidden">
                        <svg class="hidden lg:block w-5 h-5 text-gray-700" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M6.6 10.8c1.9 3.6 4.9 6.5 8.5 8.5l2.8-2.8c.3-.3.7-.4 1.1-.3 1.2.4 2.4.6 3.7.6.6 0 1 .4 1 .9V22c0 .6-.4 1-1 1C10.1 23 1 13.9 1 2c0-.6.4-1 1-1h4.3c.6 0 .9.4.9 1 0 1.3.2 2.5.6 3.7.1.4 0 .8-.3 1.1L6.6 10.8z"/></svg>
                        <a href="{{ $telHref($activePhone) }}"
                           class="text-sm leading-none font-medium text-gray-900 whitespace-nowrap hover:text-orange-600">
                            {{ $activePhone }}
                        </a>
                        <svg width="14" height="8" class="w-4 h-4 text-gray-600 transition-transform group-open:rotate-180" viewBox="0 0 14 8" aria-hidden="true" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M1.30232 1.5L5.89549 6.09317C6.43793 6.63561 7.32557 6.63561 7.86801 6.09317L12.4612 1.5" stroke="#19191A" stroke-width="1.5" stroke-miterlimit="10" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                    </summary>
                    <div class="absolute left-0 top-[calc(100%+6px)] z-50 w-64 rounded-xl border bg-white shadow-md p-2">
                        <ul class="space-y-1">
                            @foreach($phones as $phone)
                                <li>
                                    <a href="{{ $telHref($phone['display'] ?? $phone['tel'] ?? '') }}" class="flex items-center justify-between gap-2 px-3 py-2 rounded-lg hover:bg-gray-50">
                                        <span class="text-sm">{{ $phone['display'] }}</span>
                                        @if($headerPhonePrimary && ($phone['tel'] ?? '') === ($headerPhonePrimary['tel'] ?? ''))
                                            <span class="text-[#FF7500] text-xs">{{ st('header.phone.main','основний') }}</span>
                                        @endif
                                    </a>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                </details>

                {{-- LANGUAGE DROPDOWN --}}
                @php $locale = app()->getLocale(); $langs = ['uk'=>'UA','ru'=>'RU','en'=>'EN']; @endphp
                <details class="relative hidden md:block group">
                    <summary
                        class="inline-flex items-center gap-2 h-10 px-3 rounded-lg ring-1 ring-black/10 hover:bg-gray-50 cursor-pointer select-none
                        [&::-webkit-details-marker]:hidden">
                        <span class="font-medium text-sm">{{ $langs[$locale] ?? 'UA' }}</span>
                        <svg width="14" height="8" class="w-4 h-4 text-gray-600 transition-transform group-open:rotate-180" viewBox="0 0 14 8" aria-hidden="true" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M1.30232 1.5L5.89549 6.09317C6.43793 6.63561 7.32557 6.63561 7.86801 6.09317L12.4612 1.5" stroke="#19191A" stroke-width="1.5" stroke-miterlimit="10" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                    </summary>
                    <div class="absolute left-0 top-[calc(100%+6px)] z-50 w-16 rounded-xl border bg-white shadow-md p-2">
                        <ul class="text-sm">
                            @foreach($langs as $code => $label)
                                <li>
                                    <a href="{{ route('lang.switch', $code) }}"
                                       class="flex items-center justify-between px-3 py-2 rounded-lg hover:bg-gray-50 @if($locale===$code) text-orange-600 font-medium @endif"
                                       @if($locale===$code) aria-current="true" @endif>
                                        {{ $label }}
                                    </a>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                </details>
            </div>

            {{-- ЦЕНТР: слоган / поиск --}}
            <div class="flex desk:flex-1 justify-between w-full">
                {{-- Слоган (desktop) --}}
                <div
                    x-show="!$store.search.open"
                    x-transition.opacity
                    class="hidden lg:flex items-center ml-[100px] font-normal text-[13px] leading-4 text-[#19191A]"
                >
                    <span class="inline-flex items-center gap-2">
                        <img src="{{ asset('images/fire.svg') }}" class="w-5 h-5 mx-auto" alt="">
                        {{ st('header.wood-fired','Готуємо в дров’яній печі!') }}
                    </span>
                </div>

                {{-- Поиск как компонент --}}
                <x-search.header :action="route('search')" :suggest="route('search.suggest')" placeholder="Я шукаю..." maxWidth="450px" />
            </div>

            {{-- ПРАВО: иконки --}}
            <div class="flex items-center gap-[20px] shrink-0">
                {{-- Поиск (mobile) --}}
                <button
                    type="button"
                    class="relative inline-flex items-center justify-center w-5 h-5 shrink-0 lg:hidden"
                    aria-label="Пошук"
                    @click.stop="$store.search.open = true"
                >
                    <img src="{{ asset('images/search.svg') }}" class="w-5 h-5 shrink-0 flex-none" width="20" height="20" alt="">
                </button>

                {{-- Поиск (desktop) --}}
                <button
                    type="button"
                    class="relative hidden lg:inline-flex items-center justify-center w-5 h-5 shrink-0"
                    aria-label="Пошук"
                    @click.stop="$store.search.open = !$store.search.open"
                >
                    <img src="{{ asset('images/search.svg') }}" class="w-5 h-5 shrink-0 flex-none" width="20" height="20" alt="">
                </button>

                {{-- Акції --}}
                <a
                    href="/discounts"
                    class="inline-flex items-center gap-2 text-sm leading-none font-medium text-[#19191A] hover:text-orange-600 shrink-0"
                >
                    <img src="{{ asset('images/percent.svg') }}" class="w-5 h-5 shrink-0 flex-none" width="20" height="20" alt="">
                    <span class="hidden lg:inline whitespace-nowrap">{{ st('header.promotions','Акції') }}</span>
                </a>

                {{-- Увійти --}}

                    @include('partials.header-auth')


                {{-- Обране --}}
                <a
                    href="{{ route('favorites.index') }}"
                    class="relative inline-flex items-center justify-center w-5 h-5 shrink-0"
                    aria-label="Обране"
                >
                    <img src="{{ asset('images/heart.svg') }}" class="w-5 h-5 shrink-0 flex-none" width="20" height="20" alt="">
                    <span
                        x-cloak
                        x-show="$store.favorites && ($store.favorites.qty > 0)"
                        x-text="$store.favorites ? $store.favorites.qty : 0"
                        class="absolute -top-1 -right-2 bg-red-600 text-white text-[10px] leading-none rounded-full px-1 min-w-[16px] text-center"
                    >0</span>
                </a>

                {{-- Кошик --}}
                <div class="shrink-0">
                    @include('partials.header-cart')
                </div>
            </div>


        </div>
    </div>
    </div>


</header>
{{-- Меню --}}
@include('partials.menu')
