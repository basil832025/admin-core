
@extends('layouts.app')

@section('title', $title ?? 'Блог')

@section('content')
    <div class="mx-auto desk:w-[1198px]  max-w-full px-6">
        {{-- Хлебные крошки --}}
        <nav class="text-sm text-gray-500 my-4">
            <a href="{{ route('home') }}" class="hover:text-gray-700">{{ st('menu.home','Головна') }}</a>
            <span class="mx-2">→</span>
            <span class="text-gray-700">{{$title}}</span>
        </nav>


        <section >
            <h2 class="inline-block font-intro mb-12 text-[40px] md:text-[64px] md:leading-[64px] font-bold text-[#19191A] border-b-2 border-[#FF7500]">
                {{$title}}
            </h2>

        {{-- Если нужны табы по подкатегориям, можно вывести тут список категорий --}}

        {{-- Сетка карточек: 3 на десктопе, 2 на планшете, 1 на мобилке --}}
        <div class="grid gap-6 grid-cols-1 md:grid-cols-2 xl:grid-cols-3 mx-auto">
            @forelse ($posts as $post)
                <x-blog.card :post="$post" :categorySlug="$slug" :showDate="$slug !== 'discounts'" />
            @empty
                <p>Немає публікацій у цій категорії.</p>
            @endforelse
        </div>

        {{-- Пагинация --}}
            {{-- Пагинация как на макете --}}
            @if($posts->hasPages())
                <div class="mt-6 flex items-center justify-center gap-2">
                    <a href="{{ $posts->previousPageUrl() ?? '#' }}" class="w-10 h-10 rounded border flex items-center justify-center {{ $posts->onFirstPage() ? 'pointer-events-none opacity-40' : 'hover:border-[#FF7500]' }}">‹</a>
                    @foreach($posts->getUrlRange(max(1,$posts->currentPage()-2), min($posts->lastPage(),$posts->currentPage()+2)) as $page => $url)
                        <a href="{{ $url }}" class="w-10 h-10 rounded border flex items-center justify-center text-sm {{ $page === $posts->currentPage() ? 'bg-[#FF7500] text-white border-[#FF7500]' : 'hover:border-[#FF7500]' }}">
                            {{ $page }}
                        </a>
                    @endforeach
                    <a href="{{ $posts->nextPageUrl() ?? '#' }}" class="w-10 h-10 rounded border flex items-center justify-center {{ $posts->currentPage()===$posts->lastPage() ? 'pointer-events-none opacity-40' : 'hover:border-[#FF7500]' }}">›</a>
                </div>
            @endif

        </section>
    </div>
@endsection
