<?php

namespace App\Models\Accounting;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AccountingPaymentTermLine extends Model
{
    protected $table = 'accounting_payment_term_lines';

    protected $fillable = [
        'payment_term_id',
        'value_type',
        'value',
        'days',
        'sequence',
    ];

    protected $casts = [
        'value'    => 'decimal:4',
        'days'     => 'integer',
        'sequence' => 'integer',
    ];

    public function paymentTerm(): BelongsTo
    {
        return $this->belongsTo(AccountingPaymentTerm::class, 'payment_term_id');
    }
}
