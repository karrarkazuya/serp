<?php

namespace App\Models\Employees;

use App\Models\Settings\Company;
use App\Models\User;
use App\Traits\HasChatter;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class RequestBalanceConfig extends Model
{
    use HasChatter, SoftDeletes;

    protected $table = 'hr_request_balance_configs';

    public array $chatterTracked = [
        'company_id'                => 'Company',
        'leave_days_per_month'      => 'Leave Days per Month',
        'leave_days_max'            => 'Leave Days Max',
        'time_off_hours_per_month'  => 'Time Off Hours per Month',
    ];

    protected $fillable = [
        'uuid', 'company_id',
        'leave_days_per_month', 'leave_days_max', 'time_off_hours_per_month',
        'created_by', 'updated_by',
    ];

    protected $casts = [
        'leave_days_per_month'     => 'decimal:2',
        'leave_days_max'           => 'decimal:2',
        'time_off_hours_per_month' => 'decimal:2',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}
