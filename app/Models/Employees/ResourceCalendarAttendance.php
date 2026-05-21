<?php

namespace App\Models\Employees;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ResourceCalendarAttendance extends Model
{
    protected $table = 'hr_resource_calendar_attendances';

    protected $fillable = [
        'calendar_id', 'day_of_week', 'hour_from', 'hour_to', 'day_period', 'sequence',
    ];

    public function calendar(): BelongsTo
    {
        return $this->belongsTo(ResourceCalendar::class, 'calendar_id');
    }

    public function getDayNameAttribute(): string
    {
        return ResourceCalendar::$dayNames[$this->day_of_week] ?? 'Unknown';
    }

    public function getHourFromFormattedAttribute(): string
    {
        $h = floor($this->hour_from);
        $m = ($this->hour_from - $h) * 60;
        return sprintf('%02d:%02d', $h, $m);
    }

    public function getHourToFormattedAttribute(): string
    {
        $value = $this->hour_to > 24 ? $this->hour_to - 24 : $this->hour_to;
        $h = floor($value);
        $m = round(($value - $h) * 60);
        $str = sprintf('%02d:%02d', $h, $m);
        return $this->hour_to > 24 ? $str . ' +1' : $str;
    }

    public function getIsNextDayAttribute(): bool
    {
        return $this->hour_to > 24;
    }

    public function getHourToClockAttribute(): string
    {
        $value = $this->hour_to > 24 ? $this->hour_to - 24 : $this->hour_to;
        $h = floor($value);
        $m = round(($value - $h) * 60);
        return sprintf('%02d:%02d', $h, $m);
    }
}
