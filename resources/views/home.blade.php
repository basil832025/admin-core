@extends('layouts.app')

@section('title', 'Доставка осетинських пирогів у Києві')

@section('content')
    <section class="flex items-center justify-center">
             <!-- баннер -->
        <div class="w-[1100px] h-[320px] ">
            <x-banner-carousel />
        </div>

    </section>
    <section class="mb-8 mt-12 flex items-center justify-center">
      <h1 class="text-[65px] leading-[1] font-bold text-[#19191A] text-center">
            Доставка осетинських пирогів у Києві
        </h1>
    </section>

    <div class="catalog-wrap mx-auto w-[1198px] max-w-full px-4">
    <div class="flex items-center justify-between mb-4">
        <button type="button"
                class="w-[132px] h-10 rounded-[12px] border border-[#E5E7EB] bg-white
         px-3 inline-flex items-center gap-2 justify-center">
            <img src="{{ asset('images/filter.svg') }}" alt="" class="w-[22px] h-[19px]" aria-hidden="true">
            <span class="font-bold text-[16px] leading-none text-[#19191A]">Фільтр</span>
        </button>
        <x-ui.sort-dropdown class="mt-4" />
    </div>
    <section class="grid sm:grid-cols-2 lg:grid-cols-3 gap-6">
        <h2 class="text-xl font-semibold">Хіти</h2>
        @forelse($products as $product)
            <x-product-card :product="$product"/>
        @empty
            <div class="col-span-full text-gray-500">Немає товарів.</div>
        @endforelse
    </section>

    <div class="mt-8">
        {{ $products->withQueryString()->links() }}
    </div>
    </div>
@endsection
