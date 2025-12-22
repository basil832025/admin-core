@extends('layouts.app')

@section('title', 'Оплата замовлення №'.$order->id)

@section('content')
    <div class="mx-auto desk:w-[1208px] p-2 max-w-full">
        <h1 class="text-2xl font-semibold mb-4">
            Оплата замовлення № {{ $order->id }}
        </h1>

        <div class="mb-4 text-[16px]">
            До сплати:
            <strong>{{ number_format($order->grand_total, 2, ',', ' ') }} грн</strong>
        </div>

        <div class="bg-white rounded-xl p-4 shadow">
            {!! $liqpayForm !!}
        </div>

        <p class="mt-4 text-sm text-gray-500">
            Після успішної оплати ви будете автоматично повернуті на сторінку замовлення.
        </p>
    </div>
@endsection
