<?php

namespace App\Services\Employees;

use App\Models\Employees\Attendance;
use App\Models\Employees\Employee;
use App\Models\Employees\ResourceCalendar;
use Carbon\Carbon;
use Carbon\CarbonInterface;

class AttendanceService
{
    /**
     * Create an attendance, snapshotting the employee's calendar and
     * computing expected/worked/overtime/shortage in one pass.
     */
    public function create(array $data): Attendance
    {
        $employee = Employee::findOrFail($data['employee_id']);
        $data['company_id']           = $data['company_id']           ?? $employee->company_id;
        $data['resource_calendar_id'] = $data['resource_calendar_id'] ?? $employee->resource_calendar_id;

        $attendance = Attendance::create($this->stripDerivedFields($data));
        $this->recompute($attendance);
        $attendance->logSystemMessage('Attendance created.');

        return $attendance;
    }

    /**
     * Update an attendance and recompute the derived metrics.
     */
    public function update(Attendance $attendance, array $data): Attendance
    {
        $attendance->update($this->stripDerivedFields($data));
        $this->recompute($attendance);
        $attendance->logSystemMessage('Attendance updated.');

        return $attendance;
    }

    /**
     * is_day_off and is_absence are computed from the schedule + punches.
     * Strip them so callers cannot override the derived values.
     */
    private function stripDerivedFields(array $data): array
    {
        unset($data['is_day_off'], $data['is_absence']);
        return $data;
    }

    /**
     * Recompute expected_check_in/out, expected_hours, worked_hours,
     * overtime_hours, shortage_hours, is_day_off, is_absence from the
     * stored fields + the linked schedule.
     *
     * Saves the record. Safe to call repeatedly.
     */
    public function recompute(Attendance $attendance): Attendance
    {
        $date = $attendance->attendance_date instanceof CarbonInterface
            ? $attendance->attendance_date
            : Carbon::parse($attendance->attendance_date);

        $blocks = $this->scheduleBlocksFor($attendance, $date);

        if (empty($blocks)) {
            // No working blocks for this date → day off
            $attendance->expected_check_in  = null;
            $attendance->expected_check_out = null;
            $attendance->expected_hours     = 0;
            $attendance->is_day_off         = true;
        } else {
            $earliest = min(array_column($blocks, 'from'));
            $latest   = max(array_column($blocks, 'to'));
            $sumHours = array_sum(array_map(fn ($b) => $b['to'] - $b['from'], $blocks));

            $attendance->expected_check_in  = $this->decimalToDateTime($date, $earliest);
            $attendance->expected_check_out = $this->decimalToDateTime($date, $latest);
            $attendance->expected_hours     = round($sumHours, 2);
            $attendance->is_day_off         = false;
        }

        // Worked hours + absence flag.
        // Absence is derived, never user-editable: if either punch is missing on a
        // non-day-off, the record is an absence.
        $bothPunched = $attendance->check_in && $attendance->check_out;

        if ($bothPunched && $attendance->check_out->greaterThanOrEqualTo($attendance->check_in)) {
            // Carbon's diffInMinutes() is absolute by default — without the
            // explicit order guard above, a swapped/typo'd check_out before
            // check_in would credit the (positive) interval as legitimate
            // worked hours. Treat reversed punches as missing / absence.
            $worked = $attendance->check_in->diffInMinutes($attendance->check_out) / 60;
            $attendance->worked_hours = round(max(0, $worked), 2);
            $attendance->is_absence   = false;
        } else {
            $attendance->worked_hours = 0;
            // An approved leave / time-off request linked to the row is not an
            // absence — the employee was legitimately off.
            $attendance->is_absence   = !$attendance->is_day_off && !$attendance->request_id;
        }

        // Overtime / shortage are zero on day-off rows
        if ($attendance->is_day_off) {
            $attendance->overtime_hours = round((float) $attendance->worked_hours, 2);
            $attendance->shortage_hours = 0;
        } else {
            $diff = (float) $attendance->worked_hours - (float) $attendance->expected_hours;
            $attendance->overtime_hours = $diff > 0 ? round($diff, 2)  : 0;
            $attendance->shortage_hours = $diff < 0 ? round(-$diff, 2) : 0;
        }

        $attendance->save();

        return $attendance;
    }

    /**
     * Returns the working blocks (hour_from / hour_to as decimals) for the
     * given attendance row's date, derived from its calendar.
     *
     * @return array<int, array{from: float, to: float}>
     */
    private function scheduleBlocksFor(Attendance $attendance, CarbonInterface $date): array
    {
        $calendarId = $attendance->resource_calendar_id
            ?? $attendance->employee?->resource_calendar_id;

        if (!$calendarId) return [];

        $calendar = ResourceCalendar::with('attendances')->find($calendarId);
        if (!$calendar) return [];

        // Carbon dayOfWeek: 0=Sunday … 6=Saturday.
        // S-ERP convention (per ResourceCalendar::$dayNames): 0=Saturday … 6=Friday.
        $sysDow = ($date->dayOfWeek + 1) % 7;

        return $calendar->attendances
            ->where('day_of_week', $sysDow)
            ->map(fn ($a) => ['from' => (float) $a->hour_from, 'to' => (float) $a->hour_to])
            ->values()
            ->all();
    }

    private function decimalToDateTime(CarbonInterface $date, float $hour): Carbon
    {
        $dayOffset = (int) floor($hour / 24);
        $remaining = $hour - ($dayOffset * 24);
        $h         = (int) floor($remaining);
        $m         = (int) round(($remaining - $h) * 60);

        return $date->copy()->startOfDay()->addDays($dayOffset)->addHours($h)->addMinutes($m);
    }
}
