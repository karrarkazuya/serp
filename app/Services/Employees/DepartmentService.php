<?php

namespace App\Services\Employees;

use App\Models\Employees\Department;
use App\Services\Chatter\ChatterService;

class DepartmentService
{
    public function __construct(
        private readonly ChatterService $chatterService
    ) {}

    public function create(array $data): Department
    {
        $dept = Department::create($data);
        $this->chatterService->logCreated($dept, 'Department');
        return $dept;
    }

    public function update(Department $dept, array $data): Department
    {
        $changes = $this->detectChanges($dept, $data);
        $dept->update($data);

        if (!empty($changes)) {
            $this->chatterService->logUpdated($dept, $changes, 'Department');
        }

        return $dept->fresh();
    }

    public function archive(Department $dept): Department
    {
        $dept->update(['active' => false]);
        $this->chatterService->logArchived($dept, 'Department');
        return $dept;
    }

    public function unarchive(Department $dept): Department
    {
        $dept->update(['active' => true]);
        $this->chatterService->logUnarchived($dept, 'Department');
        return $dept;
    }

    public function delete(Department $dept): void
    {
        $this->chatterService->log($dept, 'Department deleted.', 'system');
        $dept->delete();
    }

    private function detectChanges(Department $dept, array $data): array
    {
        $changes = [];

        foreach ($dept->chatterTracked as $field => $label) {
            if (!array_key_exists($field, $data)) continue;

            $old = (string) ($dept->{$field} ?? '');
            $new = (string) ($data[$field] ?? '');

            if ($old === $new) continue;

            $changes[] = [
                'field' => $field,
                'label' => $label,
                'from'  => $this->resolveValue($field, $dept->{$field}),
                'to'    => $this->resolveValue($field, $data[$field]),
            ];
        }

        return $changes;
    }

    private function resolveValue(string $field, mixed $value): string
    {
        if ($value === null || $value === '') return '—';

        return match ($field) {
            'company_id'  => \App\Models\Settings\Company::find($value)?->name ?? "#{$value}",
            'parent_id'   => Department::find($value)?->name ?? "#{$value}",
            'manager_id'  => \App\Models\Employees\Employee::find($value)?->name ?? "#{$value}",
            default       => (string) $value,
        };
    }
}
