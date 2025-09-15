<header class="bg-white px-[48px] pt-[12px] pb-[4px]">
    <div class="border-b border-black/10 pb-2 mx-auto w-[1343px] h-[76px]">
            <div class="flex items-center justify-between min-h-[68px]">
                {{-- ЛЕВЫЙ МИНИ-БЛОК: бургер + логотип + телефон (dropdown) + язык (dropdown) --}}
                <div class="flex items-center gap-8">
                    {{-- burger --}}
                    <button type="button"
                            class="inline-flex items-center justify-center w-10 h-10 rounded-lg hover:bg-gray-100"
                            aria-label="Меню">
                        <img src="{{ asset('images/menu.svg') }}" class="w-6 h-6" alt="">
                    </button>

                    {{-- logo --}}
                    <a href="{{ url('/') }}" class="flex items-center">
                        <img src="{{ asset('images/logo.svg') }}"  alt="Три пироги">
                    </a>

                    {{-- PHONE DROPDOWN --}}
                    @php
                        $activePhone = config('phones.default');
                        $phones = config('phones.list', []);
                        $telHref = fn($p) => 'tel:' . preg_replace('/[^\d+]/', '', $p);
                    @endphp

                    <details class="relative hidden md:block group">
                        <summary
                            class="inline-flex items-center gap-2.5 h-10 px-3 rounded-lg ring-1 ring-black/10 hover:bg-gray-50 cursor-pointer select-none
                     [&::-webkit-details-marker]:hidden">
                            <svg class="w-5 h-5 text-gray-700" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                                <path d="M6.6 10.8c1.9 3.6 4.9 6.5 8.5 8.5l2.8-2.8c.3-.3.7-.4 1.1-.3 1.2.4 2.4.6 3.7.6.6 0 1 .4 1 .9V22c0 .6-.4 1-1 1C10.1 23 1 13.9 1 2c0-.6.4-1 1-1h4.3c.6 0 .9.4.9 1 0 1.3.2 2.5.6 3.7.1.4 0 .8-.3 1.1L6.6 10.8z"/>
                            </svg>
                            <a href="{{ $telHref($activePhone) }}"
                               class="text-base leading-none font-medium text-gray-900 whitespace-nowrap hover:text-orange-600">
                                {{ $activePhone }}
                            </a>
                            <svg class="w-4 h-4 text-gray-600 transition-transform group-open:rotate-180" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                <path d="M5 7l5 5 5-5"/>
                            </svg>
                        </summary>

                        {{-- меню телефонов --}}
                        <div class="absolute left-0 top-[calc(100%+6px)] z-50 w-64 rounded-xl border bg-white shadow-md p-2">
                            <ul class="space-y-1">
                                @foreach($phones as $phone)
                                    <li>
                                        <a href="{{ $telHref($phone) }}"
                                           class="flex items-center justify-between gap-2 px-3 py-2 rounded-lg hover:bg-gray-50">
                                            <span class="text-sm">{{ $phone }}</span>
                                            @if($phone === $activePhone)
                                                <span class="text-xs text-orange-600">основний</span>
                                            @endif
                                        </a>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    </details>

                    {{-- LANGUAGE DROPDOWN --}}
                    @php
                        $locale = app()->getLocale();
                        $langs = ['uk' => 'UA', 'ru' => 'RU', 'en' => 'EN'];
                    @endphp

                    <details class="relative hidden md:block group">
                        <summary
                            class="inline-flex items-center gap-2 h-10 px-3 rounded-lg ring-1 ring-black/10 hover:bg-gray-50 cursor-pointer select-none
                     [&::-webkit-details-marker]:hidden">
                            <span class="font-medium">{{ $langs[$locale] ?? 'UA' }}</span>
                            <svg class="w-4 h-4 text-gray-600 transition-transform group-open:rotate-180" viewBox="0 0 20 20" fill="currentColor">
                                <path d="M5 7l5 5 5-5"/>
                            </svg>
                        </summary>

                        <div class="absolute left-0 top-[calc(100%+6px)] z-200 w-16 rounded-xl border bg-white shadow-md p-2">
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

                {{-- ЦЕНТР: слоган --}}
                <div class="hidden lg:flex items-center font-normal text-[13px] leading-4 text-[#19191A]">
    <span class="inline-flex items-center gap-2">
        <img src="{{ asset('images/fire.svg') }}" class="w-5 h-5 mx-auto" alt=""> Готуємо в дров’яній печі!
    </span>
                </div>

                {{-- ПРАВО: иконки --}}
                <div class="flex items-center gap-6">
                    {{-- Поиск --}}
                    <a href="#" class="w-5 h-5 flex items-center justify-center" aria-label="Пошук">
                        <img src="{{ asset('images/search.svg') }}" class="w-5 h-5" alt="">
                    </a>

                    {{-- Акції (иконка + текст, gap-1.5 ~ 6px) --}}
                    <a href="#" class="flex items-center gap-1.5 text-sm font-medium text-[#19191A] hover:text-orange-600">
                        <img src="{{ asset('images/percent.svg') }}" class="w-4 h-4" alt=""> Акції
                    </a>

                    {{-- Увійти (иконка + текст, gap-1.5 ~ 6px) --}}
                    <a href="#" class="flex items-center gap-1.5 text-sm font-medium text-[#19191A] hover:text-orange-600">
                        <img src="{{ asset('images/user.svg') }}" class="w-5 h-5" alt=""> Увійти
                    </a>

                    {{-- Вибране (иконка + бейджик) --}}
                    <a href="#" class="relative flex items-center justify-center w-5 h-5" aria-label="Вибране">
                        <img src="{{ asset('images/heart.svg') }}" class="w-5 h-5" alt="">
                        <span class="absolute -top-1 -right-2 bg-red-600 text-white text-[10px] leading-none rounded-full px-1">
            1
        </span>
                    </a>

                    {{-- Кошик (иконка + бейджик) --}}
                    <a href="#" class="relative flex items-center justify-center w-5 h-5" aria-label="Кошик">
                        <img src="{{ asset('images/cart.svg') }}" class="w-5 h-5" alt="">
                        <span class="absolute -top-1 -right-2 bg-red-600 text-white text-[10px] leading-none rounded-full px-1">
            0
        </span>
                    </a>
                </div>

            </div>

    </div>
    {{-- Меню вынесено отдельно и подключается здесь --}}
    @include('partials.menu')
</header>
