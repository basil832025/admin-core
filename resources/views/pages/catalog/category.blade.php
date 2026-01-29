@extends('layouts.app')

@section('title', 'Доставка осетинських пирогів у Києві')

@section('content')
    <div class=" mx-auto desk:w-[1198px] w-[357px] md:w-[736px] max-w-full">
        <div x-data="{ filterOpen: false }">
        <div class="flex items-center justify-between mb-4">
            <button type="button"
                    @click="filterOpen = true"
                    class="w-10 md:w-[132px] h-10 rounded-[12px] border border-[#E5E7EB] bg-white
                       px-3 inline-flex items-center gap-2 justify-center">
                <img src="{{ asset('images/filter.svg') }}" alt="" class="w-[22px] h-[19px]" aria-hidden="true">
                <span class="hidden md:block font-bold text-[16px] leading-none text-[#19191A]">
                 {{ st('all.filter','Фільтр') }}
            </span>
            </button>
            <x-ui.sort-dropdown  />
        </div>

        <section class="max-w-screen-xl mx-auto ">
            @foreach($categorySections as $section)
               <div class="space-y-14 mt-4">
                    <x-product.section :title="$section['title']"
                                       :favoriteIds="$favoriteIds ?? []"
                                       :items="$section['items']" />
            </div>
            @endforeach
        </section>
            {{-- Окно фильтра --}}
            @include('product.filter-panel')
    </div>
    </div>
@endsection
