<?php

namespace App\Services\Employees;

use App\Models\Employees\Employee;
use App\Models\Employees\EmployeeBalance;
use App\Models\Employees\RequestBalanceConfig;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class BalanceService
{
    /**
     * Get or create the employee's balance row.
     */
    public function getOrCreate(Employee $employee): EmployeeBalance
    {
        return EmployeeBalance::firstOrCreate(
            ['employee_id' => $employee->id],
            ['leave_days_balance' => 0, 'time_off_hours_balance' => 0],
        );
    }

    /**
     * Like getOrCreate but acquires a SELECT FOR UPDATE row lock — used by the
     * approval path so two parallel approvals for the same employee can't both
     * pass the sufficiency check and double-deduct.
     */
    public function getForUpdate(Employee $employee): EmployeeBalance
    {
        // Ensure the row exists first (firstOrCreate is atomic on the unique
        // employee_id index), then re-read with a lock.
        $this->getOrCreate($employee);
        return EmployeeBalance::where('employee_id', $employee->id)->lockForUpdate()->first();
    }

    /**
     * Monthly cron entry point. For each active employee in a company that has
     * a balance config, credit leave (accumulative, capped) and reset time-off
     * (full reset). Catches up if the cron missed months.
     *
     * Idempotent: keyed off last_credited_month. Calling twice in the same
     * month is a no-op for already-credited employees.
     */
    public function creditMonthly(?Carbon $asOf = null): array
    {
        $asOf      = ($asOf ?? Carbon::today())->copy()->startOfMonth();
        $processed = 0;

        DB::transaction(function () use ($asOf, &$processed) {
            $configsByCompany = RequestBalanceConfig::all()->keyBy('company_id');

            Employee::query()
                ->where('active', true)
                ->whereIn('company_id', $configsByCompany->keys())
                ->chunk(200, function ($chunk) use ($configsByCompany, $asOf, &$processed) {
                    foreach ($chunk as $employee) {
                        $config = $configsByCompany->get($employee->company_id);
                        if (!$config) continue;

                        $balance = $this->getOrCreate($employee);

                        // Determine how many months to credit (catch-up).
                        $monthsToCredit = $balance->last_credited_month
                            ? max(0, $balance->last_credited_month->diffInMonths($asOf))
                            : 1; // never credited — credit for the asOf month only

                        if ($monthsToCredit === 0) continue;

                        $newLeave = (float) $balance->leave_days_balance
                            + ((float) $config->leave_days_per_month * $monthsToCredit);
                        $newLeave = min($newLeave, (float) $config->leave_days_max);

                        $balance->update([
                            'leave_days_balance'     => round($newLeave, 2),
                            'time_off_hours_balance' => (float) $config->time_off_hours_per_month, // full reset
                            'last_credited_month'    => $asOf->toDateString(),
                        ]);
                        $processed++;
                    }
                });
        });

        return ['processed' => $processed, 'month' => $asOf->toDateString()];
    }
}
