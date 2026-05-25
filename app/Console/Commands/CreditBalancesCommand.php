<?php

namespace App\Console\Commands;

use App\Services\Employees\BalanceService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class CreditBalancesCommand extends Command
{
    /**
     * Monthly cron: credit leave-days (accumulative, capped) and reset
     * time-off-hours per the per-company balance config. Idempotent — uses
     * BalanceService's last_credited_month tracking + a Cache::lock so two
     * concurrent invocations don't double-credit.
     */
    protected $signature = 'attendance:credit-balances';

    protected $description = 'Credit each employee\'s monthly leave + time-off balance.';

    public function handle(BalanceService $service): int
    {
        $lock = Cache::lock('attendance:credit-balances', 600);
        if (!$lock->get()) {
            $this->warn('Another invocation is already running. Skipping.');
            return self::SUCCESS;
        }
        try {
            $result = $service->creditMonthly();
            $this->info(sprintf(
                'Credited balances for %d employee(s) for month %s.',
                $result['processed'],
                $result['month'],
            ));
            return self::SUCCESS;
        } finally {
            $lock->release();
        }
    }
}
