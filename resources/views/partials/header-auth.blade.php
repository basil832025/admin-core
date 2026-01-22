@auth
    <div
        x-data="{
    open:false,
    hover:false,
    justOpened:false,
    canHover: window.matchMedia('(hover:hover)').matches
  }"
        x-init="
    const mq = window.matchMedia('(hover:hover)');
    const upd = () => canHover = mq.matches;
    upd();
    mq.addEventListener ? mq.addEventListener('change', upd) : mq.addListener(upd);

    window.addEventListener('popstate', () => { open=false; hover=false; justOpened=false; });
    document.addEventListener('visibilitychange', () => { if (!document.hidden && open) { open=false; hover=false; justOpened=false; } });
  "
        @keydown.escape.window="open=false; justOpened=false"
        class="relative shrink-0"
    >
        {{-- ТРИГЕР --}}
        <button
            type="button"
            @click.stop="
      justOpened = true;
      open = !open;
      $nextTick(() => setTimeout(() => justOpened = false, 200));
    "
            @mouseenter="if (canHover) { hover=true; open=true }"
            @mouseleave="if (canHover) { hover=false; setTimeout(()=>{ if(!hover) open=false }, 120) }"
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
            x-transition:enter="transition ease-out duration-100"
            x-transition:enter-start="opacity-0"
            x-transition:enter-end="opacity-100"
            x-transition:leave="transition ease-in duration-75"
            x-transition:leave-start="opacity-100"
            x-transition:leave-end="opacity-0"
            class="fixed inset-0 z-40 lg:hidden bg-black/20"
            @click="if (!justOpened) { open = false; justOpened = false; }"
        ></div>

        {{-- ДРОПДАУН --}}
        <div
            x-show="open"
            x-cloak
            x-transition:enter="transition ease-out duration-100"
            x-transition:enter-start="opacity-0 scale-95"
            x-transition:enter-end="opacity-100 scale-100"
            x-transition:leave="transition ease-in duration-75"
            x-transition:leave-start="opacity-100 scale-100"
            x-transition:leave-end="opacity-0 scale-95"
            @click.stop
            @mouseenter="hover=true"
            @mouseleave="hover=false; setTimeout(()=>{ if(!hover) open=false }, 120)"
            class="
    absolute mt-3 z-50 pointer-events-auto
    right-4 left-auto
    w-72 max-w-[calc(100vw-2rem)]
    rounded-lg bg-white shadow-xl ring-1 ring-black/10
    lg:right-0 lg:left-auto lg:w-72
  "
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
