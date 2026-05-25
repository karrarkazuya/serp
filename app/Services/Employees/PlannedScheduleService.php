<?php

namespace App\Services\Employees;

use App\Models\Employees\Employee;
use App\Models\Employees\PlannedDay;
use App\Models\Employees\PlannedRSchedule;
use App\Models\Employees\PlannedScheduleRun;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

class PlannedScheduleService
{
    public const BUFFER_DAYS = 30;

    /**
     * Top up the forward planned-days buffer for one employee to BUFFER_DAYS
     * entries starting from tomorrow. Idempotent — only inserts the missing
     * tail; existing days are never touched. When the employee has a repeat
     * pattern, the tail is filled by cycling through it from the position
     * where existing days leave off.
     */
    public function syncMissingDays(Employee $employee): void
    {
        if (!$employee->resource_calendar_id) {
            return;
        }

        $existing = PlannedDay::where('employee_id', $employee->id)
            ->orderBy('planned_date')
            ->get(['id', 'planned_date', 'resource_calendar_id']);

        $existingCount = $existing->count();
        if ($existingCount >= self::BUFFER_DAYS) {
            return;
        }

        $missing  = self::BUFFER_DAYS - $existingCount;
        $lastDate = $existing->last()?->planned_date instanceof CarbonInterface
            ? $existing->last()->planned_date->copy()
            : Carbon::today();

        $pattern = PlannedRSchedule::where('employee_id', $employee->id)
            ->orderBy('sequence')->orderBy('id')
            ->pluck('resource_calendar_id')
            ->all();

        // When a pattern exists, figure out where the existing tail sits in
        // the cycle so we can continue from the next slot rather than from 0.
        $nextPatternIndex = 0;
        if (!empty($pattern)) {
            $existingIds = $existing->pluck('resource_calendar_id')->all();
            $shift = $this->findShift($existingIds, $pattern);
            if ($shift !== null) {
                $nextPatternIndex = ($shift + count($existingIds)) % count($pattern);
            } elseif (!empty($existingIds)) {
                // Pattern alignment was lost — usually a manual setDay override
                // injected a value that doesn't fit the cycle. Recover gracefully:
                // find where the LAST existing day sits in the pattern, and
                // continue from the next slot. The override stays as-is, but
                // the cron-spawned tail continues the pattern from there.
                $lastIdx = array_search(end($existingIds), $pattern, true);
                if ($lastIdx !== false) {
                    $nextPatternIndex = ($lastIdx + 1) % count($pattern);
                }
            }
        }

        $defaultCalendarId = $employee->resource_calendar_id;
        $lastCalendarId    = $existing->last()?->resource_calendar_id ?? $defaultCalendarId;

        $rows = [];
        for ($i = 0; $i < $missing; $i++) {
            $lastDate = $lastDate->copy()->addDay();

            if (!empty($pattern)) {
                $calendarId = $pattern[($nextPatternIndex + $i) % count($pattern)];
            } else {
                $calendarId = $lastCalendarId;
            }

            $rows[] = [
                'employee_id'          => $employee->id,
                'resource_calendar_id' => $calendarId,
                'planned_date'         => $lastDate->toDateString(),
            ];
        }

        // Use the service create path so the observer + chatter still apply.
        foreach ($rows as $row) {
            PlannedDay::create($row);
        }
    }

    /**
     * Cron entry point. Idempotent + lock-safe.
     *  - For every planned row where date <= today, apply schedule to employee
     *    and delete the row.
     *  - Refill every employee's buffer to BUFFER_DAYS.
     *  - Mark today's run row complete so a second invocation today is a no-op.
     *
     * Returns array{processed:int, employees_refilled:int, skipped:bool}.
     */
    public function applyForToday(bool $force = false): array
    {
        $today = Carbon::today()->toDateString();

        if (!$force) {
            $existingRun = PlannedScheduleRun::whereDate('run_date', $today)
                ->where('success', true)
                ->first();
            if ($existingRun) {
                return ['processed' => 0, 'employees_refilled' => 0, 'skipped' => true];
            }
        }

        return DB::transaction(function () use ($today) {
            $processed = 0;

            $duePlannedDays = PlannedDay::with('employee')
                ->whereDate('planned_date', '<=', $today)
                ->orderBy('employee_id')
                ->orderBy('planned_date')
                ->get();

            // Group by employee — only the latest date per employee actually
            // dictates the active schedule, but every leftover row must be cleared.
            $latestPerEmployee = [];
            foreach ($duePlannedDays as $row) {
                $latestPerEmployee[$row->employee_id] = $row;
            }

            foreach ($latestPerEmployee as $row) {
                $employee = $row->employee;
                if (!$employee) continue;

                if ($row->resource_calendar_id
                    && $employee->resource_calendar_id !== $row->resource_calendar_id) {
                    $employee->update(['resource_calendar_id' => $row->resource_calendar_id]);
                }
            }

            $processed = $duePlannedDays->count();
            PlannedDay::whereIn('id', $duePlannedDays->pluck('id'))->forceDelete();

            $employeesRefilled = 0;
            Employee::whereNotNull('resource_calendar_id')
                ->where('active', true)
                ->chunk(100, function ($chunk) use (&$employeesRefilled) {
                    foreach ($chunk as $employee) {
                        $this->syncMissingDays($employee);
                        $employeesRefilled++;
                    }
                });

            // Use whereDate to defeat the same SQLite Y-m-d vs Y-m-d H:i:s
            // string-equality issue we hit with planned_date.
            $existing = PlannedScheduleRun::whereDate('run_date', $today)->first();
            if ($existing) {
                $existing->update(['ran_at' => now(), 'success' => true]);
            } else {
                PlannedScheduleRun::create([
                    'run_date' => $today,
                    'ran_at'   => now(),
                    'success'  => true,
                ]);
            }

            return [
                'processed'          => $processed,
                'employees_refilled' => $employeesRefilled,
                'skipped'            => false,
            ];
        });
    }

    /**
     * Update a single planned day. When the day is today, also immediately
     * update the employee's active resource_calendar_id (since the midnight
     * cron has already run and we want the change to take effect right now).
     */
    public function setDay(Employee $employee, string|CarbonInterface $date, int $resourceCalendarId): PlannedDay
    {
        $dateStr = $date instanceof CarbonInterface ? $date->toDateString() : Carbon::parse($date)->toDateString();
        $today   = Carbon::today()->toDateString();

        if ($dateStr < $today) {
            throw new \InvalidArgumentException('Cannot edit a planned schedule for a past date.');
        }

        return DB::transaction(function () use ($employee, $dateStr, $resourceCalendarId, $today) {
            $row = $this->upsertPlannedDay($employee->id, $dateStr, $resourceCalendarId);

            if ($dateStr === $today && $employee->resource_calendar_id !== $resourceCalendarId) {
                // Update without firing EmployeeScheduleObserver — that observer
                // is meant for the "default calendar changed" path (employee
                // form) and would wipe today's planned row we just wrote.
                Employee::withoutEvents(function () use ($employee, $resourceCalendarId) {
                    $employee->update(['resource_calendar_id' => $resourceCalendarId]);
                });
            }

            return $row;
        });
    }

    /**
     * Replace the employee's repeat pattern and refill the planning buffer
     * so every future day cycles through it. Pattern applies indefinitely —
     * syncMissingDays continues the cycle as days roll off the buffer.
     *
     * @param int[] $pattern  Ordered list of resource_calendar_id values.
     */
    public function applyPattern(Employee $employee, array $pattern): void
    {
        if (empty($pattern)) {
            throw new \InvalidArgumentException('Pattern must contain at least one schedule.');
        }

        DB::transaction(function () use ($employee, $pattern) {
            PlannedRSchedule::where('employee_id', $employee->id)->forceDelete();
            foreach ($pattern as $i => $calendarId) {
                PlannedRSchedule::create([
                    'employee_id'          => $employee->id,
                    'resource_calendar_id' => $calendarId,
                    'sequence'             => $i,
                ]);
            }

            // Wipe future planned days so syncMissingDays rebuilds them from
            // index 0 of the new pattern. Today is left alone — setDay() is
            // the right path for changing today.
            PlannedDay::where('employee_id', $employee->id)
                ->whereDate('planned_date', '>=', Carbon::tomorrow()->toDateString())
                ->forceDelete();

            $this->syncMissingDays($employee->refresh());
        });
    }

    /**
     * Called when the employee's default resource_calendar_id is changed on
     * the employee form. Wipes the pattern + planned days and regenerates
     * BUFFER_DAYS fresh days using the new calendar starting tomorrow.
     */
    public function resetForEmployee(Employee $employee): void
    {
        if (!$employee->resource_calendar_id) {
            return;
        }

        DB::transaction(function () use ($employee) {
            PlannedRSchedule::where('employee_id', $employee->id)->forceDelete();
            PlannedDay::where('employee_id', $employee->id)->forceDelete();
            $this->syncMissingDays($employee);
        });
    }

    /**
     * Upsert by (employee_id, planned_date) using whereDate. Eloquent's
     * updateOrCreate does string equality on planned_date, which fails on
     * SQLite where DATE columns are stored as `Y-m-d H:i:s`.
     */
    private function upsertPlannedDay(int $employeeId, string $date, int $resourceCalendarId): PlannedDay
    {
        $existing = PlannedDay::where('employee_id', $employeeId)
            ->whereDate('planned_date', $date)
            ->first();

        if ($existing) {
            if ($existing->resource_calendar_id !== $resourceCalendarId) {
                $existing->update(['resource_calendar_id' => $resourceCalendarId]);
            }
            return $existing;
        }

        return PlannedDay::create([
            'employee_id'          => $employeeId,
            'planned_date'         => $date,
            'resource_calendar_id' => $resourceCalendarId,
        ]);
    }

    /**
     * Find the offset s such that result_array aligns with pattern_array
     * starting at position s. Returns null if no alignment exists.
     *
     * Lifted from the Odoo jtemployees implementation. Used by syncMissingDays
     * to continue a pattern from wherever the existing tail leaves off.
     */
    private function findShift(array $resultArray, array $patternArray): ?int
    {
        $m = count($patternArray);
        $n = count($resultArray);

        if ($m === 0) return null;
        if ($n === 0) return 0;

        for ($s = 0; $s < $m; $s++) {
            $aligned = true;
            for ($i = 0; $i < $n; $i++) {
                if ($resultArray[$i] !== $patternArray[($s + $i) % $m]) {
                    $aligned = false;
                    break;
                }
            }
            if ($aligned) return $s;
        }
        return null;
    }
}
