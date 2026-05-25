<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Apply each employee's planned working schedule for today and refill their
// 30-day planning buffer. Belt-and-suspenders idempotency: withoutOverlapping
// blocks concurrent runs at the scheduler level, the command itself uses a
// Cache::lock and a hr_planned_schedule_runs row to no-op on re-invocation.
Schedule::command('attendance:apply-planned-schedules')
    ->dailyAt('00:00')
    ->withoutOverlapping(10)
    ->runInBackground();
