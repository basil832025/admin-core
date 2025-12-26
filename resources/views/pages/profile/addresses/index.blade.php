@extends('layouts.app')

@section('title', st('profile.addresses.title', 'Адреса доставки'))

@section('content')
    <div class="mx-auto desk:w-[1200px] px-4 md:px-6 desk:px-0">
        <h1 class="sr-only md:not-sr-only md:text-[28px] md:leading-8 font-bold text-[#19191A] md:mb-4">
            {{ st('profile.addresses.title', 'Адреса доставки') }}
        </h1>

        <div class="xl:grid xl:grid-cols-[240px,1fr] md:gap-6">
            {{-- Левое меню (desktop) --}}
            <aside class="hidden xl:block">
                @include('pages.menu.profile-menu')
            </aside>

            {{-- Контент --}}
            <main>
                <div class="bg-white rounded-[6px] ring-1 ring-black/10 p-4 md:p-6">
                    {{-- Заголовок и кнопка добавления --}}
                    <div class="flex items-center justify-between mb-6">
                        <h2 class="text-xl font-semibold text-[#19191A]">
                            {{ st('profile.addresses.list', 'Список адресів') }}
                        </h2>
                        <a href="{{ route('profile.addresses.create') }}"
                           class="inline-flex items-center gap-2 px-4 py-2 bg-[#FF7500] text-white rounded-lg hover:bg-orange-600 transition">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                            </svg>
                            {{ st('profile.addresses.add', 'Додати адресу') }}
                        </a>
                    </div>

                    {{-- Сообщение об успехе --}}
                    @if(session('success'))
                        <div class="mb-4 p-4 bg-green-50 text-green-800 rounded-lg">
                            {{ session('success') }}
                        </div>
                    @endif

                    {{-- Список адресов --}}
                    @if($addresses->count() > 0)
                        <div class="space-y-4">
                            @foreach($addresses as $address)
                                @php
                                    $fullLine = trim(
                                        ($address->street
                                            ? st('address.parts.street_prefix', 'вулиця').' '.$address->street
                                            : ''
                                        ) .
                                        ($address->house
                                            ? ', '.st('address.parts.house_short', 'д.').$address->house
                                            : ''
                                        ) .
                                        ($address->apartment
                                            ? ', '.st('address.parts.apartment_short', 'кв. ').$address->apartment
                                            : ''
                                        )
                                    );

                                    $typeLabel = null;
                                    if (!empty($address->type)) {
                                        $map = [
                                            'home'    => st('address.type.home', 'Дім'),
                                            'work'    => st('address.type.work', 'Робота'),
                                            'friends' => st('address.type.friends', 'Друзі'),
                                        ];
                                        $typeLabel = $map[$address->type] ?? $address->type;
                                    }
                                @endphp

                                <div class="border border-gray-200 rounded-lg p-4 hover:shadow-md transition">
                                    <div class="flex items-start justify-between">
                                        <div class="flex-1">
                                            <div class="flex items-center gap-2 mb-2">
                                                <svg class="w-5 h-5 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/>
                                                </svg>
                                                <span class="text-[16px] font-medium text-[#19191A]">
                                                    {{ $fullLine }}
                                                    @if($typeLabel)
                                                        <span class="text-sm text-gray-500">({{ $typeLabel }})</span>
                                                    @endif
                                                </span>
                                            </div>

                                            @if(!empty($address->city))
                                                <p class="text-sm text-gray-600 mb-1">{{ $address->city }}</p>
                                            @endif

                                            <div class="flex flex-wrap gap-4 text-sm text-gray-500 mt-2">
                                                @if($address->floor)
                                                    <span>{{ st('address.form.floor', 'Поверх') }}: {{ $address->floor }}</span>
                                                @endif
                                                @if($address->entrance)
                                                    <span>{{ st('address.form.porch', 'Під\'їзд') }}: {{ $address->entrance }}</span>
                                                @endif
                                                @if($address->intercom)
                                                    <span>{{ st('address.form.intercom', 'Домофон') }}: {{ $address->intercom }}</span>
                                                @endif
                                                @if($address->is_private_house)
                                                    <span class="text-orange-600">{{ st('address.form.private_house', 'Приватний будинок') }}</span>
                                                @endif
                                            </div>

                                            @if($address->note)
                                                <p class="text-sm text-gray-600 mt-2 italic">{{ $address->note }}</p>
                                            @endif
                                        </div>

                                        <div class="flex items-center gap-2 ml-4">
                                            <a href="{{ route('profile.addresses.edit', $address) }}"
                                               class="p-2 text-gray-600 hover:text-[#FF7500] transition"
                                               title="{{ st('profile.addresses.edit', 'Редагувати') }}">
                                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                                                </svg>
                                            </a>
                                            <form action="{{ route('profile.addresses.destroy', $address) }}" method="POST"
                                                  onsubmit="return confirm('{{ st('profile.addresses.delete_confirm', 'Ви впевнені, що хочете видалити цю адресу?') }}');">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit"
                                                        class="p-2 text-gray-600 hover:text-red-600 transition"
                                                        title="{{ st('profile.addresses.delete', 'Видалити') }}">
                                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                                    </svg>
                                                </button>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <div class="text-center py-12">
                            <svg class="w-16 h-16 text-gray-300 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/>
                            </svg>
                            <p class="text-gray-500 mb-4">{{ st('profile.addresses.empty', 'У вас немає збережених адресів') }}</p>
                            <a href="{{ route('profile.addresses.create') }}"
                               class="inline-flex items-center gap-2 px-4 py-2 bg-[#FF7500] text-white rounded-lg hover:bg-orange-600 transition">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                                </svg>
                                {{ st('profile.addresses.add', 'Додати адресу') }}
                            </a>
                        </div>
                    @endif
                </div>
            </main>
        </div>
    </div>
@endsection

