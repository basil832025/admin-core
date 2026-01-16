@php
//dd($cartQty);
    $cartQty = (int)($cartQty ?? (app(\App\Services\CartService::class)->info()['qty'] ?? 0));
//dd($cartQty,app(\App\Services\CartService::class)->info()['qty'] );
@endphp
<div
    x-data="{
    isOpen:false, url:null, hdr:0,
    async open(u){ this.calcHeader(); this.isOpen=true; this.url=u; await this.load(u) },
    close(){ this.isOpen=false; this.url=null; },
    calcHeader(){ const el=document.getElementById('site-header'); this.hdr=el?Math.round(el.getBoundingClientRect().height):0 },
    async load(u){
      const box = this.$refs.box;
      box.innerHTML = '<div class=\'p-6 text-center text-gray-500\'>{{ st('cart.zavantazhennya', 'Завантаження') }}…</div>';
const html = await fetch(u, { headers:{'Accept':'text/html'} }).then(r=>r.text());
box.innerHTML = html;
}
}"
x-init="isOpen = false; url = null; $nextTick(() => { isOpen = false; close(); }); $el.addEventListener('cart-reload', () => { if (isOpen && url) load(url) }); window.addEventListener('cart-reload', () => { if (isOpen && url) load(url) }); window.addEventListener('popstate', () => { close(); }); document.addEventListener('visibilitychange', () => { if (!document.hidden && isOpen) { close(); } });"
>

    {{-- иконка --}}
    <a href="#"
       class="relative flex items-center justify-center w-5 h-5"
       data-url="{{ route('cart.sidebar') }}"
       @click.prevent="open($event.currentTarget.dataset.url)">
        <img src="{{ asset('images/cart.svg') }}" class="w-5 h-5 shrink-0 flex-none block" width="20" height="20" alt="">
        <span x-cloak
              x-show="$store.cart && ($store.cart.qty > 0)"
              x-text="$store.cart ? $store.cart.qty : 0"
              class="absolute -top-1 -right-2 bg-red-600 text-white text-[10px] leading-none rounded-full px-1 min-w-[16px] text-center">0</span>
    </a>

    {{-- backdrop: начинается ниже шапки --}}
    <div x-show="isOpen"
         x-cloak
         x-transition.opacity
         @click="close"
         class="fixed left-0 right-0 z-[40] bg-black/40"
         :style="`top:${hdr}px; height: calc(100dvh - ${hdr}px);`"></div>

    {{-- панель: тоже ниже шапки, ширины по брейкпоинтам --}}
    <div x-show="isOpen"
         x-cloak
         x-transition:enter="transition transform duration-300"
         x-transition:enter-start="translate-x-full"
         x-transition:enter-end="translate-x-0"
         x-transition:leave="transition transform duration-300"
         x-transition:leave-start="translate-x-0"
         x-transition:leave-end="translate-x-full"
         class="fixed right-0 z-[50] bg-white shadow-xl overflow-y-auto"
         :style="`top:${hdr}px; height: calc(100dvh - ${hdr}px);`"
    >
        <div class="w-screen md:w-[768px] xl:w-[800px] h-full flex flex-col">
            <div class="flex items-center justify-between p-4 border-b">
                <h2 class="text-lg font-semibold">{{ st('cart.korzina', 'Кошик') }}</h2>
                <button @click="close" class="text-gray-400 hover:text-gray-600">✕</button>
            </div>
            <div x-ref="box" class="min-h-[120px] p-2 grow">
                <div class="p-6 text-center text-gray-500">{{ st('cart.zavantazhennya', 'Завантаження') }}...</div>
            </div>
        </div>
    </div>
</div>
