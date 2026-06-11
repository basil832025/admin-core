<?php

namespace App\Console\Commands;

use App\Services\LoyaltyService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ExpireLoyaltyBonuses extends Command
{
    protected $signature = 'loyalty:expire-bonuses';

    protected $description = 'Expire overdue loyalty bonuses';

    public function handle(LoyaltyService $loyalty): int
    {
        $result = $loyalty->expireBonuses();

        $expiredCount = (int) ($result['expired_count'] ?? 0);
        $expiredAmount = (float) ($result['expired_amount'] ?? 0);

        if ($expiredCount > 0 || $expiredAmount > 0) {
            Log::info('Loyalty bonuses expired', [
                'expired_count' => $expiredCount,
                'expired_amount' => $expiredAmount,
                'command' => $this->getName(),
            ]);
        }

        $this->info('Expired accruals: ' . $expiredCount);
        $this->info('Expired amount: ' . number_format($expiredAmount, 2, '.', ' '));

        return self::SUCCESS;
    }
}
