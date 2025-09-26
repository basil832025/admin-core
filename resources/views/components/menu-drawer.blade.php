@props([
'event' => 'open-mobile-menu',        // какое событие открывает
'side'  => 'left',                    // 'left' | 'right'
'width' => 'w-[304px] sm:w-[360px] lg:w-[420px] max-w-[90vw]',
'panelClass' => '',                   // доп. классы для панели
'overlayClass' => '',                 // доп. классы для затемнения
])
@php
/*
   $aboutMenu = [
      ['key'=>'about',     'label'=>'Про нас',                 'href'=>route('about'),     'activeWhen'=>'about*'],
      ['key'=>'reviews',   'label'=>'Відгуки клієнтів',        'href'=>route('reviews'),   'activeWhen'=>'reviews*'],
      ['key'=>'sales',     'label'=>'Акції',                   'href'=>route('sales'),     'activeWhen'=>'sales*'],
      ['key'=>'bonus',     'label'=>'Бонуси',                  'href'=>route('bonus'),     'activeWhen'=>'bonus*'],
      ['key'=>'blog',      'label'=>'Блог',                    'href'=>route('blog.index'),'activeWhen'=>'blog*'],
      ['key'=>'delivery',  'label'=>'Доставка і самовивіз',    'href'=>route('delivery'),  'activeWhen'=>'delivery*'],
      ['key'=>'restaurants','label'=>'Наші ресторани',         'href'=>route('restaurants'),'activeWhen'=>'restaurants*'],
    ];
 */
    $aboutMenu = [
      ['key'=>'about',     'label'=>'Про нас',                 'href'=>'#',     'activeWhen'=>'about*'],
      ['key'=>'reviews',   'label'=>'Відгуки клієнтів',        'href'=>'#',   'activeWhen'=>'reviews*'],
      ['key'=>'sales',     'label'=>'Акції',                   'href'=>'#',     'activeWhen'=>'sales*'],
      ['key'=>'bonus',     'label'=>'Бонуси',                  'href'=>'#',     'activeWhen'=>'bonus*'],
      ['key'=>'blog',      'label'=>'Блог',                    'href'=>'#','activeWhen'=>'blog*'],
      ['key'=>'delivery',  'label'=>'Доставка і самовивіз',    'href'=>'#',  'activeWhen'=>'delivery*'],
      ['key'=>'restaurants','label'=>'Наші ресторани',         'href'=>'#','activeWhen'=>'restaurants*'],
    ];

       $catalogMenu = [
            ['key'=>'all',     'label'=>'Всі пироги', 'href'=>'#', 'activeWhen'=>'category.all'],
            ['key'=>'hits',    'label'=>'Хіти',       'href'=>'#','activeWhen'=>'category.hits'],
            ['key'=>'news',    'label'=>'Новинки',       'href'=>'#','activeWhen'=>'category.news'],
            ['key'=>'cheese',    'label'=>'Сырные',       'href'=>'#','activeWhen'=>'category.cheese'],
            ['key'=>'meat',    'label'=>'Мясные',       'href'=>'#','activeWhen'=>'category.meat'],
            ['key'=>'postn',    'label'=>'Постные',       'href'=>'#','activeWhen'=>'category.postn'],
            ['key'=>'sladk',    'label'=>'Сладкие',       'href'=>'#','activeWhen'=>'category.sladk'],
            ['key'=>'sets',    'label'=>'Сеты',       'href'=>'#','activeWhen'=>'category.sets'],
            ['key'=>'tort',    'label'=>'Торты',       'href'=>'#','activeWhen'=>'category.tort'],
            ['key'=>'souse',    'label'=>'Соусы',       'href'=>'#','activeWhen'=>'category.souse'],
            ['key'=>'napitk',    'label'=>'Напитки',       'href'=>'#','activeWhen'=>'category.napitk'],

            ];
   $accountMenu = [
    ['key'=>'fav',   'label'=>'Избранное',        'route'=>'favorites.index',  'activeWhen'=>'favorites.*',   'icon'=>'icons.heart'],
    ['key'=>'orders','label'=>'История заказов',  'route'=>'orders.index',     'activeWhen'=>'orders.*',      'icon'=>'icons.history'],
    ['key'=>'bonus', 'label'=>'0 Бонусов',        'route'=>'bonus.index',      'activeWhen'=>'bonus.*',       'icon'=>'icons.bonus'],
    ['key'=>'prof',  'label'=>'Профиль',          'route'=>'profile.show',     'activeWhen'=>'profile.*',     'icon'=>'icons.user'],
    ['key'=>'addr',  'label'=>'Адреса доставки',  'route'=>'addresses.index',  'activeWhen'=>'addresses.*',   'icon'=>'icons.pin'],
];
@endphp
<div
    x-data="{ open: false }"
    x-on:{{ $event }}.window="open = true"
    x-init="$watch('open', v => {
        document.documentElement.classList.toggle('no-scroll', v)
        document.body.classList.toggle('no-scroll', v)
     })"
    x-on:keydown.window.escape="open = false"

    x-cloak
>
    <!-- затемнение -->
    <div
        x-show="open"
        x-transition.opacity
        @click="open=false"
        class="fixed inset-0 z-50 bg-black/40 {{ $overlayClass }}"
        aria-hidden="true"
    ></div>

@php
    $from = $side === 'right' ? 'translate-x-full' : '-translate-x-full';
    $pos  = $side === 'right' ? 'right-0' : 'left-0';
@endphp


    <!-- панель слева -->
    <aside
        x-show="open"
        x-transition:enter="transform transition ease-in-out duration-300"
        x-transition:enter-start="-translate-x-full"
        x-transition:enter-end="translate-x-0"
        x-transition:leave="transform transition ease-in-out duration-300"
        x-transition:leave-start="translate-x-0"
        x-transition:leave-end="-translate-x-full"
        :id="$id('drawer')"
        class="fixed left-0 top-0 z-[60] h-full md:w-[414px] w-[355px] bg-white shadow-2xl h-full overflow-y-auto custom-scroll"
        role="dialog"
        aria-modal="true"
    >
        <!-- ВНУТРЕННИЙ СКРОЛЛЕР  class="h-full overflow-y-auto pr-3 custom-scroll" -->
        <div >
        <!-- Шапка меню -->
        <div class="flex items-center justify-between px-8 py-6 ">
            <div class="flex items-center gap-2">
                <img src="{{ asset('images/logo.svg') }}" alt="Три пироги" >

            </div>
            <button class="p-2 rounded-lg hover:bg-black/5" @click="open=false" aria-label="Закрыть меню">
                <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M6 6l12 12M18 6l-12 12"/>
                </svg>
            </button>
        </div>

        <!-- Контент (скролл внутри) -->
        <div class="h-[calc(100%-56px)] overflow-y-auto px-8">
            <!-- разделы -->
            <x-ui.menu-list  :items="$accountMenu" :is_account_menu="true"  />
            <x-ui.menu-list  :items="$aboutMenu" :remember="true" />
            <!--   <nav class="space-y-1 text-[18px]">
                   <a href="#" class="block rounded-xl px-3 py-2 bg-[#FFF9ED] text-[#19191A] font-medium">Про нас</a>
                   <a href="#" class="block rounded-xl px-3 py-2 text-[#929292]">Отзывы клиентов</a>
                   <a href="#" class="block rounded-xl px-3 py-2 text-[#929292]">Акции</a>
                   <a href="#" class="block rounded-xl px-3 py-2 text-[#929292]">Бонусы</a>
                   <a href="#" class="block rounded-xl px-3 py-2 text-[#929292]">Блог</a>
                   <a href="#" class="block rounded-xl px-3 py-2 text-[#929292]">Доставка и самовывоз</a>
                   <a href="#" class="block rounded-xl px-3 py-2 text-[#929292]">Наши рестораны</a>
               </nav>-->


               <div class="mt-6">
                   <x-ui.menu-list title="Меню" :items="$catalogMenu" :remember="true" />
                 <!-- Меню каталога
                   <h3 class="px-3 text-[18px] font-semibold text-[#C04103]">Меню</h3>
                   <ul class="mt-2 space-y-1 ml-8">
                       <li><a class="block px-3 py-2 text-sm text-[#929292] hover:bg-black/5 rounded-lg" href="#">Все пироги</a></li>
                       <li><a class="block px-3 py-2 text-sm text-[#929292] hover:bg-black/5 rounded-lg" href="#">Хиты</a></li>
                       <li><a class="block px-3 py-2 text-sm text-[#929292] hover:bg-black/5 rounded-lg" href="#">Новинки</a></li>
                       <li><a class="block px-3 py-2 text-sm text-[#929292] hover:bg-black/5 rounded-lg" href="#">Сырные</a></li>
                       <li><a class="block px-3 py-2 text-sm text-[#929292] hover:bg-black/5 rounded-lg" href="#">Мясные</a></li>
                       <li><a class="block px-3 py-2 text-sm text-[#929292] hover:bg-black/5 rounded-lg" href="#">Постные</a></li>
                       <li><a class="block px-3 py-2 text-sm text-[#929292] hover:bg-black/5 rounded-lg" href="#">Сладкие</a></li>
                       <li><a class="block px-3 py-2 text-sm text-[#929292] hover:bg-black/5 rounded-lg" href="#">Сеты</a></li>
                       <li><a class="block px-3 py-2 text-sm text-[#929292] hover:bg-black/5 rounded-lg" href="#">Торты</a></li>
                       <li><a class="block px-3 py-2 text-sm text-[#929292] hover:bg-black/5 rounded-lg" href="#">Соусы</a></li>
                       <li><a class="block px-3 py-2 text-sm text-[#929292] hover:bg-black/5 rounded-lg" href="#">Напитки</a></li>
                   </ul>
               </div>
               -->
            <!-- Кнопка входа -->
            <div class="px-3 py-5">
                <a href="#"
                   class="block text-center rounded-[4px] bg-[#FF7500] text-white font-semibold py-3">
                    Увійти
                </a>
            </div>

            <!-- Контакты -->
            <div class="px-3 pb-6 text-[16px] text-[#272828]">
                <h4 class="font-semibold mb-2">Контакты</h4>
                <div class="space-y-1">
                    <a href="tel:0444533333" class="block"> (044) 453-33-33</a>
                    <a href="tel:0932884333" class="block"> (093) 288-43-33</a>
                    <a href="tel:0979884333" class="block"> (097) 988-43-33</a>
                    <a href="tel:0660784333" class="block"> (066) 078-43-33</a>
                    <a href="mailto:info@3piroga.ua" class="block"> info@3piroga.ua</a>
                    <div class="mt-2 text-xs text-[#929292]">
                        Київ, бул. Лесі Українки, 24
                    </div>
                </div>

                <!-- соцсети -->
                <div class="mt-4">
                    <span class="text-[#929292] text-[13px]">Ми в соціальних мережах</span>
                <ul class="flex items-center mt-2 gap-8 md:gap-8 md:justify-left">
                    <li><a href="https://www.facebook.com/3piroga.ua" target="_blank" aria-label="Facebook" class="text-black hover:text-[#FF7500]">
                            <x-icons.facebook class="w-8 h-8"/>
                        </a></li>
                    <li><a href="https://www.instagram.com/3piroga_ua" target="_blank" aria-label="Instagram"  class="text-black hover:text-[#FF7500]">
                            <x-icons.instagram class="w-8 h-8"/>
                        </a></li>
                    <li><a href="#" aria-label="TikTok" target="_blank" class="text-black hover:text-[#FF7500]">
                            <x-icons.tiktok class="w-8 h-8"/>
                        </a></li>
                    <li><a href="https://t.me/OsetianBakery" target="_blank" aria-label="Telegram" class="text-black hover:text-[#FF7500]">
                            <x-icons.telegram class="w-8 h-8"/>
                        </a></li>
                    <li><a href="https://www.youtube.com/channel/UC37VV_ZFmkTacWeHKFsYWLQ" target="_blank" aria-label="YouTube" class="text-black hover:text-[#FF7500]">
                            <x-icons.youtube class="w-8 h-8"/>
                        </a></li>
                </ul>
            </div>
            </div>
        </div>
        </div>
    </aside>
</div>

