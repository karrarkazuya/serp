<?php

namespace App\Services\Employees;

use App\Models\Employees\Job;

class JobService
{
    public function create(array $data): Job
    {
        return Job::create($data);
    }

    public function update(Job $job, array $data): Job
    {
        $job->update($data);
        return $job->fresh();
    }

    public function archive(Job $job): Job
    {
        $job->update(['active' => false]);
        return $job;
    }

    public function unarchive(Job $job): Job
    {
        $job->update(['active' => true]);
        return $job;
    }

    public function delete(Job $job): void
    {
        $job->delete();
    }
}
