<div x-data="{ kitchen: false, courier: false }"
     class="bg-white rounded shadow-[0_2px_10px_rgba(0,0,0,.08)] pt-3 pr-4 pb-3 pl-4">

    <div class="text-[22px] leading-7 font-semibold mb-4">
        {{ st('cart.addons.title', 'Дополнения') }}
    </div>

    <div class="space-y-5">

        {{-- Комментарий для кухни --}}
        <div>
            <button type="button"
                    @click="kitchen = !kitchen"
                    class="flex items-center gap-2 font-medium"
                    :class="kitchen ? 'text-[#EF4444]' : 'text-[#272828]'">

                <span class="text-lg leading-none"
                      x-text="kitchen ? '{{ st('ui.close_icon','✕') }}' : '{{ st('ui.plus_icon','+') }}'"></span>

                <span
                    x-text="kitchen
                        ? '{{ st('cart.addons.kitchen.toggle','Добавить комментарий для кухни') }}'
                        : '{{ st('cart.addons.kitchen.toggle','Добавить комментарий для кухни') }}'">
                </span>
            </button>

            <template x-if="kitchen">
                <div class="mt-2">
                    <input type="text"
                           name="comment_kitchen"
                           placeholder="{{ st('cart.addons.kitchen.placeholder','Комментарий') }}"
                           class="w-full h-[46px] rounded-[6px] border border-[#E5E7EB] px-4 mt-2
                                  text-[16px] leading-[22px] placeholder:text-[#9CA3AF]
                                  focus:outline-none focus:ring-2 focus:ring-[#FF7500]/20 focus:border-[#FF7500]
                                  transition">
                </div>
            </template>
        </div>

        {{-- Комментарий для курьера --}}
        <div>
            <button type="button"
                    @click="courier = !courier"
                    class="flex items-center gap-2 font-medium"
                    :class="courier ? 'text-[#EF4444]' : 'text-[#272828]'">

                <span class="text-lg leading-none"
                      x-text="courier ? '{{ st('ui.close_icon','✕') }}' : '{{ st('ui.plus_icon','+') }}'"></span>

                <span
                    x-text="courier
                        ? '{{ st('cart.addons.courier.toggle','Добавить комментарий для курьера') }}'
                        : '{{ st('cart.addons.courier.toggle','Добавить комментарий для курьера') }}'">
                </span>
            </button>

            <template x-if="courier">
                <div class="mt-2">
                    <input type="text"
                           name="comment_courier"
                           placeholder="{{ st('cart.addons.courier.placeholder','Комментарий для курьера') }}"
                           class="w-full h-[46px] rounded-[6px] border border-[#E5E7EB] px-4 mt-2
                                  text-[16px] leading-[22px] placeholder:text-[#9CA3AF]
                                  focus:outline-none focus:ring-2 focus:ring-[#FF7500]/20 focus:border-[#FF7500]
                                  transition">
                </div>
            </template>
        </div>

    </div>
</div>
