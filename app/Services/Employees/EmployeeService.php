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

    public function updateStatus(Employee $employee, string $status, array $extra = []): Employee
    {
        $data = array_merge(['employment_status' => $status], $extra);
        $changes = $this->detectChanges($employee, $data);
        $employee->update($data);

        if (!empty($changes)) {
            $this->chatterService->logUpdated($employee, $changes, 'Employee');
        }

        return $employee->fresh();
    }

    public function syncSkills(Employee $employee, array $skills): void
    {
        $incoming = collect($skills)->pluck('skill_id')->toArray();

        $employee->skills()->whereNotIn('skill_id', $incoming)->delete();

        // Upsert remaining
        foreach ($skills as $skillData) {
            $employee->skills()->updateOrCreate(
                ['skill_id' => $skillData['skill_id']],
                [
                    'skill_type_id'       => $skillData['skill_type_id'],
                    'skill_level_id'      => $skillData['skill_level_id'] ?? null,
                    'years_of_experience' => $skillData['years_of_experience'] ?? null,
                ]
            );
        }
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
            'company_id'         => \App\Models\Settings\Company::find($value)?->name ?? "#{$value}",
            'department_id'      => \App\Models\Employees\Department::find($value)?->name ?? "#{$value}",
            'job_id'             => \App\Models\Employees\Job::find($value)?->name ?? "#{$value}",
            'parent_id',
            'coach_id'           => \App\Models\Employees\Employee::find($value)?->name ?? "#{$value}",
            'contract_id'        => \App\Models\Employees\Contract::find($value)?->name ?? "#{$value}",
            'contact_id'         => \App\Models\Contacts\Contact::find($value)?->name ?? "#{$value}",
            'user_id'            => \App\Models\User::find($value)?->name ?? "#{$value}",
            'departure_reason_id' => \App\Models\Employees\DepartureReason::find($value)?->name ?? "#{$value}",
            'employment_status'  => Employee::employmentStatusLabel((string) $value),
            'active'             => $value ? 'Yes' : 'No',
            default              => (string) $value,
        };
    }
}
