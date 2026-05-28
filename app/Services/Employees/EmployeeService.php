<?php

namespace App\Services\Employees;

use App\Models\Employees\Employee;
use App\Services\Chatter\ChatterService;
use App\Services\FileService;

class EmployeeService
{
    public function __construct(
        private readonly ChatterService $chatterService,
        private readonly FileService $fileService,
    ) {}

    public function create(array $data): Employee
    {
        $employee = Employee::create($data);
        $this->chatterService->logCreated($employee, 'Employee');
        return $employee;
    }

    public function update(Employee $employee, array $data): Employee
    {
        $changes = $this->detectChanges($employee, $data);
        $employee->update($data);

        if (!empty($changes)) {
            $this->chatterService->logUpdated($employee, $changes, 'Employee');
        }

        return $employee->fresh();
    }

    public function archive(Employee $employee): Employee
    {
        $employee->update(['active' => false]);
        $this->chatterService->logArchived($employee, 'Employee');
        return $employee;
    }

    public function unarchive(Employee $employee): Employee
    {
        $employee->update(['active' => true]);
        $this->chatterService->logUnarchived($employee, 'Employee');
        return $employee;
    }

    public function delete(Employee $employee): void
    {
        if ($employee->avatar) {
            $this->fileService->deleteByUuid($employee->avatar);
        }
        $this->chatterService->log($employee, 'Employee deleted.', 'system');
        $employee->delete();
    }

    private function detectChanges(Employee $employee, array $data): array
    {
        $changes = [];

        foreach ($employee->chatterTracked as $field => $label) {
            if (!array_key_exists($field, $data)) continue;

            $old = (string) ($employee->{$field} ?? '');
            $new = (string) ($data[$field] ?? '');

            if ($old === $new) continue;

            $changes[] = [
                'field' => $field,
                'label' => $label,
                'from'  => $this->resolveValue($field, $employee->{$field}),
                'to'    => $this->resolveValue($field, $data[$field]),
            ];
        }

        return $changes;
    }

    private function resolveValue(string $field, mixed $value): string
    {
        if ($value === null || $value === '') return '—';

        return match ($field) {
            'company_id'              => \App\Models\Settings\Company::find($value)?->name ?? "#{$value}",
            'department_id'           => \App\Models\Employees\Department::find($value)?->name ?? "#{$value}",
            'job_id'                  => \App\Models\Employees\Job::find($value)?->name ?? "#{$value}",
            'work_location_id'        => \App\Models\Employees\WorkLocation::find($value)?->name ?? "#{$value}",
            'resource_calendar_id'    => \App\Models\Employees\ResourceCalendar::find($value)?->name ?? "#{$value}",
            'parent_id',
            'coach_id',
            'expense_manager_id',
            'attendance_manager_id'   => \App\Models\Employees\Employee::find($value)?->name ?? "#{$value}",
            'contract_id'             => \App\Models\Employees\Contract::find($value)?->name ?? "#{$value}",
            'contact_id'              => \App\Models\Contacts\Contact::find($value)?->name ?? "#{$value}",
            'user_id'                 => \App\Models\User::find($value)?->name ?? "#{$value}",
            'departure_reason_id'     => \App\Models\Employees\DepartureReason::find($value)?->name ?? "#{$value}",
            'employment_status'       => Employee::employmentStatusLabel((string) $value),
            'active',
            'flexible_hours'          => $value ? 'Yes' : 'No',
            default                   => (string) $value,
        };
    }
}
