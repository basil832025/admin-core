@props([
'title' => 'Товар',
'url' => '',
'price' => '0.00',
'description' => '',
'article' => null,

'productId' => null,     // может прийти из презентера (id базового продукта)
'root_id'   => null,     // если презентер отдаёт root_id
'isFavorite' => false,

'price_no_sale' => null,
'image' => '/images/no-image.svg',
'characteristics' => [],
'rows' => [],
])

@php
    // ЕДИНЫЙ ID, по которому ставим/снимаем избранное
    $pid = $productId ?? $root_id ?? ($rows[0]['product_id'] ?? null);
    
    // Подготовка карты цен для всех вариантов
    $priceMap = [];
    $initialDiscount = null;
    $initialProductId = $pid ? (string)$pid : ($rows[0]['product_id'] ?? '');
    
    // ДИАГНОСТИКА: Логируем входные данные
    $debugInfo = [
        'pid' => $pid,
        'initialProductId' => $initialProductId,
        'price_no_sale' => $price_no_sale,
        'price' => $price,
        'rows_count' => count($rows ?? []),
        'rows_data' => [],
    ];
    
    // Проверяем все варианты на наличие старой цены
    if (!empty($rows)) {
        foreach ($rows as $row) {
            $rowId = (string)($row['product_id'] ?? '');
            $rowPrice = (float)($row['price'] ?? 0);
            // Проверяем old_price более тщательно
            $rowOldPrice = null;
            if (isset($row['old_price'])) {
                $oldPriceVal = $row['old_price'];
                if ($oldPriceVal !== null && $oldPriceVal !== '' && $oldPriceVal !== 0) {
                    $rowOldPrice = (float)$oldPriceVal;
                }
            }
            
            $priceMap[$rowId] = [
                'price' => $rowPrice,
                'old'   => $rowOldPrice,
            ];
            
            // ДИАГНОСТИКА: Сохраняем данные варианта
            $debugInfo['rows_data'][] = [
                'id' => $rowId,
                'price' => $rowPrice,
                'old_price' => $rowOldPrice,
                'raw_old_price' => $row['old_price'] ?? 'NOT SET',
                'raw_row_keys' => array_keys($row), // ДИАГНОСТИКА: какие ключи есть в $row
                'raw_row_full' => $row, // ДИАГНОСТИКА: полные данные варианта
            ];
            
            // Рассчитываем скидку только для начального варианта
            if ($rowId === $initialProductId && $rowOldPrice && $rowOldPrice > 0 && $rowPrice && $rowPrice > 0 && $rowOldPrice > $rowPrice) {
                $discount = round((($rowOldPrice - $rowPrice) / $rowOldPrice) * 100);
                $initialDiscount = $discount;
            }
        }
    }
    
    // Если не нашли скидку в вариантах, используем значения из пропсов (для обратной совместимости)
    if ($initialDiscount === null && $price_no_sale && $price_no_sale > 0 && $price && $price > 0) {
        $oldPrice = (float)$price_no_sale;
        $currentPrice = (float)$price;
        if ($oldPrice > $currentPrice) {
            $initialDiscount = round((($oldPrice - $currentPrice) / $oldPrice) * 100);
        }
    }
    
    $debugInfo['initialDiscount'] = $initialDiscount;
    $debugInfo['priceMap'] = $priceMap;
@endphp

<!-- ДИАГНОСТИКА: Product Card Debug Info -->
<!--
DEBUG INFO:
PID: {{ $pid }}
Initial Product ID: {{ $initialProductId }}
Price: {{ $price }}
Price No Sale: {{ $price_no_sale }}
Initial Discount: {{ $initialDiscount ?? 'null' }}
Rows Count: {{ count($rows ?? []) }}
Price Map: {{ json_encode($priceMap, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) }}
Rows Data:
@foreach($rows ?? [] as $row)
- ID: {{ $row['product_id'] ?? 'N/A' }}, Price: {{ $row['price'] ?? 'N/A' }}, Old Price: {{ $row['old_price'] ?? 'null' }}, Raw Old Price: {{ var_export($row['old_price'] ?? null, true) }}
@endforeach
-->

<article
    x-data="{ 
        prices: @js($priceMap), 
        discountPercent: @js($initialDiscount ?? null), 
        rootId: @js($pid), 
        init() { 
            const initialProductId = @js($pid ? (string)$pid : ($rows[0]['product_id'] ?? ''));
            if (initialProductId) { 
                this.updateDiscount(initialProductId); 
            }
            this.$nextTick(() => { 
                const rowsSelectorContainer = this.$el.querySelector('.rows-selector-container'); 
                if (rowsSelectorContainer) { 
                    const rowsSelector = rowsSelectorContainer.querySelector('[x-data]'); 
                    if (rowsSelector && window.Alpine) { 
                        try {
                            const selectorData = window.Alpine.$data(rowsSelector); 
                            if (selectorData && typeof selectorData.$watch === 'function') { 
                                selectorData.$watch('selected', (newVal) => { 
                                    if (newVal) { this.updateDiscount(String(newVal)); } 
                                }); 
                                if (selectorData.selected) { this.updateDiscount(String(selectorData.selected)); } 
                            } 
                        } catch(e) {
                            // Silent error handling
                        }
                    } 
                } 
            }); 
        }, 
        updateDiscount(productId) { 
            if (!productId) { 
                this.discountPercent = null; 
                return; 
            } 
            const priceData = this.prices[String(productId)]; 
            if (!priceData) { 
                this.discountPercent = null; 
                return; 
            } 
            const oldPrice = priceData.old; 
            const currentPrice = priceData.price; 
            
            if (oldPrice !== null && oldPrice !== undefined && oldPrice > 0 && currentPrice && currentPrice > 0 && oldPrice > currentPrice) { 
                this.discountPercent = Math.round(((oldPrice - currentPrice) / oldPrice) * 100); 
            } else { 
                this.discountPercent = null; 
            } 
        }, 
        handleVariantSelected(event) { 
            if (event && event.detail && event.detail.productId) { 
                const productId = String(event.detail.productId); 
                if (this.prices[productId]) { this.updateDiscount(productId); } 
            } 
        } 
    }"
    @variant-selected="handleVariantSelected($event)"
    class="desk:w-[378px] md:w-[360px] w-[355px] md:h-[650px] flex flex-col rounded-[12px] bg-white desk:gap-8 md:gap-4 p-3 shadow-[0_8px_20px_rgba(0,0,0,0.05)]"
    @if($pid) data-product-id="{{ $pid }}" @endif
>
    <a href="{{ $url }}">
        <div class="relative desk:w-[354px] h-[220px] md:w-[336px] w-[331px] overflow-hidden rounded-[12px]">
            <img src="{{ $image }}" alt="{{ $title }}" class="h-full w-full object-cover">
            <span 
                x-show="discountPercent !== null && discountPercent > 0"
                x-text="'Знижка –' + discountPercent + '%'"
                x-cloak
                class="absolute right-[10px] top-[10px] rounded-[3px] bg-[#B91C1C] px-[10px] py-[4px] text-white font-intro font-bold text-[14px] leading-[16px] z-10">
            </span>
        </div>
    </a>

    <div class="pt-4 flex-1 flex flex-col">
        <div class="flex items-start justify-between">
            <h5 class="w-[312px] font-intro font-bold text-[16px] leading-[22px] text-neutral-700">
                {{ $title }}
            </h5>

            @if($pid)
                    <x-ui.favorite-button
                    :product-id="$pid"
                    :post-url="route('favorite.toggle', $pid)"
                    :active="$isFavorite"
                    color="#FF7500"
                />
            @endif
        </div>

        <p class="w-[354px] font-intro text-[13px] leading-[16px] text-[#C04103]">
            {{ st('product.sku_label', 'Артикул') }}: {{ $article ?? '123456' }}
        </p>

        <div class="w-[354px] font-intro text-[13px] leading-[16px] text-[#A9A9A9] clamp-6 max-h-[96px] overflow-hidden">
            {!! $description !!}
        </div>

        @if(!empty($rows))
            <div class="md:mt-auto rows-selector-container">
                <x-ui.rows-selector
                    :rows="$rows"
                    :characteristics="$characteristics"
                    :root-id="$pid"
                    :default-price="$price"
                    :default-old-price="$price_no_sale"
                    cart-text="{{ st('product.addcart','Додати в кошик') }}"
                />
            </div>
        @endif
    </div>
</article>
