<footer class="bg-white text-[#929292] mt-[120px]">
    <div class="max-w-screen-xl mx-auto px-4 md:px-6">
        {{-- линия-разделитель --}}
        <div class="border-t border-black/10"></div>

        {{-- сетка для md = 3 колонки, lg = 4 --}}
        <div class="grid grid-cols-1 md:grid-cols-3 lg:grid-cols-4 gap-x-10 gap-y-8 py-12">

            {{-- Три пирога --}}
            <div>
                <h3 class="text-lg font-semibold text-black">Три пирога</h3>
                <ul class="mt-4 space-y-2 text-[14px] text-[#929292] font-bold">
                    <li><a class="hover:text-black" href="#">Про нас</a></li>
                    <li><a class="hover:text-black" href="#">Відгуки клієнтів</a></li>
                    <li><a class="hover:text-black" href="#">Акції</a></li>
                    <li><a class="hover:text-black" href="#">Бонусна програма лояности</a></li>
                    <li><a class="hover:text-black" href="#">Блог</a></li>
                </ul>
            </div>

            {{-- Юридична інформація --}}
            <div>
                <h3 class="text-lg font-semibold text-black">Юридична інформація</h3>
                <ul class="mt-4 space-y-2 text-[14px] text-[#929292] font-bold">
                    <li><a class="hover:text-black" href="#">Публічна оферта</a></li>
                    <li><a class="hover:text-black" href="#">Політика конфіденційності</a></li>
                </ul>
            </div>

            {{-- Доставка і ресторани --}}
            <div>
                <h3 class="text-lg font-semibold text-black">Доставка і ресторани</h3>
                <ul class="mt-4 space-y-2 text-[14px] text-[#929292] font-bold">
                    <li><a class="hover:text-black" href="#">Доставка та самовивіз</a></li>
                    <li><a class="hover:text-black" href="#">Наші ресторани</a></li>
                </ul>
            </div>

            {{-- Контакти --}}
            <div class="md:col-span-3 lg:col-span-1">
                <h3 class="text-lg font-semibold text-black">Контакти</h3>

                {{-- внутри: md = 3 колонки, lg = 1 колонка --}}
                <div class="mt-4 grid grid-cols-1 md:grid-cols-3 lg:grid-cols-1 gap-6 text-[14px] font-bold">
                    <ul class="space-y-2 text-[#272828]">
                        <li>(044) 453-33-33</li>
                        <li>(093) 288-43-33</li>
                    </ul>

                    <ul class="space-y-2 text-[#272828]">
                        <li>(097) 898-43-33</li>
                        <li>(066) 078-43-33</li>
                    </ul>

                    <ul class="space-y-2">
                        <li class="font-bold">
                            <a href="mailto:info@3piroga.ua" class="hover:text-black">info@3piroga.ua</a>
                        </li>
                        <li class="text-[#929292] font-normal">
                            Київ, бул. Лесі Українки, 24
                        </li>
                    </ul>
                </div>
            </div>

        </div>


            {{-- линия-разделитель --}}
            <div class="border-t border-black/10"></div>

            {{-- строка: логотип+лозунг слева | соцсети справа --}}
            <div class="py-8 flex flex-col gap-6 md:flex-row md:items-center justify-between">
                <div class=" flex items-start gap-4 desk:w-[498px] md:w-[419px]">
                    <img src="/images/logo-footer.png" alt="Три Пироги" class="w-14 h-14">
                    <p class="text-[#666666] text-[13px] leading-snug">
                        Мережа пекарень "ТРИ ПИРОГИ"  <span class="emoji">&#x2668;&#xFE0F;&#x2668;&#xFE0F;&#x2668;&#xFE0F;</span> - єдина у Києві, де печуть справжні осетинські пироги з пилу, з жару, в дров`яній печі
                    </p>
                </div>

                <ul class="flex items-center gap-8 md:gap-5 md:justify-end">
                    <li><a href="https://www.facebook.com/3piroga.ua" target="_blank" aria-label="Facebook" class="text-black hover:text-[#FF7500]">
                            <x-icons.facebook class="w-6 h-6"/>
                        </a></li>
                    <li><a href="https://www.instagram.com/3piroga_ua" target="_blank" aria-label="Instagram" class="text-black hover:text-[#FF7500]">
                            <x-icons.instagram class="w-6 h-6"/>
                        </a></li>
                    <li><a href="#" aria-label="TikTok" target="_blank" class="text-black hover:text-[#FF7500]">
                            <x-icons.tiktok class="w-6 h-6"/>
                        </a></li>
                    <li><a href="https://t.me/OsetianBakery" target="_blank" aria-label="Telegram" class="text-black hover:text-[#FF7500]">
                            <x-icons.telegram class="w-6 h-6"/>
                        </a></li>
                    <li><a href="https://www.youtube.com/channel/UC37VV_ZFmkTacWeHKFsYWLQ" target="_blank" aria-label="YouTube" class="text-black hover:text-[#FF7500]">
                            <x-icons.youtube class="w-6 h-6"/>
                        </a></li>
                </ul>
            </div>

            {{-- нижняя строка копирайта --}}
            <div class="border-t border-black/10"></div>
            <div class="py-6 flex flex-col md:flex-row md:items-center md:justify-left gap-4">
                <p class="text-[14px] text-[#A9A9A9]">© ТРИ ПИРОГА. Усі права захищені</p>
                <div class="flex items-center gap-6">
                    <img src="/images/payments/mastercard.png" alt="Mastercard" class="h-5">
                    <img src="/images/payments/visa.png" alt="VISA" class="h-5">
                </div>
            </div>

        </div>
    </footer>
