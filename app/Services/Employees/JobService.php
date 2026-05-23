<?php

namespace App\Services\Employees;

use App\Models\Employees\Department;
use App\Models\Employees\Job;
use App\Models\Settings\Company;
use App\Services\Chatter\ChatterService;

class JobService
{
    public function __construct(
        private readonly ChatterService $chatterService
    ) {}

    public function create(array $data): Job
    {
        $job = Job::create($data);
        $this->chatterService->logCreated($job, 'Job Position');
        return $job;
    }

    public function update(Job $job, array $data): Job
    {
        $changes = $this->detectChanges($job, $data);
        $job->update($data);

        if (!empty($changes)) {
            $this->chatterService->logUpdated($job, $changes, 'Job Position');
        }

        return $job->fresh();
    }

    public function archive(Job $job): Job
    {
        $job->update(['active' => false]);
        $this->chatterService->logArchived($job, 'Job Position');
        return $job;
    }

    public function unarchive(Job $job): Job
    {
        $job->update(['active' => true]);
        $this->chatterService->logUnarchived($job, 'Job Position');
        return $job;
    }

    public function delete(Job $job): void
    {
        $this->chatterService->log($job, 'Job Position deleted.', 'system');
        $job->delete();
    }

    private function detectChanges(Job $job, array $data): array
    {
        $changes = [];

        foreach ($job->chatterTracked as $field => $label) {
            if (!array_key_exists($field, $data)) continue;

            $old = (string) ($job->{$field} ?? '');
            $new = (string) ($data[$field] ?? '');

            if ($old === $new) continue;

            $changes[] = [
                'field' => $field,
                'label' => $label,
                'from'  => $this->resolveValue($field, $job->{$field}),
                'to'    => $this->resolveValue($field, $data[$field]),
            ];
        }

        return $changes;
    }

    private function resolveValue(string $field, mixed $value): string
    {
        if ($value === null || $value === '') return '—';

        return match ($field) {
            'company_id'    => Company::find($value)?->name ?? "#{$value}",
            'department_id' => Department::find($value)?->name ?? "#{$value}",
            'active'        => $value ? 'Yes' : 'No',
            default         => (string) $value,
        };
    }
}
