@php
    $record = $getRecord();
    $items = ($record?->items ?? collect())->sortBy('id');
    $orderKitchenNote = trim((string) ($record?->notes ?? ''));
    $visibleBlocksLimit = 3;

    $formatQty = static fn ($qty): string => rtrim(rtrim(number_format((float) $qty, 2, '.', ' '), '0'), '.');
    $itemTitle = static function ($item): string {
        $meta = is_array($item->meta ?? null) ? $item->meta : [];
        $labelParts = collect(preg_split('/\s*·\s*/u', (string) data_get($meta, 'cart_label', '')))
            ->filter()
            ->values();

        return trim((string) (
            data_get($meta, 'name')
            ?? data_get($meta, 'title')
            ?? ($labelParts->count() >= 3 ? $labelParts->get(1) : null)
            ?? $item->product?->display_name
            ?? '—'
        ));
    };
    $itemVolume = static function ($item): string {
        $meta = is_array($item->meta ?? null) ? $item->meta : [];
        $volume = trim((string) data_get($meta, 'volume', ''));

        if ($volume === '' && ! empty($meta['cart_label'])) {
            preg_match('/\d+(?:[.,]\d+)?\s*(?:мл|ml)/iu', (string) $meta['cart_label'], $matches);
            $volume = trim((string) ($matches[0] ?? ''));
        }

        if ($volume === '') {
            $snapshotLabel = trim((string) data_get($item->product_snapshot ?? [], 'menu_measure_label', ''));
            if ($snapshotLabel !== '') {
                $volume = $snapshotLabel;
            }
        }

        if ($volume === '' && $item->product?->relationLoaded('unit') && $item->product?->unit) {
            $unitCode = trim((string) $item->product->unit->code);
            if ($unitCode !== '' && $unitCode !== 'sht') {
                $unitLabel = $item->product->unit->getTranslation('short_name', app()->getLocale(), false)
                    ?: $item->product->unit->getTranslation('name', app()->getLocale(), false)
                    ?: $unitCode;
                $quantity = (float) ($item->product->price_unit_quantity ?? 1);

                if ($unitCode === 'ml' && (float) $item->qty > 1 && $quantity <= 1) {
                    $quantity = (float) $item->qty;
                }

                $quantityLabel = rtrim(rtrim(number_format($quantity, 3, '.', ''), '0'), '.');
                $volume = ($quantityLabel ?: '1') . ' ' . $unitLabel;
            }
        }

        return $volume;
    };
    $isDiscoveryItem = static fn ($item): bool => (bool) data_get($item->meta, 'discovery_53')
        && filled(data_get($item->meta, 'discovery_set_id'));
    $pluralAromas = static function (int $count): string {
        $lastTwo = $count % 100;
        $last = $count % 10;

        if ($lastTwo >= 11 && $lastTwo <= 14) {
            return 'ароматів';
        }

        return match ($last) {
            1 => 'аромат',
            2, 3, 4 => 'аромати',
            default => 'ароматів',
        };
    };

    $sets = $items
        ->filter($isDiscoveryItem)
        ->groupBy(fn ($item): string => (string) data_get($item->meta, 'discovery_set_id'));
    $seenSetIds = [];
    $blocks = $items
        ->map(function ($item) use ($sets, &$seenSetIds, $isDiscoveryItem) {
            if (! $isDiscoveryItem($item)) {
                return ['type' => 'regular', 'item' => $item];
            }

            $setId = (string) data_get($item->meta, 'discovery_set_id');
            if (isset($seenSetIds[$setId])) {
                return null;
            }

            $seenSetIds[$setId] = true;

            return [
                'type' => 'discovery',
                'id' => $setId,
                'items' => ($sets->get($setId) ?? collect())->sortBy('id')->values(),
            ];
        })
        ->filter()
        ->values();
@endphp

@if ($blocks->isEmpty())
    <span class="text-gray-400">вЂ”</span>
@else
    <div class="callcenter-order-items-inline" x-data="{ openSets: {}, showAllItems: false }">
        @foreach ($blocks as $index => $block)
            @php
                $isHiddenBlock = $index >= $visibleBlocksLimit;
            @endphp

            @if ($block['type'] === 'discovery')
                @php
                    $setItems = $block['items'];
                    $setId = (string) $block['id'];
                    $setQty = (float) max(1, $setItems->min('qty') ?? 1);
                    $volume = $itemVolume($setItems->first()) ?: '3 мл';
                    $previewItems = $setItems->take(3);
                    $remainingItemsCount = max(0, $setItems->count() - $previewItems->count());
                    $previewText = $previewItems
                        ->map(fn ($setItem): string => $itemTitle($setItem))
                        ->filter()
                        ->implode(', ');
                @endphp

                <div
                    class="callcenter-order-items-inline-block is-discovery"
                    @if ($isHiddenBlock) x-show="showAllItems" x-cloak @endif
                >
                    <button
                        type="button"
                        class="callcenter-order-items-inline-set-head"
                        x-on:click.stop.prevent="openSets[@js($setId)] = ! openSets[@js($setId)]"
                    >
                        <span class="callcenter-order-items-inline-main">
                            <span class="callcenter-order-items-inline-toggle" x-text="openSets[@js($setId)] ? '-' : '+'">+</span>
                            <span class="callcenter-order-items-inline-title">DISCOVERY 53</span>
                            <span class="callcenter-order-items-inline-badge">СЕТ</span>
                            <span class="callcenter-order-items-inline-volume">{{ $volume }}</span>
                        </span>
                        <span class="callcenter-order-items-inline-qty">×{{ $formatQty($setQty) }}</span>
                    </button>

                    <div class="callcenter-order-items-inline-preview" x-show="! openSets[@js($setId)]">
                        <span>{{ $previewText }}</span>
                        @if ($remainingItemsCount > 0)
                            <span class="callcenter-order-items-inline-more">+{{ $remainingItemsCount }} {{ $pluralAromas($remainingItemsCount) }}</span>
                        @endif
                    </div>

                    <div class="callcenter-order-items-inline-children" x-show="openSets[@js($setId)]" x-cloak>
                        @foreach ($setItems as $setItem)
                            <div class="callcenter-order-items-inline-child is-expanded">
                                <span>{{ $itemTitle($setItem) }}</span>
                                @if ((float) $setItem->qty !== $setQty)
                                    <span class="callcenter-order-items-inline-child-qty">×{{ $formatQty($setItem->qty) }}</span>
                                @endif
                            </div>
                        @endforeach
                    </div>
                </div>
            @else
                @php
                    $item = $block['item'];
                    $title = $itemTitle($item);
                    $volume = $itemVolume($item);
                    $qty = $formatQty($item->qty);
                    $kitchenNote = trim((string) ($item->kitchen_note ?? ''));
                @endphp

                <div
                    class="callcenter-order-items-inline-block is-regular"
                    @if ($isHiddenBlock) x-show="showAllItems" x-cloak @endif
                >
                    <div class="callcenter-order-items-inline-regular-head">
                        <span class="callcenter-order-items-inline-main">
                            <span class="callcenter-order-items-inline-title">{{ $title }}</span>
                            @if ($volume !== '')
                                <span class="callcenter-order-items-inline-volume">{{ $volume }}</span>
                            @endif
                        </span>
                        <span class="callcenter-order-items-inline-qty">×{{ $qty }}</span>
                    </div>

                    @if ($kitchenNote !== '')
                        <div class="mt-1 block max-w-full rounded px-1.5 py-0.5 text-[10px] font-medium leading-snug break-words whitespace-normal" style="background:#fef08a;color:#713f12;overflow-wrap:anywhere;">
                            РџСЂРёРјС–С‚РєР°: {{ $kitchenNote }}
                        </div>
                    @endif
                </div>
            @endif
        @endforeach

        @if ($blocks->count() > $visibleBlocksLimit)
            <button
                type="button"
                class="callcenter-order-items-inline-show-more"
                x-on:click.stop.prevent="showAllItems = ! showAllItems"
                x-text="showAllItems ? 'Згорнути позиції' : 'Розгорнути ще {{ $blocks->count() - $visibleBlocksLimit }}'"
            >
                Розгорнути ще {{ $blocks->count() - $visibleBlocksLimit }}
            </button>
        @endif

        @if ($orderKitchenNote !== '')
            <div class="mt-1.5 block max-w-full rounded px-2 py-1 text-[10px] font-semibold leading-snug break-words whitespace-normal" style="background:#fde047;color:#713f12;overflow-wrap:anywhere;">
                {{ $orderKitchenNote }}
            </div>
        @endif
    </div>
@endif

