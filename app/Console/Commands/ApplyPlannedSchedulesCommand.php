<?php

namespace App\Console\Commands;

use App\Services\Employees\PlannedScheduleService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class ApplyPlannedSchedulesCommand extends Command
{
    /**
     * Runs at midnight via the scheduler. Idempotent on three layers:
     *  1. Cache::lock blocks concurrent invocations.
     *  2. hr_planned_schedule_runs row tracks "already ran today" and the
     *     service exits early when seen.
     *  3. The data operations themselves (delete + updateOrCreate + buffer
     *     top-up) are safe to repeat — running twice produces the same end
     *     state as running once.
     *
     * --force bypasses the "already ran today" check (use for recovery after
     * a mid-flight failure). Lock is still acquired even under --force.
     */
    protected $signature = 'attendance:apply-planned-schedules {--force : Re-run even if today already succeeded}';

    protected $description = 'Apply each employee\'s planned working schedule for today and refill the planning buffer.';

    public function handle(PlannedScheduleService $service): int
    {
        $force = (bool) $this->option('force');

        $lock = Cache::lock('attendance:apply-planned-schedules', 600);

        if (!$lock->get()) {
            $this->warn('Another invocation is already running. Skipping.');
            return self::SUCCESS;
        }

        try {
            $result = $service->applyForToday($force);

            if ($result['skipped']) {
                $this->info('Already ran today — skipping. Pass --force to re-run.');
                return self::SUCCESS;
            }

            $this->info(sprintf(
                'Applied %d planned day(s); refilled buffer for %d employee(s).',
                $result['processed'],
                $result['employees_refilled'],
            ));
            return self::SUCCESS;
        } finally {
            $lock->release();
        }
    }
}
