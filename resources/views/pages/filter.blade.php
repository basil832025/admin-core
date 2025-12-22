@extends('layouts.app')

@section('title', $title ?? __('Результати фільтру'))

@section('content')
    <div
        x-data="{ filterOpen: false }"
        class="mx-auto max-w-screen-xl px-2 md:px-4 lg:px-0"
    >
        {{-- верхняя панель: кнопка Фільтр + сортировка --}}
        <div class="flex items-center justify-between mb-6">
            {{-- кнопка фильтра (точно как в каталоге) --}}
            <button type="button"
                    @click="filterOpen = true"
                    class="w-10 md:w-[132px] h-10 rounded-[12px] border border-[#E5E7EB] bg-white
                           px-3 inline-flex items-center gap-2 justify-center">
                <img src="{{ asset('images/filter.svg') }}"
                     alt=""
                     class="w-[22px] h-[19px]"
                     aria-hidden="true">
                <span class="hidden md:block font-bold text-[16px] leading-none text-[#19191A]">
                    @lang('Фільтр')
                </span>
            </button>

            {{-- сортировка — тот же компонент, что и на категории --}}
            <x-ui.sort-dropdown />
        </div>

        {{-- модальное окно фильтров (наш общий partial) --}}
        @include('product.filter-panel')

        {{-- Заголовок страницы --}}
        <h1 class="mt-2 text-3xl font-semibold">
            {{ $title ?? __('Результати фільтру') }}
        </h1>

        {{-- Если по фильтру ничего не нашли --}}
        @if($groups->isEmpty())
            <p class="mt-8 text-sm text-gray-500">
                @lang('За вибраними фільтрами товари не знайдено.')
            </p>
        @else
            {{-- Секции по категориям, как на странице каталога --}}
            <section class="max-w-screen-xl mx-auto">
                @foreach($groups as $group)
                    <div class="space-y-14 mt-12">
                        <x-product.section
                            :title="$group['title']"
                            :favoriteIds="$favoriteIds ?? []"
                            :items="$group['items']"
                        />
                    </div>
                @endforeach
            </section>
        @endif
    </div>
@endsection
