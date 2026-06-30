@extends('layouts.app')

@section('title', st('checkout.payparts.order_title', 'РћРїР»Р°С‚Р° С‡Р°СЃС‚РёРЅР°РјРё Р·Р°РјРѕРІР»РµРЅРЅСЏ в„–') . ' ' . $order->id)

@section('content')
    <div class="mx-auto desk:w-[1208px] max-w-full p-2">
        @php
            $locale = app()->getLocale();
            $checkoutRoute = in_array($locale, ['ru', 'en'], true)
                ? route('localized.checkout', ['locale' => $locale])
                : route('checkout');
            $statusRoute = in_array($locale, ['ru', 'en'], true)
                ? route('localized.checkout.pay.payparts.status', ['locale' => $locale, 'order' => $order])
                : route('checkout.pay.payparts.status', ['order' => $order]);
            $bankName = $bank?->localizedText('name', $locale, 'PrivatBank') ?? 'PrivatBank';
            $saveEmailAction = in_array($locale, ['ru', 'en'], true)
                ? route('localized.checkout.pay.payparts.email', ['locale' => $locale, 'order' => $order])
                : route('checkout.pay.payparts.email', ['order' => $order]);
        @endphp

        <h1 class="mb-4 text-2xl font-semibold">
            {{ st('checkout.payparts.order_title', 'РћРїР»Р°С‚Р° С‡Р°СЃС‚РёРЅР°РјРё Р·Р°РјРѕРІР»РµРЅРЅСЏ в„–') }} {{ $order->id }}
        </h1>

        <div class="mb-4 text-[16px]">
            {{ st('checkout.liqpay.amount_to_pay', 'Р”Рѕ СЃРїР»Р°С‚Рё') }}:
            <strong>{{ number_format($order->grand_total, 2, ',', ' ') }} {{ st('cart.summary.currency_short', 'РіСЂРЅ') }}</strong>
        </div>

        <div class="rounded-xl bg-white p-5 shadow">
            @if ($bank)
                <div class="mb-4 text-sm text-[#6B7280]">
                    {{ st('checkout.payparts.bank', 'Р‘Р°РЅРє') }}:
                    <span class="font-semibold text-[#272828]">{{ $bankName }}</span>
                </div>
            @endif

            @if (session('success'))
                <div class="mb-4 rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800">
                    {{ session('success') }}
                </div>
            @endif

            @if ($error)
                <div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
                    {{ $error }}
                </div>

                <a
                    href="{{ $checkoutRoute }}"
                    class="mt-4 inline-flex h-10 items-center rounded-full border border-gray-300 px-5 text-sm font-semibold text-[#272828]"
                >
                    {{ st('checkout.payparts.back_to_checkout', 'РџРѕРІРµСЂРЅСѓС‚РёСЃСЏ РґРѕ РѕС„РѕСЂРјР»РµРЅРЅСЏ') }}
                </a>
            @elseif ($emailRequired)
                <div class="mb-4 rounded-lg border border-orange-200 bg-orange-50 px-4 py-3 text-sm text-orange-900">
                    {{ st('checkout.liqpay.email_required_before_pay', 'Вкажіть email для надсилання фіскального чека.') }}
                </div>

                <form method="POST" action="{{ $saveEmailAction }}" class="space-y-4">
                    @csrf

                    <div>
                        <label for="contact_email" class="mb-1 block text-sm font-medium text-[#272828]">
                            Email *
                        </label>
                        <input
                            id="contact_email"
                            name="contact_email"
                            type="email"
                            required
                            value="{{ old('contact_email', $clientEmail ?? '') }}"
                            class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm outline-none transition focus:border-[#FF7500]"
                        >
                        @error('contact_email')
                            <div class="mt-1 text-sm text-red-700">{{ $message }}</div>
                        @enderror
                    </div>

                    <button
                        type="submit"
                        class="inline-flex h-11 items-center rounded-full bg-[#FF7500] px-6 text-sm font-semibold text-white transition hover:bg-[#e56700]"
                    >
                        {{ st('checkout.liqpay.save_email_and_continue', 'Зберегти email та продовжити') }}
                    </button>
                </form>
            @elseif ($paymentUrl)
                <p class="mb-3 text-sm text-[#6B7280]">
                    {{ st('checkout.payparts.redirect_hint', 'Р’С–РґРєСЂРёР№С‚Рµ СЃС‚РѕСЂС–РЅРєСѓ РџСЂРёРІР°С‚Р‘Р°РЅРєСѓ РІ РЅРѕРІС–Р№ РІРєР»Р°РґС†С– С‚Р° РїС–РґС‚РІРµСЂРґСЊС‚Рµ РѕРїР»Р°С‚Сѓ С‡Р°СЃС‚РёРЅР°РјРё. Р¦СЋ СЃС‚РѕСЂС–РЅРєСѓ РЅРµ Р·Р°РєСЂРёРІР°Р№С‚Рµ: РјРё РѕС‡С–РєСѓС”РјРѕ РїС–РґС‚РІРµСЂРґР¶РµРЅРЅСЏ РІС–Рґ Р±Р°РЅРєСѓ.') }}
                </p>

                <p id="payparts-waiting-status" class="mb-4 rounded-lg border border-orange-200 bg-orange-50 px-4 py-3 text-sm text-orange-900">
                    {{ st('checkout.payparts.waiting_bank', 'РћС‡С–РєСѓС”РјРѕ РїС–РґС‚РІРµСЂРґР¶РµРЅРЅСЏ РІС–Рґ РџСЂРёРІР°С‚Р‘Р°РЅРєСѓ...') }}
                </p>

                <a
                    id="payparts-pay-button"
                    href="{{ $paymentUrl }}"
                    target="_blank"
                    rel="noopener noreferrer"
                    class="inline-flex h-11 items-center rounded-full bg-[#FF7500] px-6 text-sm font-semibold text-white transition hover:bg-[#e56700]"
                >
                    {{ st('checkout.payparts.go_to_bank', 'Р’С–РґРєСЂРёС‚Рё РџСЂРёРІР°С‚Р‘Р°РЅРє') }}
                </a>
            @else
                <div class="rounded-lg border border-orange-200 bg-orange-50 px-4 py-3 text-sm text-orange-900">
                    {{ st('checkout.payparts.prepare_failed', 'РќРµ РІРґР°Р»РѕСЃСЏ РїС–РґРіРѕС‚СѓРІР°С‚Рё РїРµСЂРµС…С–Рґ РґРѕ Р±Р°РЅРєСѓ. РЎРїСЂРѕР±СѓР№С‚Рµ С‰Рµ СЂР°Р·.') }}
                </div>
            @endif
        </div>

        <p class="mt-4 text-sm text-gray-500">
            {{ st('checkout.payparts.return_after_success', 'РџС–СЃР»СЏ РїС–РґС‚РІРµСЂРґР¶РµРЅРЅСЏ Р±Р°РЅРєСѓ РјРё Р°РІС‚РѕРјР°С‚РёС‡РЅРѕ РІС–РґРєСЂРёС”РјРѕ СЃС‚РѕСЂС–РЅРєСѓ СѓСЃРїС–С€РЅРѕРіРѕ Р·Р°РјРѕРІР»РµРЅРЅСЏ.') }}
        </p>
    </div>

    @if ($paymentUrl)
        @push('scripts')
            <script>
                (function () {
                    const statusUrl = @json($statusRoute);
                    const statusBox = document.getElementById('payparts-waiting-status');
                    let attempts = 0;

                    const poll = function () {
                        attempts += 1;

                        fetch(statusUrl, {
                            headers: {
                                'Accept': 'application/json',
                                'X-Requested-With': 'XMLHttpRequest'
                            }
                        })
                            .then((response) => response.ok ? response.json() : null)
                            .then((data) => {
                                if (!data) {
                                    return;
                                }

                                if (data.success && data.success_url) {
                                    window.location.href = data.success_url;
                                    return;
                                }

                                if (data.failed && statusBox) {
                                    statusBox.className = 'mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800';
                                    statusBox.textContent = @json(st('checkout.payparts.failed_status', 'Р‘Р°РЅРє РЅРµ РїС–РґС‚РІРµСЂРґРёРІ РѕРїР»Р°С‚Сѓ С‡Р°СЃС‚РёРЅР°РјРё. РЎРїСЂРѕР±СѓР№С‚Рµ С‰Рµ СЂР°Р· Р°Р±Рѕ РѕР±РµСЂС–С‚СЊ С–РЅС€РёР№ СЃРїРѕСЃС–Р± РѕРїР»Р°С‚Рё.'));
                                    return;
                                }

                                if (attempts >= 80 && statusBox) {
                                    statusBox.textContent = @json(st('checkout.payparts.long_waiting_bank', 'РџС–РґС‚РІРµСЂРґР¶РµРЅРЅСЏ С‰Рµ РЅРµ РЅР°РґС–Р№С€Р»Рѕ. РЇРєС‰Рѕ РІРё РІР¶Рµ Р·Р°РІРµСЂС€РёР»Рё РѕС„РѕСЂРјР»РµРЅРЅСЏ РІ Р±Р°РЅРєСѓ, Р·Р°С‡РµРєР°Р№С‚Рµ С‰Рµ С‚СЂРѕС…Рё Р°Р±Рѕ РѕРЅРѕРІС–С‚СЊ СЃС‚РѕСЂС–РЅРєСѓ.'));
                                }
                            })
                            .catch(() => {});
                    };

                    poll();
                    window.setInterval(poll, 3000);
                })();
            </script>
        @endpush
    @endif
@endsection
