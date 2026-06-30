<?php

namespace App\Console\Commands;

use App\Models\Shop\PaypartsRefund;
use App\Services\PrivatBankPaypartsRefundService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SyncPaypartsRefunds extends Command
{
    protected $signature = 'payparts:sync-refunds {--limit=100}';
    protected $description = 'Проверить в ПриватБанке незавершённые возвраты Оплаты частями';

    public function handle(): int
    {
        $limit = max(1, min(500, (int) $this->option('limit')));
        $refunds = PaypartsRefund::query()
            ->where('status', 'refund_pending')
            ->oldest('checked_at')
            ->limit($limit)
            ->get();

        foreach ($refunds as $refund) {
            try {
                PrivatBankPaypartsRefundService::make()->sync($refund);
            } catch (\Throwable $e) {
                Log::warning('Payparts refund status sync failed', [
                    'refund_id' => $refund->id,
                    'error' => $e->getMessage(),
                ]);
                $this->warn("Refund {$refund->id}: {$e->getMessage()}");
            }
        }

        $this->info('Проверено возвратов: ' . $refunds->count());

        return self::SUCCESS;
    }
}
