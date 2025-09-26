@props([
// [{ key:'about', label:'Про нас', href:route('about'), activeWhen:'about*' }, ...]
'items'   => [],
// server-side текущий ключ (опционально). если не задан — попытаемся вычислить по activeWhen
'current' => null,
// показать заголовок секции (опционально)
'title'   => null,
// сохранять выбор в localStorage
'remember'=> false,
// ключ для localStorage
'storageKey' => 'burger.menu.active',
// сдвиг слева если есть название
'class_no_title' => '',
// выводить ли аккаутн меню
'is_account_menu' => false,
])

@php
    // если current не передан — найдём актив по шаблону маршрута
    $initial = $current;
    if (!$initial) {
        foreach ($items as $it) {
            if (!empty($it['activeWhen']) && request()->routeIs($it['activeWhen'])) {
                $initial = $it['key'] ?? null;
                break;
            }
        }
    }
@endphp

@if($is_account_menu)
    <nav x-data="menuList({ initial: @js($initial), remember: @js($remember), storageKey: @js($storageKey) })"
         class="space-y-1 text-[16px] mb-6">
             @foreach ($items as $it)
            @php
                $pattern  = $it['activeWhen'] ?? $it['route'] ?? null;
                $isActive = $pattern ? request()->routeIs($pattern) : false;

                $href = $it['href']
                    ?? (isset($it['route']) && \Illuminate\Support\Facades\Route::has($it['route'])
                        ? route($it['route'], $it['params'] ?? [])
                        : '#'); // fallback
            @endphp
            <a href="{{ $href }}"
               class="group flex items-center gap-3 rounded-xl px-3 py-2 transition-colors
              {{ $isActive ? 'text-[#FF7500] font-semibold bg-[#FFF9ED]' : 'text-[#929292]  hover:text-[#19191A]' }}">
                <x-dynamic-component
                    :component="$it['icon']"
                    class="w-6 h-6 {{ $isActive ? 'text-[#FF7500]' : 'text-[#929292] group-hover:text-[#FF7500]' }}"
                />
                <span class="flex-1">{{ $it['label'] }}</span>
            </a>
            @endforeach
                 @guest('web')
                     @php $isActive = request()->routeIs('login'); @endphp
                     <a href="{{ route('login') }}"
                        class="group flex items-center gap-3 rounded-xl px-3 py-2 transition-colors
              {{ $isActive ? 'text-[#FF7500] font-semibold bg-[#FFF9ED]' : 'text-[#929292]  hover:text-[#19191A]' }}">
                         <x-icons.login class="w-6 h-6 {{ $isActive ? 'text-[#FF7500]' : 'text-[#929292] group-hover:text-[#FF7500]' }}"/>
                         <span>Увійти</span>
                     </a>
                 @endguest

                 @auth('web')
                     @php $isActive = request()->routeIs('logout'); @endphp
                     <a href="{{ route('logout') }}"
                        class="group flex items-center gap-3 rounded-xl px-3 py-2 transition-colors
              {{ $isActive ? 'text-[#FF7500] font-semibold bg-[#FFF9ED]' : 'text-[#929292] hover:bg-[#FFF9ED] hover:text-[#19191A]' }}"
                        onclick="event.preventDefault(); document.getElementById('logout-form').submit();">
                         <x-icons.login class="w-6 h-6 {{ $isActive ? 'text-[#FF7500]' : 'text-[#929292] group-hover:text-[#FF7500]' }}"/>
                         <span>Вийти</span>
                     </a>
                     <form id="logout-form" action="{{ route('logout') }}" method="POST" class="hidden">
                         @csrf
                     </form>
                 @endauth


    </nav>
@endif

@if(!$is_account_menu)
<nav x-data="menuList({ initial: @js($initial), remember: @js($remember), storageKey: @js($storageKey) })"
     class="space-y-1 text-[18px]">
    @if($title)
        @php $class_no_title = 'ml-8'; @endphp
        <h3 class="px-3 text-[18px] font-semibold text-[#C04103]">{{ $title }}</h3>
    @endif
        <ul class="mt-2 space-y-1 {{$class_no_title}}">
    @foreach($items as $it)
        @php $key = $it['key'] ?? Str::slug($it['label']); @endphp
                <li> <a
            href="{{ $it['href'] ?? '#' }}"
            @click="{{ ($it['href'] ?? '#') === '#' ? "setActive('{$key}'); \$event.preventDefault()" : "setActive('{$key}')" }}"
            :aria-current="isActive('{{ $key }}') ? 'page' : null"
            :class="isActive('{{ $key }}')
                ? 'block rounded-xl px-3 py-2 transition-colors bg-[#FFF9ED] text-[#19191A] font-medium'
                : 'block rounded-xl px-3 py-2 text-[#929292] hover:bg-[#FFF9ED] hover:text-[#19191A]'" >
            {{ $it['label'] }}
                    </a></li>
    @endforeach
        </ul>
</nav>
@endif
