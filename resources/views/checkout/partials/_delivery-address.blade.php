<div
    class="bg-white rounded-xl shadow-[0_2px_10px_rgba(0,0,0,.08)] pt-3 pr-4 pb-3 pl-4 space-y-6"
    x-data="{ useNew: {{ $useNewInitial ? 'true' : 'false' }} }"
    x-show="method === 'delivery'"
    x-cloak
>
    <div class="text-[18px] md:text-[22px] leading-6 md:leading-7 font-semibold">
        {{ st('cart.delivery.title', 'Адреса доставки') }}
    </div>

    {{-- Сохранённые адреса --}}
    @if($client && $addresses->count())
        <div class="space-y-4">
            @foreach($addresses as $addr)
                @php
                    $fullLine = trim(
                        ($addr->street
                            ? st('address.parts.street_prefix', 'вулиця').' '.$addr->street
                            : ''
                        ) .
                        ($addr->house
                            ? ', '.st('address.parts.house_short', 'д.').$addr->house
                            : ''
                        ) .
                        ($addr->apartment
                            ? ', '.st('address.parts.apartment_short', 'кв. ').$addr->apartment
                            : ''
                        )
                    );

                    $typeLabel = null;
                    if (!empty($addr->type)) {
                        $map = [
                            'home'    => st('address.type.home', 'Дім'),
                            'work'    => st('address.type.work', 'Робота'),
                            'friends' => st('address.type.friends', 'Друзі'),
                        ];
                        $typeLabel = $map[$addr->type] ?? $addr->type;
                    }
                @endphp

                <label class="flex items-start gap-2 cursor-pointer"
                       @click="useNew = false">
                    <input type="radio"
                           name="selected_address_id"
                           value="{{ $addr->id }}"
                           class="tp-radio mt-[3px]"
                           @checked($selectedId == $addr->id)
                    >
                    <span class="leading-5">
                        <span class="text-[16px] leading-[22px] text-[#272828]">
                            {{ $fullLine }}
                            @if($typeLabel) ({{ $typeLabel }}) @endif
                        </span><br>
                        @if(!empty($addr->city))
                            <span class="text-xs text-[#9CA3AF]">{{ $addr->city }}</span>
                        @endif
                    </span>
                </label>
            @endforeach
        </div>
        @error('selected_address_id')
        <p class="mt-1 text-xs text-red-500">{{ $message }}</p>
        @enderror

        {{-- Управление новым адресом --}}
        <div class="flex items-center gap-4">
            <button type="button"
                    class="text-[#272828] font-medium flex items-center gap-2"
                    @click="useNew = true">
                <span class="text-xl leading-none">+</span>
                {{ st('cart.address.add_new', 'Додати нову адресу') }}
            </button>

            <button type="button"
                    class="text-[#EF4444] text-sm font-medium"
                    @click="useNew = false">
                {{ st('cart.address.do_not_use_new', '✕ Не використовувати нову адресу') }}
            </button>
        </div>
    @endif

    {{-- Флаг для бэка: использовать новый адрес или нет --}}
    <input type="hidden" name="use_new_address" :value="useNew ? 1 : 0">

    {{-- Поля нового адреса --}}
    <div class="space-y-4" x-show="useNew" x-cloak>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
            <div class="input-required">
                <label class="tp-label tp-required">
                    {{ st('address.form.street', 'Вулиця') }}
                </label>
                <input
                    id="checkout-address-street"
                    name="addr[street]"
                    class="tp-input"
                    placeholder="{{ st('address.form.street', 'Вулиця') }}"
                    :disabled="!useNew || method  === 'pickup'"
                    :required="useNew && method  === 'delivery'"
                    value="{{ old('addr.street', $sessionData['addr_street'] ?? '') }}"
                >
                @error('addr.street')
                <p class="mt-1 text-xs text-red-500">{{ $message }}</p>
                @enderror
            </div>

            <div class="input-required">
                <label class="tp-label tp-required">
                    {{ st('address.form.house', 'Дім') }}
                </label>
                <input
                    id="checkout-address-house"
                    name="addr[house]"
                    class="tp-input"
                    placeholder="{{ st('address.form.house', 'Дім') }}"
                    :disabled="!useNew || method  === 'pickup'"
                    :required="useNew && method  === 'delivery'"
                    value="{{ old('addr.house', $sessionData['addr_house'] ?? '') }}"
                >
                @error('addr.house')
                <p class="mt-1 text-xs text-red-500">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label class="tp-label">
                    {{ st('address.form.intercom', 'Домофон') }}
                </label>
                <input
                    name="addr[intercom]"
                    class="tp-input"
                    placeholder="{{ st('address.form.intercom', 'Домофон') }}"
                    :disabled="!useNew"
                    value="{{ old('addr.intercom', $sessionData['addr_intercom'] ?? '') }}"
                >
            </div>

            <div>
                <label class="tp-label tp-required">
                    {{ st('address.form.apartment', 'Квартира') }}
                </label>
                <input
                    name="addr[apartment]"
                    class="tp-input"
                    placeholder="{{ st('address.form.apartment', 'Квартира') }}"
                    :disabled="!useNew || method  === 'pickup'"
                    :required="useNew && method  === 'delivery'"
                    value="{{ old('addr.apartment', $sessionData['addr_apartment'] ?? '') }}"
                >
                @error('addr.apartment')
                <p class="mt-1 text-xs text-red-500">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label class="tp-label">
                    {{ st('address.form.floor', 'Поверх') }}
                </label>
                <input
                    name="addr[floor]"
                    class="tp-input"
                    placeholder="{{ st('address.form.floor', 'Поверх') }}"
                    :disabled="!useNew"
                    value="{{ old('addr.floor', $sessionData['addr_floor'] ?? '') }}"
                >
            </div>

            <div>
                <label class="tp-label tp-required">
                    {{ st('address.form.porch', 'Під’їзд') }}
                </label>
                <input
                    name="addr[porch]"
                    class="tp-input"
                    placeholder="{{ st('address.form.porch', 'Під’їзд') }}"
                    :disabled="!useNew || method  === 'pickup'"
                    :required="useNew && method  === 'delivery'"
                    value="{{ old('addr.porch', $sessionData['addr_porch'] ?? '') }}"
                >
                @error('addr.porch')
                <p class="mt-1 text-xs text-red-500">{{ $message }}</p>
                @enderror
            </div>
        </div>

        <div>
            <label class="tp-label">
                {{ st('address.form.comment', 'Коментар для кур’єра') }}
            </label>
            <input
                name="addr[comment]"
                class="tp-input"
                placeholder="{{ st('address.form.comment', 'Коментар для кур’єра') }}"
                :disabled="!useNew"
                value="{{ old('addr.comment', $sessionData['addr_comment'] ?? '') }}"
            >
        </div>

        <label class="inline-flex items-center gap-2">
            <input
                type="checkbox"
                class="tp-check"
                name="addr[is_private_house]"
                value="1"
                :disabled="!useNew"
                @checked(old('addr.is_private_house', !empty($sessionData['addr_is_private_house'])))
            >
            <span class="text-sm text-gray-700">
                {{ st('address.form.private_house', 'Це приватний будинок') }}
            </span>
        </label>

        {{-- Тип адреса: дом / работа / друзья --}}
        <div
            class="flex flex-wrap gap-2"
            x-data="{ t: '{{ old('addr.type', $sessionData['addr_type'] ?? 'home') }}' }"
        >
            <input type="hidden" name="addr[type]" :value="t" :disabled="!useNew">

            <button type="button"
                    class="h-10 min-w-[72px] px-3 rounded-[12px] text-[14px]"
                    :class="t === 'home'
                        ? 'bg-[#FF7500] text-white'
                        : 'bg-[#F3F4F6] text-[#272828]'"
                    @click="t = 'home'">
                {{ st('address.type.home', 'Дім') }}
            </button>

            <button type="button"
                    class="h-10 min-w-[72px] px-3 rounded-[12px] text-[14px]"
                    :class="t === 'work'
                        ? 'bg-[#FF7500] text-white'
                        : 'bg-[#F3F4F6] text-[#272828]'"
                    @click="t = 'work'">
                {{ st('address.type.work', 'Робота') }}
            </button>

            <button type="button"
                    class="h-10 min-w-[72px] px-3 rounded-[12px] text-[14px]"
                    :class="t === 'friends'
                        ? 'bg-[#FF7500] text-white'
                        : 'bg-[#F3F4F6] text-[#272828]'"
                    @click="t = 'friends'">
                {{ st('address.type.friends', 'Друзі') }}
            </button>
        </div>
    </div>
</div>
