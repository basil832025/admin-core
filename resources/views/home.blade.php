@extends('layouts.app')

@section('title', 'Доставка осетинських пирогів у Києві')

@section('content')
    <section class="flex items-center justify-center">
             <!-- баннер -->
        <div class="desk:h-[320px] md:h-[197px]  desk:w-[1100px] md:w-[674px] ">
            <x-banner-carousel />
        </div>

    </section>
    <section class="mb-8 mt-12 flex items-center justify-center">
      <h1 class="desk:text-[65px] md:text-[56px] text-[41px] leading-[1] font-bold text-[#19191A] text-center">
            Доставка осетинських пирогів у Києві
        </h1>
    </section>

        <div class=" mx-auto desk:w-[1198px] w-[357px] md:w-[736px] max-w-full md:mx-4">
    <div class="flex items-center justify-between mb-4">
        <button type="button"
                class="w-10 md:w-[132px] h-10 rounded-[12px] border border-[#E5E7EB] bg-white
         px-3 inline-flex items-center gap-2 justify-center">
            <img src="{{ asset('images/filter.svg') }}" alt="" class="w-[22px] h-[19px]" aria-hidden="true">
            <span class="hidden md:block font-bold text-[16px] leading-none text-[#19191A]">Фільтр</span>
        </button>
        <x-ui.sort-dropdown  />
    </div>
        <section class="max-w-screen-xl mx-auto ">
            <div class="space-y-14"> {{-- вертикальные интервалы между блоками --}}
                <x-product.section
                    title="Хіти"
                    :items="[
        ['title'=>'Пиріг з курячим філе, сиром сулугуні та свіжою зеленню','price_no_sale'=>'299.99', 'price'=>'249.99', 'image'=>'/images/products/1.png'],
        ['title'=>'Пиріг із індичкою, печерицями, осетинським сиром та свіжою зеленню', 'price'=>'120', 'image'=>'/images/products/2.png'],
        ['title'=>'Пиріг із індичкою, печерицями, осетинським сиром та свіжою зеленню', 'price'=>'250', 'image'=>'/images/products/3.png'],
        ['title'=>'Пиріг із індичкою, печерицями, осетинським сиром та свіжою зеленню', 'price'=>'250', 'image'=>'/images/products/4.png'],
        ['title'=>'Пиріг із індичкою, печерицями, осетинським сиром та свіжою зеленню', 'price'=>'250', 'image'=>'/images/products/5.png'],
        ['title'=>'Пиріг із індичкою, печерицями, осетинським сиром та свіжою зеленню', 'price'=>'250', 'image'=>'/images/products/6.png'],
        // ... до 6
      ]"

                />
            </div>
            <div class="space-y-14 mt-12"> {{-- вертикальные интервалы между блоками --}}
                <x-product.section
                    title="Новинки"
                    :items="[
        ['title'=>'Пиріг з курячим філе, сиром сулугуні та свіжою зеленню','price_no_sale'=>'299.99', 'price'=>'249.99', 'image'=>'/images/products/1.png'],
        ['title'=>'Пиріг із індичкою, печерицями, осетинським сиром та свіжою зеленню', 'price'=>'120', 'image'=>'/images/products/2.png'],
        ['title'=>'Пиріг із індичкою, печерицями, осетинським сиром та свіжою зеленню', 'price'=>'250', 'image'=>'/images/products/3.png'],
        ['title'=>'Пиріг із індичкою, печерицями, осетинським сиром та свіжою зеленню', 'price'=>'250', 'image'=>'/images/products/4.png'],
        ['title'=>'Пиріг із індичкою, печерицями, осетинським сиром та свіжою зеленню', 'price'=>'250', 'image'=>'/images/products/5.png'],
        ['title'=>'Пиріг із індичкою, печерицями, осетинським сиром та свіжою зеленню', 'price'=>'250', 'image'=>'/images/products/6.png'],
        // ... до 6
      ]"

                />
            </div>
    </section>
        {{-- статья на главной --}}
        <section class="grid grid-cols-1 lg:grid-cols-[minmax(280px,500px),1fr] mt-[120px] gap-8 lg:gap-[32px] bg-white overflow-hidden">
            <!-- Картинка -->
            <div >
                <img src="/images/products/home.png"
                     alt="Осетинський пиріг"
                     class=" w-full h-full desk:w-[584px] md:w-[736px]" />
            </div>

            <!-- Текстовый блок -->
            <div class="desk:p-[30px] lg:p-[50px] flex flex-col desk:mt-6 lg:mt-12">
                <h2 class="text-[40px]  leading-tight font-bold">
                    Осетинські пироги <span class="text-[#FF7500]">у Києві</span>
                </h2>

                <p class="text-[15px] md:text-[16px] mt-4 leading-[22px] font-normal text-[#333333]">
                    Пироги зустрічаються в будь-якій кухні світу і є універсальною та смачною стравою. Ми пропонуємо вашій увазі неймовірно смачні пироги, а ще щось особливе – осетинські пироги – чудова альтернатива новомодному фаст-фуду та піци, які останнім часом посіли своє знакове місце у сучасній культурі їжі Києва....
                </p>

                <div class="flex mt-6 text-[16px] text-[#666666] justify-between">
                    <span>20 травня 2024</span>
                    <a href="#" class="text-[#FF7500] font-medium">Читати далі</a>
                </div>
            </div>
        </section>




    </div>
@endsection
