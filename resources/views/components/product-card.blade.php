@props(['product'])
@php
 //   dd($product->toArray(),$product->image_url,$product->display_name,$product->description);
@endphp
<article class="bg-white rounded-2xl shadow-sm border hover:shadow-md transition overflow-hidden">
    <a href="{{ route('product.show', $product) }}" class="block relative aspect-[4/3] bg-gray-100">
        <img src="{{ $product->image_url }}" alt="{{ $product->display_name }}" class="w-full h-full object-cover">
    </a>

    <div class="p-4">
        <a href="{{ route('product.show', $product) }}" class="block">
            <h3 class="font-semibold text-lg leading-tight">{{ $product->display_name }}</h3>
        </a>

        @php $desc = data_get($product->description, app()->getLocale()) @endphp
        @if($desc)
            <p class="text-sm text-gray-600 mt-1 line-clamp-2">{{ $desc }}</p>
        @endif

        <div class="mt-4 flex items-center justify-between">
            <div class="text-lg font-bold">
                {{ number_format($product->unit_price, 0, ',', ' ') }} грн
            </div>

            <button type="button"
                    class="inline-flex items-center justify-center h-10 px-4 rounded-xl bg-orange-500 text-white text-sm hover:bg-orange-600">
                У кошик
            </button>
        </div>
    </div>
</article>
