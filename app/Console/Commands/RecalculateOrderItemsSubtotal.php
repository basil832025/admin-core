<?php

namespace App\Console\Commands;

use App\Models\Shop\OrderItem;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class RecalculateOrderItemsSubtotal extends Command
{
    protected $signature = 'orders:recalculate-subtotal 
                            {--order-id= : Recalculate for specific order ID only}
                            {--force : Force recalculation even if subtotal exists}';

    protected $description = 'Recalculate subtotal and total for order items based on unit_price and qty';

    public function handle()
    {
        $orderId = $this->option('order-id');
        $force = $this->option('force');

        $query = OrderItem::query();
        
        if ($orderId) {
            $query->where('shop_order_id', $orderId);
            $this->info("Recalculating for order ID: {$orderId}");
        } else {
            $this->info("Recalculating for all order items");
        }

        if (!$force) {
            // Только те, у которых subtotal пустой или равен 0
            $query->where(function($q) {
                $q->whereNull('subtotal')
                  ->orWhere('subtotal', 0)
                  ->orWhereNull('total')
                  ->orWhere('total', 0);
            });
        }

        $items = $query->get();
        $count = 0;

        $this->info("Found {$items->count()} items to process");

        foreach ($items as $item) {
            if (empty($item->unit_price) || $item->unit_price == 0) {
                $this->warn("Skipping item ID {$item->id}: unit_price is 0 or empty");
                continue;
            }

            $qty = (int)($item->qty ?? 1);
            $unitPrice = (float)$item->unit_price;
            $subtotal = $qty * $unitPrice;

            // Если total пустой, вычисляем его
            $total = $item->total ?? $subtotal;

            $item->subtotal = $subtotal;
            if (empty($item->total)) {
                $item->total = $total;
            }

            $item->save();
            $count++;

            if ($count % 100 == 0) {
                $this->info("Processed {$count} items...");
            }
        }

        $this->info("Successfully recalculated {$count} order items");
        return 0;
    }
}

