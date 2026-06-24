@extends('layouts.app')

@section('title', st('checkout.payparts.order_title', 'Оплата частинами замовлення №') . ' ' . $order->id)

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
        @endphp

        <h1 class="mb-4 text-2xl font-semibold">
            {{ st('checkout.payparts.order_title', 'Оплата частинами замовлення №') }} {{ $order->id }}
        </h1>

        <div class="mb-4 text-[16px]">
            {{ st('checkout.liqpay.amount_to_pay', 'До сплати') }}:
            <strong>{{ number_format($order->grand_total, 2, ',', ' ') }} {{ st('cart.summary.currency_short', 'грн') }}</strong>
        </div>

        <div class="rounded-xl bg-white p-5 shadow">
            @if ($bank)
                <div class="mb-4 text-sm text-[#6B7280]">
                    {{ st('checkout.payparts.bank', 'Банк') }}:
                    <span class="font-semibold text-[#272828]">{{ $bankName }}</span>
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
                    {{ st('checkout.payparts.back_to_checkout', 'Повернутися до оформлення') }}
                </a>
            @elseif ($paymentUrl)
                <p class="mb-3 text-sm text-[#6B7280]">
                    {{ st('checkout.payparts.redirect_hint', 'Відкрийте сторінку ПриватБанку в новій вкладці та підтвердьте оплату частинами. Цю сторінку не закривайте: ми очікуємо підтвердження від банку.') }}
                </p>

                <p id="payparts-waiting-status" class="mb-4 rounded-lg border border-orange-200 bg-orange-50 px-4 py-3 text-sm text-orange-900">
                    {{ st('checkout.payparts.waiting_bank', 'Очікуємо підтвердження від ПриватБанку...') }}
                </p>

                <a
                    id="payparts-pay-button"
                    href="{{ $paymentUrl }}"
                    target="_blank"
                    rel="noopener noreferrer"
                    class="inline-flex h-11 items-center rounded-full bg-[#FF7500] px-6 text-sm font-semibold text-white transition hover:bg-[#e56700]"
                >
                    {{ st('checkout.payparts.go_to_bank', 'Відкрити ПриватБанк') }}
                </a>
            @else
                <div class="rounded-lg border border-orange-200 bg-orange-50 px-4 py-3 text-sm text-orange-900">
                    {{ st('checkout.payparts.prepare_failed', 'Не вдалося підготувати перехід до банку. Спробуйте ще раз.') }}
                </div>
            @endif
        </div>

        <p class="mt-4 text-sm text-gray-500">
            {{ st('checkout.payparts.return_after_success', 'Після підтвердження банку ми автоматично відкриємо сторінку успішного замовлення.') }}
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
                                    statusBox.textContent = @json(st('checkout.payparts.failed_status', 'Банк не підтвердив оплату частинами. Спробуйте ще раз або оберіть інший спосіб оплати.'));
                                    return;
                                }

                                if (attempts >= 80 && statusBox) {
                                    statusBox.textContent = @json(st('checkout.payparts.long_waiting_bank', 'Підтвердження ще не надійшло. Якщо ви вже завершили оформлення в банку, зачекайте ще трохи або оновіть сторінку.'));
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
