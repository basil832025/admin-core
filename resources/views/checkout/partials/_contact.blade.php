<div class="bg-white rounded-xl shadow-[0_2px_10px_rgba(0,0,0,0.08)] p-4">
    <div class="text-[22px] leading-7 font-semibold mb-4">{{ st('profile.kontaktni-dani', 'Контактні дані') }}</div>

    <div class="flex flex-col gap-4 @auth md:flex-col @else md:flex-row @endauth">
        {{-- ЛЕВАЯ ЧАСТЬ: поля контактов --}}
        <div class="w-full grid gap-2">
            {{-- Имя --}}
            <label class="block">
                <span class="sr-only">{{ st('profile.name', 'Імʼя') }}</span>
                <input
                    type="text"
                    id="contact_name"
                    name="contact_name"
                    placeholder="{{ st('profile.name', 'Імʼя') }}*"
                    value="{{ old('contact_name', $client->name ?? '') }}"
                    class="w-full h-[46px] rounded-[6px] border border-[#E5E7EB] px-4
                           text-[16px] leading-[22px] placeholder:text-[#9CA3AF]
                           focus:outline-none focus:ring-2 focus:ring-[#FF7500]/20 focus:border-[#FF7500]
                           transition"
                    required
                >
            </label>

            {{-- Телефон --}}
            <label class="block">
                <span class="sr-only">{{ st('profile.phone', 'Телефон') }}</span>
                <input
                    type="tel"
                    id="contact_phone"
                    name="contact_phone"
                    inputmode="tel"
                    placeholder="{{ st('profile.phone', 'Телефон') }}*"
                    value="{{ old('contact_phone', $client->phone ?? '') }}"
                    @auth readonly class="w-full h-[46px] rounded-[6px] border border-[#E5E7EB] px-4
                       bg-gray-100 cursor-not-allowed text-[16px] leading-[22px]" @endauth
                    @guest class="w-full h-[46px] rounded-[6px] border border-[#E5E7EB] px-4
                       text-[16px] leading-[22px] placeholder:text-[#9CA3AF]
                       focus:outline-none focus:ring-2 focus:ring-[#FF7500]/20 focus:border-[#FF7500]
                       transition" @endguest
                    required
                >
            </label>

            {{-- Email --}}
            <label class="block">
                <span class="sr-only">Email</span>
                <input
                    type="email"
                    name="contact_email"
                    placeholder="E-mail ({{ st('profile.neobovyazkovo', 'необовʼязково') }})"
                    value="{{ old('contact_email', $client->email ?? '') }}"
                    class="w-full h-[46px] rounded-[6px] border border-[#E5E7EB] px-4
                           text-[16px] leading-[22px] placeholder:text-[#9CA3AF]
                           focus:outline-none focus:ring-2 focus:ring-[#FF7500]/20 focus:border-[#FF7500]
                           transition"
                >
            </label>
        </div>

        {{-- ПРАВАЯ ЧАСТЬ: мини-блок авторизации (только для гостя) --}}
        @guest
            <div class="w-full md:w-1/2">
                <div class="h-full rounded-[10px] border border-dashed border-[#FBBF77]
                            bg-[#FFF7EB] px-4 py-3 flex flex-col justify-between">
                    <div class="text-[12px] leading-[20px] text-[#272828] mb-3">
                        {{ st('cart.avtoryzuytes-telefon-avtozapovnennya-bonusy', 'Авторизуйтесь за допомогою номера телефону, щоб
                        автоматично заповнити інформацію та мати змогу
                        накопичувати й розраховуватися бонусами') }}.
                    </div>

                    <button
                        type="button"
                        class="h-[40px] w-full rounded-full bg-[#FF7500] text-white
                               text-[14px] font-semibold hover:bg-[#e56700] transition"
                        @click="$dispatch('open-auth-modal', { tab: 'login' })"
                    >
                        <span>{{ st('auth.login','Увійти') }}</span>
                    </button>
                </div>
            </div>
        @endguest
    </div>
</div>
