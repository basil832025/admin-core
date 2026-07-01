<?php

namespace App\Http\Controllers\Admin;

use App\Enums\PaymentMethodEnum;
use App\Http\Controllers\Controller;
use App\Models\Shop\PaypartsTransaction;
use App\Services\PaypartsStatusSyncService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class PaypartsStatusController extends Controller
{
    public function __invoke(PaypartsStatusSyncService $syncService): JsonResponse
    {
        $pendingStatuses = ['payment_redirected', 'pending_payment'];
        $maxAgeHours = max(1, (int) config('services.payparts.admin_polling_max_age_hours', 24));
        $batchSize = min(20, max(1, (int) config('services.payparts.admin_polling_batch_size', 5)));

        $transactions = PaypartsTransaction::query()
            ->with(['order', 'bank'])
            ->whereIn('status', $pendingStatuses)
            ->where('created_at', '>=', now()->subHours($maxAgeHours))
            ->whereIn('id', PaypartsTransaction::query()
                ->selectRaw('MAX(id)')
                ->groupBy('shop_order_id'))
            ->whereHas('order', function (Builder $query) use ($pendingStatuses): void {
                $query->whereIn('payparts_status', $pendingStatuses)
                    ->where('payment', PaymentMethodEnum::PAYPARTS->value);
            })
            ->oldest('created_at')
            ->limit($batchSize)
            ->get();

        $checked = 0;
        $changedOrderIds = [];
        $errors = 0;

        foreach ($transactions as $transaction) {
            try {
                $result = $syncService->sync($transaction);
                if ($result['checked']) {
                    $checked++;
                }
                if ($result['order_changed']) {
                    $changedOrderIds[] = (int) $transaction->shop_order_id;
                }
            } catch (\Throwable $e) {
                $errors++;
                Log::info('Payparts admin fallback sync skipped', [
                    'order_id' => $transaction->shop_order_id,
                    'transaction_id' => $transaction->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return response()->json([
            'pending' => $transactions->count(),
            'checked' => $checked,
            'changed_order_ids' => array_values(array_unique($changedOrderIds)),
            'errors' => $errors,
        ]);
    }
}