<div
    x-show="showTop"
    class="fixed bottom-0 left-0 right-0 z-50 pointer-events-none flex justify-end
         w-screen max-w-full px-4 sm:px-6 md:px-8"
    style="
    padding-right: max(16px, env(safe-area-inset-right));
    padding-bottom: max(16px, env(safe-area-inset-bottom));
  "
>
    <button
        @click="window.scrollTo({ top: 0, behavior: 'smooth' })"
        class="w-14 h-14 md:w-16 md:h-16 rounded-full bg-[#FF7500]
           flex items-center justify-center shadow-lg transition hover:scale-110 pointer-events-auto"
        aria-label="Наверх"
    >
        <svg viewBox="0 0 24 24" class="w-6 h-6" fill="none">
            <path d="M12 5v14M12 5l-6 6M12 5l6 6" stroke="white" stroke-width="2" stroke-linecap="round"/>
        </svg>
    </button>
</div>
