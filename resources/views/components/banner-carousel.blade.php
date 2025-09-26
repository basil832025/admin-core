<div class="banner-swiper relative desk:h-[320px] md:h-[197px] rounded-2xl ">
    <div class="swiper-wrapper">
        @for($n=1; $n<=5; $n++)
            <div class="swiper-slide">
                <img src="{{ asset("images/baner/$n.png") }}"
                     alt="Банер {{ $n }}" class="w-full md:h-[197px] md:w-[674px] desk:w-[1098px] desk:h-[320px]  object-cover rounded-2xl">
            </div>
        @endfor
    </div>

    {{-- стрелки 40×40, радиус 12, паддинг 8 --}}
    <div class="swiper-button-prev banner-arrow">
        <svg width="24" height="25" viewBox="0 0 24 25" fill="none" xmlns="http://www.w3.org/2000/svg">
            <path d="M15.0901 4.61184L8.57009 11.1318C7.80009 11.9018 7.80009 13.1618 8.57009 13.9318L15.0901 20.4518" fill="#272828"/>
        </svg>

    </div>

    <div class="swiper-button-next banner-arrow">

        <svg width="24" height="25" viewBox="0 0 24 25" fill="none" xmlns="http://www.w3.org/2000/svg">
            <path d="M8.90991 20.4519L15.4299 13.9319C16.1999 13.1619 16.1999 11.9019 15.4299 11.1319L8.90991 4.61188" fill="#272828"/>
        </svg>
    </div>
</div>

<div id="banner-pagination" class="mt-3 flex justify-center"></div>
