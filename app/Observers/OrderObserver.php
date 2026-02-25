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
        $priority = $urgent ? 30 : 100;

        // тип доставки из self_pickup: 1 — pickup, 0 — delivery, null — не определён
        $type = match ((int) ($order->self_pickup ?? -1)) {
            1       => 'pickup',
            0       => 'delivery',
            default => null,
        };

        $statusEnum = $this->resolveStatusEnum($order);

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

                return;
            }

            if ($statusEnum && $this->isKitchenStage($statusEnum)) {
                $ticket = KitchenTicket::create([
                    'order_id'      => $order->id,
                    'stage'         => OrderStatus::Processing,
                    'urgent'        => $urgent,
                    'delivery_type' => $type,
                    'priority'      => $priority,
                    'processing_at' => $order->created_at ?? now(),
                ]);

                $ticket->syncItemsFromOrder();

                if ($statusEnum !== OrderStatus::Processing) {
                    $ticket->moveTo($statusEnum, auth('admin')->id());
                }
            }

            return;
        }

        // ---------- статус действительно изменился ----------

        // 1) Пишем время нового статуса в JSON status_times
        if (! $statusEnum) {
            return;
        }

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
                    'priority'      => $priority,
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
                    'priority'      => $priority,
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

    private function resolveStatusEnum(Order $order): ?OrderStatus
    {
        try {
            if ($order->status instanceof OrderStatus) {
                return $order->status;
            }

            $status = (string) $order->status;
            $enum = OrderStatus::tryFrom($status);
            if ($enum) {
                return $enum;
            }
        } catch (\Throwable $e) {
            // fallback to raw status below
        }

        $raw = mb_strtolower(trim((string) $order->getRawOriginal('status')));

        return match ($raw) {
            'на кухне', 'на кухні', 'в обработке', 'в обробці' => OrderStatus::Processing,
            default => OrderStatus::tryFrom($raw),
        };
    }

    private function isKitchenStage(OrderStatus $status): bool
    {
        return in_array($status, [
            OrderStatus::Processing,
            OrderStatus::Filling,
            OrderStatus::Molding,
            OrderStatus::Baking,
            OrderStatus::Prepared,
        ], true);
    }
}
