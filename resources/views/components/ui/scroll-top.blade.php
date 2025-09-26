<div
    x-data="{ showTop: false }"
    x-init="window.addEventListener('scroll', () => showTop = window.scrollY > 300)"
>
    <button
        x-show="showTop"
        @click="window.scrollTo({ top: 0, behavior: 'smooth' })"
        class="fixed bottom-6 right-6 w-16 h-16 rounded-full bg-[#FF7500] flex items-center justify-center shadow-lg transition hover:scale-110"
    >

        <svg width="64" height="64" viewBox="0 0 64 64" fill="none" xmlns="http://www.w3.org/2000/svg">
            <rect width="64" height="64" rx="32" fill="#FF7500"/>
            <path d="M32 41V23.5" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
            <path d="M39 30L32 23L25 30" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
        </svg>

    </button>
</div>
