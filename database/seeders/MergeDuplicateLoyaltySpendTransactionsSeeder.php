<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class MergeDuplicateLoyaltySpendTransactionsSeeder extends Seeder
{
    public function run(): void
    {
        if (
            ! Schema::hasTable('bs_loyalty_transactions')
            || ! Schema::hasTable('bs_shop_orders')
        ) {
            return;
        }

        $groups = [];

        DB::table('bs_loyalty_transactions')
            ->where('type', 'spend')
            ->where('source', 'order')
            ->whereNotNull('order_id')
            ->orderBy('created_at')
            ->orderBy('id')
            ->get()
            ->each(function ($transaction) use (&$groups): void {
                $meta = json_decode((string) $transaction->meta, true);
                $accrualId = is_array($meta) ? ($meta['accrual_id'] ?? null) : null;

                if ($accrualId === null) {
                    return;
                }

                $key = implode('|', [
                    $transaction->account_id,
                    $transaction->order_id,
                    $accrualId,
                ]);

                $groups[$key][] = $transaction;
            });

        $affectedOrderIds = [];

        foreach ($groups as $transactions) {
            if (count($transactions) < 2) {
                continue;
            }

            $first = $transactions[0];
            $last = $transactions[array_key_last($transactions)];
            $duplicateIds = collect($transactions)
                ->skip(1)
                ->pluck('id')
                ->all();

            $amount = round(array_reduce(
                $transactions,
                fn (float $carry, object $transaction): float => $carry + (float) $transaction->amount,
                0.0
            ), 2);

            DB::table('bs_loyalty_transactions')
                ->where('id', $first->id)
                ->update([
                    'amount' => $amount,
                    'balance_after' => $last->balance_after,
                    'updated_at' => now(),
                ]);

            DB::table('bs_loyalty_transactions')
                ->whereIn('id', $duplicateIds)
                ->delete();

            $affectedOrderIds[(int) $first->order_id] = true;
        }

        foreach (array_keys($affectedOrderIds) as $orderId) {
            $this->syncOrderBonusTotals((int) $orderId);
        }
    }

    private function syncOrderBonusTotals(int $orderId): void
    {
        $order = DB::table('bs_shop_orders')->where('id', $orderId)->first();
        if (! $order) {
            return;
        }

        $spent = abs((float) DB::table('bs_loyalty_transactions')
            ->where('order_id', $orderId)
            ->where('type', 'spend')
            ->where('source', 'order')
            ->sum('amount'));

        $adjustments = Schema::hasTable('bs_shop_order_adjustments')
            ? (float) DB::table('bs_shop_order_adjustments')
                ->where('shop_order_id', $orderId)
                ->sum('amount')
            : 0.0;

        $itemsTotal = (float) ($order->total_price ?? 0);
        $shipping = (float) ($order->shipping_price ?? 0);

        DB::table('bs_shop_orders')
            ->where('id', $orderId)
            ->update([
                'sale_sum' => round($spent, 2),
                'total_price_sale' => max(0, round($itemsTotal - $spent, 2)),
                'grand_total' => max(0, round($itemsTotal + $adjustments - $spent + $shipping, 2)),
                'updated_at' => now(),
            ]);
    }
}
