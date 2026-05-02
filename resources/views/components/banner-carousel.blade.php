@php
    use App\Models\Banner;
    use App\Models\Setting;

    $locale = app()->getLocale();
    $now = now();
    $delaySeconds = (int) Setting::admin('site.banner_rotation_delay_seconds', 10);
    if ($delaySeconds < 1) $delaySeconds = 10;
    if ($delaySeconds > 120) $delaySeconds = 120;
    $delayMs = $delaySeconds * 1000;

    // Загружаем активные баннеры
    $banners = Banner::query()
        ->where('is_active', true)
        ->where(function ($q) use ($now) {
            $q->whereNull('starts_at')
              ->orWhere('starts_at', '<=', $now);
        })
        ->where(function ($q) use ($now) {
            $q->whereNull('ends_at')
              ->orWhere('ends_at', '>=', $now);
        })
        ->orderBy('sort')
        ->get();

    // Dynamic priority by weekday/time schedule
    $banners = $banners
        ->map(function (Banner $b) use ($now) {
            $b->__activeSchedulePriority = $b->schedulePriorityAt($now);
            return $b;
        })
        ->sort(function (Banner $a, Banner $b) {
            $pa = $a->__activeSchedulePriority;
            $pb = $b->__activeSchedulePriority;

            $aActive = $pa !== null;
            $bActive = $pb !== null;
            if ($aActive !== $bActive) return $aActive ? -1 : 1;

            if ($aActive && $bActive && $pa !== $pb) return ($pa > $pb) ? -1 : 1;

            // fallback to existing sort, then id
            if ((int)$a->sort !== (int)$b->sort) return ((int)$a->sort < (int)$b->sort) ? -1 : 1;
            return ((int)$a->id < (int)$b->id) ? -1 : 1;
        })
        ->values();
@endphp

@if($banners->isNotEmpty())
    <div class="banner-swiper relative desk:h-[320px] md:h-[197px] rounded-2xl" data-autoplay-delay-ms="{{ $delayMs }}">
        <div class="swiper-wrapper">
            @foreach($banners as $banner)
                @php
                    // основная локализованная картинка
                    $imagePath = $banner->getImageForLocale($locale);

                    // mobile: если не задана → использовать основную
                    $mobilePath = $banner->image_mobile ?: $imagePath;

                    // alt
                    $title = $banner->getTranslation('title', $locale)
                        ?? $banner->title
                        ?? 'Банер';
                @endphp

                @if(!$imagePath)
                    @continue
                @endif

                <div class="swiper-slide">
                    @if($banner->url)
                        <a href="{{ $banner->url }}"
                           @if($banner->target === '_blank') target="_blank" rel="noopener" @endif>
                    @endif

                            <picture>
                                @if($mobilePath && $mobilePath !== $imagePath)
                                    <source srcset="{{ asset('storage/' . $mobilePath) }}" media="(max-width: 768px)">
                                @endif

                                <img src="{{ asset('storage/' . $imagePath) }}"
                                     alt="{{ $title }}"
                                     class="w-full md:h-[197px] md:w-[674px] desk:w-[1098px] desk:h-[320px] object-cover rounded-2xl">
                            </picture>

                    @if($banner->url)
                        </a>
                    @endif
                </div>
            @endforeach
        </div>

        {{-- Стрелки --}}
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
@endif
