<?php

namespace App\Observers;

use App\Enums\OrderStatus;
use App\Models\Kitchen\KitchenTicket;
use App\Models\Shop\Order;

class KitchenTicketObserver
{
    public function saved(KitchenTicket $ticket): void
    {
        if (! $ticket->wasChanged('stage')) {
            return;
        }

        // приводим к enum
        $stage = $ticket->stage instanceof OrderStatus
            ? $ticket->stage
            : OrderStatus::from($ticket->stage);

        // интересуют только «кухонные» стадии
        if (! in_array($stage, [
            OrderStatus::Processing,
            OrderStatus::Filling,
            OrderStatus::Molding,
            OrderStatus::Baking,
            OrderStatus::Prepared,
        ], true)) {
            return;
        }

        $order = $ticket->order;
        if (! $order) return;


        // Без событий, чтобы не зациклиться с OrderObserver.
        // ВАЖНО: раз отключаем события, то статус-таймлайн пишем руками.
        Order::withoutEvents(function () use ($order, $stage) {
            $order->status = $stage;

            // обновляем JSON status_times вместо колонок *_at
            $times = (array) ($order->status_times ?? []);
            $times[$stage->value] = now()->toDateTimeString();
            $order->status_times = $times;

            // сохраняем тихо (без событий)
            $order->saveQuietly();
        });
    }
}
