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

        Log::info('Loyalty bonuses expired', [
            'expired_count' => (int) ($result['expired_count'] ?? 0),
            'expired_amount' => (float) ($result['expired_amount'] ?? 0),
            'command' => $this->getName(),
        ]);

        $this->info('Expired accruals: ' . (int) ($result['expired_count'] ?? 0));
        $this->info('Expired amount: ' . number_format((float) ($result['expired_amount'] ?? 0), 2, '.', ' '));

        return self::SUCCESS;
    }
}
