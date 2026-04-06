<div class="bg-white rounded shadow-[0_2px_10px_rgba(0,0,0,.08)] pt-3 pr-4 pb-3 pl-4">
    <div class="checkout-section-title mb-3 md:mb-4">
        {{ st('cart.payment.title', 'Способы оплаты') }}
    </div>

    <div class="flex flex-col gap-6">
        {{-- LiqPay --}}
        <label class="flex items-center gap-3 cursor-pointer">
            <input type="radio" name="payment_method" value="liqpay" class="tp-radio" x-model="paymentMethod" @checked($paymentMethod === 'liqpay' || (!$sessionData || !isset($sessionData['payment_method'])))>
            <span class="flex items-center gap-3 text-[16px] leading-[22px] text-[#272828]">
                <span>
                    {{-- иконка LiqPay --}}
                    <svg width="21" height="17" viewBox="0 0 21 17" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <rect width="21" height="16.5" rx="4" fill="#FF7500"/>
                        <path d="M6.175 3L5 4.175L8.81667 8L5 11.825L6.175 13L11.175 8L6.175 3Z" fill="white"/>
                        <path d="M12.3508 3L11.1758 4.175L14.9924 8L11.1758 11.825L12.3508 13L17.3508 8L12.3508 3Z" fill="white"/>
                    </svg>
                </span>
                                {{ st('profile.orders.payment.online','Онлайн карткою')}}
            </span>
        </label>
        <p class="text-xs text-gray-500 pl-8 -mt-4">
            {{ st('cart.payment.liqpay_note', 'Переадресуем на защищённую страницу LiqPay. Мы не обрабатываем данные вашей карты.') }}
        </p>

        {{-- Картой при получении --}}
        <label class="flex items-center gap-3 cursor-pointer">
            <input type="radio" name="payment_method" value="card_on_delivery" class="tp-radio" x-model="paymentMethod" @checked($paymentMethod === 'card_on_delivery')>
            <span class="flex items-center gap-3 text-[16px] leading-[22px] text-[#272828]">
                <span class="text-[#FF7500]" aria-hidden="true">
                    {{-- иконка карты --}}
                    <svg width="21" height="17" viewBox="0 0 21 17" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M0 13.875C0 14.5712 0.276562 15.2389 0.768845 15.7312C1.26113 16.2234 1.92881 16.5 2.625 16.5H18.375C19.0712 16.5 19.7389 16.2234 20.2312 15.7312C20.7234 15.2389 21 14.5712 21 13.875V6.65625H0V13.875ZM3.09375 10.3125C3.09375 9.93954 3.24191 9.58185 3.50563 9.31813C3.76935 9.05441 4.12704 8.90625 4.5 8.90625H6.75C7.12296 8.90625 7.48065 9.05441 7.74437 9.31813C8.00809 9.58185 8.15625 9.93954 8.15625 10.3125V11.25C8.15625 11.623 8.00809 11.9806 7.74437 12.2444C7.48065 12.5081 7.12296 12.6562 6.75 12.6562H4.5C4.12704 12.6562 3.76935 12.5081 3.50563 12.2444C3.24191 11.9806 3.09375 11.623 3.09375 11.25V10.3125ZM18.375 0H2.625C1.92881 0 1.26113 0.276562 0.768845 0.768845C0.276562 1.26113 0 1.92881 0 2.625V3.84375H21V2.625C21 1.92881 20.7234 1.26113 20.2312 0.768845C19.7389 0.276562 19.0712 0 18.375 0Z" fill="#FF7500"/>
                    </svg>
                </span>
                {{ st('cart.payment.card_on_delivery', 'Картой при получении') }}
            </span>
        </label>

        {{-- Наличными --}}
        <label class="flex items-center gap-3 cursor-pointer">
            <input type="radio" name="payment_method" value="cash" class="tp-radio" x-model="paymentMethod" @checked($paymentMethod === 'cash')>
            <span class="flex items-center gap-3 text-[16px] leading-[22px] text-[#272828]">
                <span class="text-[#FF7500]" aria-hidden="true">
                    {{-- иконка наличных --}}
                    <svg width="21" height="19" viewBox="0 0 21 19" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M19.96 6.66708H18.6667V3.33375C18.6667 3.15694 18.5964 2.98737 18.4714 2.86235C18.3464 2.73732 18.1768 2.66708 18 2.66708H2C1.82319 2.66708 1.65362 2.59685 1.5286 2.47182C1.40357 2.3468 1.33333 2.17723 1.33333 2.00042C1.33333 1.82361 1.40357 1.65404 1.5286 1.52901C1.65362 1.40399 1.82319 1.33375 2 1.33375H17.7333C17.9101 1.33375 18.0797 1.26351 18.2047 1.13849C18.3298 1.01346 18.4 0.843894 18.4 0.667083C18.4 0.490272 18.3298 0.320703 18.2047 0.195679C18.0797 0.0706545 17.9101 0.000416435 17.7333 0.000416435H2C1.7426 -0.00489035 1.48667 0.040568 1.24683 0.134195C1.007 0.227821 0.787966 0.367782 0.602238 0.546081C0.41651 0.72438 0.267729 0.937523 0.164396 1.17334C0.0610619 1.40915 0.00519958 1.66301 0 1.92042V15.9204C0.000872594 16.2826 0.073176 16.641 0.212769 16.9751C0.352362 17.3093 0.556503 17.6126 0.8135 17.8677C1.0705 18.1229 1.3753 18.3248 1.71046 18.462C2.04561 18.5991 2.40453 18.6688 2.76667 18.6671H18C18.1768 18.6671 18.3464 18.5968 18.4714 18.4718C18.5964 18.3468 18.6667 18.1772 18.6667 18.0004V14.6671H19.96C20.0441 14.6734 20.1287 14.6626 20.2085 14.6351C20.2883 14.6077 20.3616 14.5642 20.4241 14.5074C20.4865 14.4506 20.5366 14.3817 20.5715 14.3048C20.6063 14.228 20.6251 14.1448 20.6267 14.0604V7.39375C20.6289 7.21054 20.5611 7.03338 20.4373 6.89837C20.3134 6.76335 20.1427 6.68064 19.96 6.66708ZM19.3333 13.3337H13.6133C12.9301 13.3094 12.2845 13.0149 11.8182 12.5149C11.352 12.0149 11.1033 11.3503 11.1267 10.6671C11.1033 9.98384 11.352 9.31923 11.8182 8.81924C12.2845 8.31925 12.9301 8.02475 13.6133 8.00042H19.3333V13.3337Z" fill="#FF7500"/>
                    </svg>
                </span>
                {{ st('cart.payment.cash', 'Наличными') }}
            </span>
        </label>

        {{-- Рахунок-фактура (для юридичних осіб) --}}
        <label class="flex items-center gap-3 cursor-pointer">
            <input type="radio" name="payment_method" value="invoice" class="tp-radio" x-model="paymentMethod" @checked($paymentMethod === 'invoice')>
            <span class="flex items-center gap-3 text-[16px] leading-[22px] text-[#272828]">
                <span class="text-[#FF7500]" aria-hidden="true">
                    {{-- иконка документа/счета --}}
                    <svg width="21" height="19" viewBox="0 0 21 19" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M18.6667 0H2.33333C1.04467 0 0 1.04467 0 2.33333V16.6667C0 17.9553 1.04467 19 2.33333 19H18.6667C19.9553 19 21 17.9553 21 16.6667V2.33333C21 1.04467 19.9553 0 18.6667 0ZM18.6667 16.6667H2.33333V2.33333H18.6667V16.6667ZM15.1667 4.66667H5.83333V7H15.1667V4.66667ZM13.4167 8.5H5.83333V10.8333H13.4167V8.5ZM13.4167 12.3333H5.83333V14.6667H13.4167V12.3333Z" fill="#FF7500"/>
                    </svg>
                </span>
                {{ st('cart.payment.invoice', 'Рахунок-фактура (для юридичних осіб)') }}
            </span>
        </label>

        @error('payment_method')
        <p class="mt-1 text-xs text-red-500">{{ $message }}</p>
        @enderror
    </div>
</div>
