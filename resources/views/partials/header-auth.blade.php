@auth
    <div
        x-data="{ open:false, hover:false }"
        x-init="open = false; hover = false; window.addEventListener('popstate', () => { open = false; hover = false; }); document.addEventListener('visibilitychange', () => { if (!document.hidden && open) { open = false; hover = false; } });"
        @keydown.escape.window="open=false"
        class="relative shrink-0"
    >
        {{-- ТРИГЕР --}}
        <button
            type="button"
            @click="open = !open"
            @mouseenter="hover=true; open=true"
            @mouseleave="hover=false; setTimeout(()=>{ if(!hover) open=false }, 120)"
            class="inline-flex items-center gap-2 text-sm leading-none font-medium text-[#19191A] hover:text-orange-600 shrink-0"
            aria-haspopup="menu"
            :aria-expanded="open"
        >
            <img src="{{ asset('images/user.svg') }}" class="w-5 h-5 shrink-0 flex-none" width="20" height="20" alt="">
            {{-- Текст на планшете скрываем, показываем с lg --}}
            <span class="hidden lg:inline whitespace-nowrap">
                @php
                    $user = auth()->user();
                    $displayName = trim($user->name ?? '');
                    if (empty($displayName)) {
                        // Если имени нет, показываем номер телефона в формате +380505585...
                        $phone = $user->phone ?? '';
                        if ($phone && strlen($phone) >= 12) {
                            // Форматируем: +380505585... (первые 9 цифр + ... + последние 3)
                            $displayName = '+' . substr($phone, 0, 9) . '...';
                        } elseif ($phone) {
                            $displayName = '+' . $phone;
                        } else {
                            $displayName = 'Мій профіль';
                        }
                    }
                @endphp
                {{ $displayName }}
            </span>
            <svg class="hidden lg:inline w-4 h-4 opacity-60" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                <path d="M5.3 7.3a1 1 0 011.4 0L10 10.6l3.3-3.3a1 1 0 111.4 1.4l-4 4a1 1 0 01-1.4 0l-4-4a1 1 0 010-1.4z"/>
            </svg>
        </button>

        {{-- ФОН для клика поза меню на мобільних --}}
        <div
            x-show="open"
            x-cloak
            x-transition.opacity
            class="fixed inset-0 z-40 lg:hidden"
            @click="open=false"
        ></div>

        {{-- ДРОПДАУН --}}
        <div
            x-show="open"
            x-cloak
            x-transition.origin.top.right
            @click.outside="open=false"
            @mouseenter="hover=true"
            @mouseleave="hover=false; setTimeout(()=>{ if(!hover) open=false }, 120)"
            class="absolute right-0 mt-3 w-72 rounded-lg bg-white shadow-xl ring-1 ring-black/10 z-50"
            role="menu"
            aria-label="Меню профілю"
        >
            @include('pages.menu.profile-menu')
        </div>
    </div>
@else
    <a
        href="{{ route('auth.show') }}"
        class="inline-flex items-center gap-2 text-sm leading-none font-medium text-[#19191A] hover:text-orange-600 shrink-0"
    >

    <img src="{{ asset('images/user.svg') }}" class="w-5 h-5 shrink-0 flex-none" width="20" height="20" alt="">
        {{-- Текст на планшете скрываем, показываем с lg --}}
        <span class="hidden lg:inline whitespace-nowrap">
            {{ st('header.login','Увійти') }}
        </span>
    </a>
@endauth
