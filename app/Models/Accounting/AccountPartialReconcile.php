<?php

namespace App\Models\Accounting;

use App\Models\Settings\Company;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

use Illuminate\Database\Eloquent\SoftDeletes;

class AccountPartialReconcile extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'company_id',
        'debit_move_line_id',
        'credit_move_line_id',
        'amount',
        'date',
    ];

    protected $casts = [
        'amount' => 'decimal:4',
        'date' => 'date',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function debitLine(): BelongsTo
    {
        return $this->belongsTo(AccountMoveLine::class, 'debit_move_line_id');
    }

    public function creditLine(): BelongsTo
    {
        return $this->belongsTo(AccountMoveLine::class, 'credit_move_line_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
