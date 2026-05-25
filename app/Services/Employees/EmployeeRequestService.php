<?php

namespace App\Services\Employees;

use App\Models\Employees\Attendance;
use App\Models\Employees\Employee;
use App\Models\Employees\EmployeeRequest;
use App\Models\Employees\RequestSubtype;
use App\Models\Notification;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class EmployeeRequestService
{
    public function __construct(
        private readonly AttendanceService $attendanceService,
        private readonly BalanceService $balanceService,
    ) {}

    /**
     * Create + validate a request. Throws RuntimeException for any business
     * rule violation (overlap, hours-outside-schedule, etc.). The transactional
     * wrap is owned by the controller.
     */
    public function create(array $data): EmployeeRequest
    {
        $employee = Employee::with('attendanceManager')->findOrFail($data['employee_id']);
        $subtype  = RequestSubtype::findOrFail($data['subtype_id']);

        if ($subtype->type !== $data['type']) {
            throw new RuntimeException(__('employees.request_subtype_type_mismatch'));
        }

        $start = Carbon::parse($data['start_at']);
        $end   = Carbon::parse($data['end_at']);

        // Type-specific window normalization: leave is whole days, so snap to
        // start-of-day and end-of-day so the duration is exact.
        if ($subtype->type === RequestSubtype::TYPE_LEAVE) {
            $start = $start->copy()->startOfDay();
            $end   = $end->copy()->endOfDay();
        }

        // Serialize all submissions for this employee so two parallel submits
        // can't both pass the overlap check before either commits. The
        // controller owns the surrounding DB::transaction.
        Employee::lockForUpdate()->find($employee->id);

        // Overlap with any other PENDING / APPROVED request for this employee.
        $overlap = EmployeeRequest::where('employee_id', $employee->id)
            ->whereIn('state', [EmployeeRequest::STATE_PENDING, EmployeeRequest::STATE_APPROVED])
            ->where(function ($q) use ($start, $end) {
                $q->where('start_at', '<=', $end->toDateTimeString())
                  ->where('end_at',   '>=', $start->toDateTimeString());
            })
            ->exists();
        if ($overlap) {
            throw new RuntimeException(__('employees.request_overlap'));
        }

        // Type-specific validation against the employee's working schedule.
        $this->validateAgainstSchedule($employee, $subtype, $start, $end);

        // Subtype "requires_*" enforcement.
        if ($subtype->requires_title && empty($data['title'])) {
            throw new RuntimeException(__('employees.request_title_required'));
        }
        if ($subtype->requires_description && empty($data['description'])) {
            throw new RuntimeException(__('employees.request_description_required'));
        }
        if ($subtype->requires_attachment && empty($data['attachment'])) {
            throw new RuntimeException(__('employees.request_attachment_required'));
        }

        $request = EmployeeRequest::create([
            'employee_id'    => $employee->id,
            'company_id'     => $employee->company_id,
            'type'           => $subtype->type,
            'subtype_id'     => $subtype->id,
            'start_at'       => $start->toDateTimeString(),
            'end_at'         => $end->toDateTimeString(),
            'duration_days'  => $subtype->type === RequestSubtype::TYPE_LEAVE
                ? max(1, $start->diffInDays($end->copy()->addSecond()))
                : 0,
            'duration_hours' => $subtype->type !== RequestSubtype::TYPE_LEAVE
                ? round($start->diffInMinutes($end) / 60, 2)
                : 0,
            'title'          => $data['title']       ?? null,
            'description'    => $data['description'] ?? null,
            'attachment'     => $data['attachment']  ?? null,
            'state'          => EmployeeRequest::STATE_PENDING,
        ]);

        $request->logSystemMessage(__('employees.request_created_log'));
        $this->notifyOnSubmission($request);
        return $request;
    }

    /**
     * When a request is submitted, notify the assigned attendance manager so
     * they see it in their queue without having to poll the dashboard.
     */
    private function notifyOnSubmission(EmployeeRequest $request): void
    {
        $managerUser = $request->employee?->attendanceManager?->user;
        if (!$managerUser) return;
        // Don't notify the manager if they happen to be the submitter
        // (defensive — submitter cannot self-approve anyway).
        if ($managerUser->id === $request->employee?->user_id) return;

        $this->createNotification(
            $managerUser->id,
            __('employees.notif_request_submitted_title'),
            __('employees.notif_request_submitted_body', [
                'employee' => $request->employee?->name ?? '',
                'type'     => __('employees.' . $request->type),
            ]),
            url('/employees/requests/' . $request->id),
        );
    }

    /**
     * Apply a decision. Both manager and HR call through here.
     *
     * @param string $role 'manager' or 'hr'
     * @param string $decision 'approve' or 'reject'
     */
    public function decide(EmployeeRequest $request, string $role, string $decision, ?string $reason, int $byUserId): EmployeeRequest
    {
        $field = match ($role) {
            'manager' => 'manager',
            'hr'      => 'hr',
            default   => throw new \InvalidArgumentException('role must be manager or hr'),
        };
        $statusValue = $decision === 'approve' ? EmployeeRequest::STATE_APPROVED : EmployeeRequest::STATE_REJECTED;

        DB::transaction(function () use ($request, $role, $decision, $field, $statusValue, $reason, $byUserId) {
            // Re-read the request inside the transaction WITH a row lock so two
            // concurrent approvals can't both pass the isLocked() check, both
            // deduct balance, and both apply side effects.
            $locked = EmployeeRequest::lockForUpdate()->findOrFail($request->id);
            if ($locked->isLocked()) {
                throw new RuntimeException(__('employees.request_already_decided'));
            }

            // Balance check now happens inside the lock so the balance row we
            // verify is the same one we'll deduct from (no TOCTOU window).
            if ($decision === 'approve' && $role === 'hr' && $this->shouldDeductBalance($locked)) {
                $this->assertSufficientBalance($locked);
            }

            $locked->fill([
                "{$field}_status"          => $statusValue,
                "{$field}_decision_at"     => now(),
                "{$field}_decision_by"     => $byUserId,
                "{$field}_decision_reason" => $reason,
            ]);
            $locked->recomputeState();
            $locked->save();

            // Side effects only fire when the request becomes fully approved.
            if ($locked->state === EmployeeRequest::STATE_APPROVED) {
                $this->applyApprovalSideEffects($locked);
            }
            // Mirror back so the caller's reference reflects the locked state.
            $request->setRawAttributes($locked->getAttributes(), true);
        });

        $this->notifyOfDecision($request, $field, $decision, $reason);

        return $request->refresh();
    }

    /**
     * Side effects when a request is fully approved.
     *  - Deduct balance (leave or time-off).
     *  - Tag the affected attendance rows with request_id.
     *  - For time off: reduce expected_hours on those rows.
     *  - For overtime: add to approved_overtime_hours on those rows.
     *  - Leave: just tag rows; recompute treats them as day-off-equivalent.
     */
    private function applyApprovalSideEffects(EmployeeRequest $request): void
    {
        $this->deductBalance($request);

        $employee = $request->employee;
        $start    = $request->start_at;
        $end      = $request->end_at;
        $factor   = (float) $request->subtype->factor;

        $cursor = $start->copy()->startOfDay();
        $stop   = $end->copy()->endOfDay();

        while ($cursor->lte($stop)) {
            $date = $cursor->copy()->startOfDay();

            // Slice of the request on THIS day, clipped to [start_at, end_at].
            $dayStart = $start->greaterThan($date) ? $start->copy() : $date->copy();
            $dayEnd   = $end->lessThan($date->copy()->endOfDay()) ? $end->copy() : $date->copy()->endOfDay();
            $hoursOnDay = max(0, $dayStart->diffInMinutes($dayEnd) / 60);

            // whereDate-aware lookup: SQLite stores DATE as Y-m-d H:i:s, so a
            // string-equality firstOrCreate would miss existing rows and hit
            // the (employee_id, attendance_date) unique constraint on insert.
            $attendance = Attendance::where('employee_id', $employee->id)
                ->whereDate('attendance_date', $date->toDateString())
                ->first();
            if (!$attendance) {
                $attendance = Attendance::create([
                    'employee_id'          => $employee->id,
                    'attendance_date'      => $date->toDateString(),
                    'company_id'           => $employee->company_id,
                    'resource_calendar_id' => $employee->resource_calendar_id,
                ]);
            }

            switch ($request->type) {
                case RequestSubtype::TYPE_LEAVE:
                    $attendance->request_id = $request->id;
                    $this->attendanceService->recompute($attendance);
                    break;
                case RequestSubtype::TYPE_TIME_OFF:
                    $attendance->request_id = $request->id;
                    $attendance->save();
                    $this->attendanceService->recompute($attendance);
                    // After recompute, reduce expected hours by the time-off hours.
                    $reduced = max(0, (float) $attendance->expected_hours - $hoursOnDay);
                    $attendance->expected_hours = round($reduced, 2);
                    $this->refreshDerivedHours($attendance);
                    $attendance->save();
                    break;
                case RequestSubtype::TYPE_OVERTIME:
                    $attendance->request_id = $request->id;
                    $attendance->approved_overtime_hours = round(
                        (float) $attendance->approved_overtime_hours + ($hoursOnDay * $factor),
                        2,
                    );
                    $attendance->save();
                    break;
            }

            $cursor->addDay();
        }
    }

    /**
     * Validation: overtime hours cannot overlap working schedule; time off must
     * fall fully within working schedule; both day-off rules per type.
     */
    private function validateAgainstSchedule(Employee $employee, RequestSubtype $subtype, CarbonInterface $start, CarbonInterface $end): void
    {
        // Leave is whole days; no time-of-day rules to enforce.
        if ($subtype->type === RequestSubtype::TYPE_LEAVE) {
            return;
        }

        $calendar = $employee->resourceCalendar;
        if (!$calendar) {
            throw new RuntimeException(__('employees.request_no_schedule_assigned'));
        }
        $calendar->loadMissing('attendances');

        $cursor = $start->copy()->startOfDay();
        $stop   = $end->copy()->endOfDay();

        while ($cursor->lte($stop)) {
            $sysDow = ($cursor->dayOfWeek + 1) % 7;
            $blocks = $calendar->attendances->where('day_of_week', $sysDow)->values()->all();

            // Slice of the request on THIS day.
            $dayStart = $start->greaterThan($cursor) ? $start->copy() : $cursor->copy();
            $dayEnd   = $end->lessThan($cursor->copy()->endOfDay()) ? $end->copy() : $cursor->copy()->endOfDay();
            $reqStartH = $dayStart->hour + $dayStart->minute / 60;
            $reqEndH   = $dayEnd->hour   + $dayEnd->minute   / 60;
            if ($dayEnd->isSameDay($dayStart) === false) {
                $reqEndH += 24 * $dayStart->diffInDays($dayEnd);
            }

            $isDayOff = empty($blocks);

            if ($subtype->type === RequestSubtype::TYPE_OVERTIME) {
                if (!$isDayOff) {
                    foreach ($blocks as $block) {
                        $blockFrom = (float) $block->hour_from;
                        $blockTo   = (float) $block->hour_to;
                        // Reject if the request slice overlaps ANY working block.
                        if ($reqStartH < $blockTo && $reqEndH > $blockFrom) {
                            throw new RuntimeException(__('employees.request_overtime_in_working_hours', [
                                'date' => $cursor->toDateString(),
                            ]));
                        }
                    }
                }
            } else { // TIME_OFF
                if ($isDayOff) {
                    throw new RuntimeException(__('employees.request_time_off_on_day_off', [
                        'date' => $cursor->toDateString(),
                    ]));
                }
                $insideAnyBlock = false;
                foreach ($blocks as $block) {
                    $blockFrom = (float) $block->hour_from;
                    $blockTo   = (float) $block->hour_to;
                    if ($reqStartH >= $blockFrom && $reqEndH <= $blockTo) {
                        $insideAnyBlock = true;
                        break;
                    }
                }
                if (!$insideAnyBlock) {
                    throw new RuntimeException(__('employees.request_time_off_outside_working_hours', [
                        'date' => $cursor->toDateString(),
                    ]));
                }
            }

            $cursor->addDay();
        }
    }

    private function shouldDeductBalance(EmployeeRequest $request): bool
    {
        return (bool) $request->subtype?->cuts_balance
            && in_array($request->type, [RequestSubtype::TYPE_LEAVE, RequestSubtype::TYPE_TIME_OFF], true);
    }

    private function assertSufficientBalance(EmployeeRequest $request): void
    {
        // Lock the balance row inside the approval transaction so the check
        // and the deduct see (and reserve) the same value.
        $balance = $this->balanceService->getForUpdate($request->employee);
        if ($request->type === RequestSubtype::TYPE_LEAVE) {
            if ((float) $balance->leave_days_balance < (float) $request->duration_days) {
                throw new RuntimeException(__('employees.request_insufficient_leave_balance'));
            }
        } else { // TIME_OFF
            if ((float) $balance->time_off_hours_balance < (float) $request->duration_hours) {
                throw new RuntimeException(__('employees.request_insufficient_timeoff_balance'));
            }
        }
    }

    private function deductBalance(EmployeeRequest $request): void
    {
        if (!$this->shouldDeductBalance($request)) return;
        // Same locked row from the earlier assertSufficientBalance call; we
        // re-acquire to be safe in code paths that skipped the assertion
        // (manager-only approval path doesn't deduct, so this only fires after
        // HR approval has held the lock).
        $balance = $this->balanceService->getForUpdate($request->employee);
        if ($request->type === RequestSubtype::TYPE_LEAVE) {
            $balance->leave_days_balance = round(
                max(0, (float) $balance->leave_days_balance - (float) $request->duration_days),
                2,
            );
        } else {
            $balance->time_off_hours_balance = round(
                max(0, (float) $balance->time_off_hours_balance - (float) $request->duration_hours),
                2,
            );
        }
        $balance->save();
    }

    private function refreshDerivedHours(Attendance $a): void
    {
        $diff = (float) $a->worked_hours - (float) $a->expected_hours;
        $a->overtime_hours = $diff > 0 ? round($diff, 2)  : 0;
        $a->shortage_hours = $diff < 0 ? round(-$diff, 2) : 0;
    }

    private function notifyOfDecision(EmployeeRequest $request, string $byField, string $decision, ?string $reason): void
    {
        $request->loadMissing(['employee.user', 'employee.attendanceManager.user']);
        $employeeUser = $request->employee?->user;
        $managerUser  = $request->employee?->attendanceManager?->user;
        // Web link both sides can open.
        $url = url('/employees/requests/' . $request->id);

        if ($decision === 'reject') {
            $reasonLine = $reason ? "\n\n" . __('employees.notif_reason') . ": " . $reason : '';
            $body = __('employees.notif_request_rejected_body', [
                'by'   => $byField === 'hr' ? __('employees.notif_role_hr') : __('employees.notif_role_manager'),
                'type' => __('employees.' . $request->type),
            ]) . $reasonLine;

            // HR rejection notifies BOTH employee + manager. Manager rejection
            // notifies the employee only.
            $recipients = collect([$employeeUser]);
            if ($byField === 'hr' && $managerUser) {
                $recipients->push($managerUser);
            }
            foreach ($recipients->filter()->unique('id') as $u) {
                $this->createNotification($u->id, __('employees.notif_request_rejected_title'), $body, $url);
            }
            return;
        }

        // Approved (final state). Notify employee only.
        if ($request->state === EmployeeRequest::STATE_APPROVED && $employeeUser) {
            $this->createNotification(
                $employeeUser->id,
                __('employees.notif_request_approved_title'),
                __('employees.notif_request_approved_body', ['type' => __('employees.' . $request->type)]),
                $url,
            );
        }
    }

    private function createNotification(int $userId, string $title, string $body, string $url): void
    {
        Notification::create([
            'user_id' => $userId,
            'title'   => $title,
            'body'    => $body,
            'url'     => $url,
        ]);
    }
}
