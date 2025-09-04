<?php

namespace App\Observers;

use App\Enums\OrderStatus;
use App\Models\Kitchen\KitchenTicket;
use App\Models\Shop\Order;

class OrderObserver
{
    public function saved(Order $order): void
    {
        // вспомогательные поля
        $urgent = (bool) ($order->as_soon_possible ?? false);

        // тип доставки из self_pickup: 1 — pickup, 0 — delivery, null — не определён
        $type = match ((int) ($order->self_pickup ?? -1)) {
            1       => 'pickup',
            0       => 'delivery',
            default => null,
        };

        // если статус НЕ менялся — просто синхронизируем флаги на уже существующем тикете
        $ticket = KitchenTicket::firstWhere('order_id', $order->id);
        if (! $order->wasChanged('status')) {
            if ($ticket) {
                $dirty = false;

                if ($ticket->urgent !== $urgent) {
                    $ticket->urgent = $urgent;
                    $dirty = true;
                }

                if ($ticket->delivery_type !== $type) {
                    $ticket->delivery_type = $type;
                    $dirty = true;
                }

                if ($dirty) {
                    $ticket->save();
                }
            }

            return;
        }

        // ---------- статус действительно изменился ----------

        // 1) Пишем время нового статуса в JSON status_times
        $statusEnum = $order->status instanceof OrderStatus
            ? $order->status
            : OrderStatus::from($order->status);

        $times = $order->status_times ?? [];
        $times[$statusEnum->value] = now()->format('Y-m-d H:i:s');   // перезаписывать/оставлять первое — на ваше усмотрение
        // тихо, без событий, чтобы не вызвать наблюдатель повторно
        $order->forceFill(['status_times' => $times])->saveQuietly();

        // 2) Создаём/обновляем тикет кухни
        if ($statusEnum === OrderStatus::Processing) {
            $ticket = KitchenTicket::firstOrCreate(
                ['order_id' => $order->id],
                [
                    'stage'         => OrderStatus::Processing,
                    'urgent'        => $urgent,
                    'delivery_type' => $type,
                    'processing_at' => $order->created_at ?? now(),
                ],
            );

            // синхронизируем позиции уже ПОСЛЕ наличия тикета
            $ticket->syncItemsFromOrder();
            return;
        }

        // переходы между кухонными стадиями
        if (in_array($statusEnum, [
            OrderStatus::Filling,
            OrderStatus::Molding,
            OrderStatus::Baking,
            OrderStatus::Prepared,
        ], true)) {
            if (! $ticket) {
                // если перескочили Processing
                $ticket = KitchenTicket::create([
                    'order_id'      => $order->id,
                    'stage'         => OrderStatus::Processing,
                    'urgent'        => $urgent,
                    'delivery_type' => $type,
                    'processing_at' => $order->created_at ?? now(),
                ]);
                // и сразу подтягиваем позиции
                $ticket->syncItemsFromOrder();
            }

            // на всякий — актуализируем тип
            if ($ticket->delivery_type !== $type) {
                $ticket->delivery_type = $type;
                $ticket->save();
            }

            $ticket->moveTo($statusEnum, auth()->id());
            return;
        }

        // 3) если ушёл из кухни (OnHold, Cancelled и т.п.) — ничего не делаем
    }
}
