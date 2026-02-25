@php
    $destinationAddress = trim((string) ($address ?? ''));
    $destinationLatValue = $destinationLat !== null ? (string) $destinationLat : '';
    $destinationLngValue = $destinationLng !== null ? (string) $destinationLng : '';
    $kitchenLatValue = $kitchenLat !== null ? (string) $kitchenLat : '';
    $kitchenLngValue = $kitchenLng !== null ? (string) $kitchenLng : '';
    $kitchenAddressValue = trim((string) ($kitchenAddress ?? ''));
    $tRouteNotAvailable = __('logistics.actions.route_not_available');
    $tRouteOriginAuto = __('logistics.actions.route_origin_auto');
    $tRouteOriginKitchen = __('logistics.actions.route_origin_kitchen');
    $tRouteOriginManual = __('logistics.actions.route_origin_manual');
    $tRouteDestinationMissing = __('logistics.actions.route_destination_missing');
    $tRouteOriginGps = __('logistics.actions.route_origin_gps');
@endphp

<div id="logistics-route-map-{{ $recordId }}"
     data-logistics-route-map="1"
     data-google-key="{{ $googleMapsKey }}"
     data-destination-address="{{ $destinationAddress }}"
     data-destination-lat="{{ $destinationLatValue }}"
     data-destination-lng="{{ $destinationLngValue }}"
     data-kitchen-lat="{{ $kitchenLatValue }}"
     data-kitchen-lng="{{ $kitchenLngValue }}"
     data-kitchen-address="{{ $kitchenAddressValue }}"
     data-t-route-not-available="{{ $tRouteNotAvailable }}"
     data-t-route-origin-auto="{{ $tRouteOriginAuto }}"
     data-t-route-origin-kitchen="{{ $tRouteOriginKitchen }}"
     data-t-route-origin-manual="{{ $tRouteOriginManual }}"
     data-t-route-destination-missing="{{ $tRouteDestinationMissing }}"
     data-t-route-origin-gps="{{ $tRouteOriginGps }}"
     class="space-y-3"
>
    <div class="rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-700">
        <div class="font-semibold">{{ __('logistics.actions.destination') }}</div>
        <div>{{ $destinationAddress !== '' ? $destinationAddress : '—' }}</div>
    </div>

    <div class="rounded-lg overflow-hidden border border-slate-200 bg-white" style="height: 460px;">
        <iframe
            data-role="route-frame"
            class="h-full w-full"
            loading="lazy"
            referrerpolicy="no-referrer-when-downgrade"
            src="about:blank"
        ></iframe>
    </div>

    <div class="flex items-center justify-between gap-3 text-sm">
        <span data-role="route-status" class="text-slate-600">{{ __('logistics.actions.route_loading') }}</span>
        <a data-role="route-link" href="#" target="_blank" class="text-primary-600 hover:underline">
            {{ __('logistics.actions.open_in_google_maps') }}
        </a>
    </div>
</div>
