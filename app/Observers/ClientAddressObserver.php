<?php
// app/Observers/ClientAddressObserver.php
namespace App\Observers;

use App\Models\Shop\ClientAddress;
use App\Models\Shop\Order;

class ClientAddressObserver
{
    public function updated(ClientAddress $address): void
    {
        $order = Order::where('client_address_id', $address->id)->latest('id')->first();
        if (! $order) return;

        $fields = ['street','house','apartment','entrance','floor','intercom','zip','city','country','type','is_private_house','note'];

        $before = array_intersect_key($address->getOriginal(), array_flip($fields));
        $after  = array_intersect_key($address->getAttributes(), array_flip($fields));

        if ($before != $after) {
            activity('order')
                ->performedOn($order)               // лог на заказ
                ->causedBy(auth()->user())
                ->withProperties([
                    'action'       => 'address_changed',
                    'address_from' => $before,
                    'address_to'   => $after,
                    'order_id'     => $order->id,
                ])
                ->log('Изменение адреса клиента');
        }
    }
}
