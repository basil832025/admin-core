@extends('layouts.app')
@section('title','Мій заказ')

@php
    $addUrl    = route('cart.add');
    $removeUrl = route('cart.remove');
    $client    = auth()->user();
    $addresses = $client ? $client->addresses()->orderByDesc('id')->get() : collect();
    $selectedId = old('selected_address_id') ?: ($addresses->first()->id ?? null);
    $useNewInitial = $selectedId ? false : true;
@endphp

@section('content')
    <div class="mx-auto desk:w-[1208px] p-2 max-w-full">
        <h1 class="text-2xl font-semibold mb-6">{{ st('cart.miy-zakaz', 'Мій заказ') }}</h1>

        <form action="{{ route('checkout.submit') }}" method="POST" class="space-y-6" data-checkout-form >
            @csrf

            <div
                x-data="{
            method: 'delivery',
            useNew: {{ $useNewInitial ? 'true' : 'false' }}
                    }"
                class="mb-6"
            >
                {{-- Переключатель способа получения + hidden --}}
                @include('checkout.partials._shipping-toggle')

                {{-- Весь блок внутри страницы "Мой заказ" --}}
                <div class="flex flex-col xl:flex-row justify-center gap-[32px] mt-6">
                    {{-- Левая колонка (форма) --}}
                    <div class="w-full xl:w-[580px] space-y-6">
                        @include('checkout.partials._contact')
                        @include('checkout.partials._delivery-address')
                        @include('checkout.partials._pickup-locations')
                        @include('checkout.partials._delivery-conditions')
                        @include('checkout.partials._promotions')
                        @include('checkout.partials._payment-methods')
                        @include('checkout.partials._extras')
                    </div>

                    {{-- Правая колонка (корзина, промокод, итоги) --}}
                    <div class="w-full xl:w-[585px] space-y-6">
                        @include('checkout.partials._order-items')
                        @include('checkout.partials._summary')
                        @include('checkout.partials._bonus-earned')
                    </div>
                </div>
            </div>
        </form>
        <style>
            [x-cloak] { display: none !important; }
        </style>

        <div x-data="{ showAuthModal: false, authMessage: '' }"
             x-cloak
             x-show="showAuthModal"
             x-on:show-auth-modal.window="
        authMessage = $event.detail.message || 'Щоб застосувати акцію, увійдіть або зареєструйтесь.';
           authName   = $event.detail.name  || '';
        authPhone  = $event.detail.phone || '';
        showAuthModal = true;
     "
             x-transition.opacity
             class="fixed inset-0 z-[500] flex items-center justify-center bg-black/50 backdrop-blur-sm">

            <div x-show="showAuthModal"
                 x-transition.scale.80
                 class="bg-white rounded-2xl shadow-xl p-6 w-[90%] max-w-[380px] text-center">

                <div class="text-lg font-semibold mb-3">{{ st('cart.potribna-avtoryzatsiya', 'Потрібна авторизація') }}</div>
                <div class="text-sm text-gray-700 mb-6" x-text="authMessage"></div>

                <div class="flex justify-center gap-3">
                    <button
                        type="button"
                        class="h-[40px] w-full rounded-full bg-[#FF7500] text-white
           text-[14px] font-semibold hover:bg-[#e56700] transition"
                        @click="
        // 1) закрываем эту модалку
        showAuthModal = false;

        // 2) подтягиваем имя и телефон из формы чекаута (если заполнены)
        const authName  = document.getElementById('contact_name')?.value || '';
        const authPhone = document.getElementById('contact_phone')?.value || '';

        // 3) открываем основное окно авторизации с уже подставленными данными
        $dispatch('open-auth-modal', {
            tab: 'login',
            name: authName,
            phone: authPhone,
        });
    "
                    >
                        <span>{{ st('auth.login','Увійти') }}</span>
                    </button>




                    <button type="button"
                            @click="showAuthModal = false"
                            class="px-4 py-2 rounded-lg bg-gray-200 text-gray-700 hover:bg-gray-300">
                        {{ st('all.skasuvaty','Скасувати') }}
                    </button>
                </div>
            </div>
        </div>


    </div>
@endsection
