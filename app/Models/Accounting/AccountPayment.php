<?php

namespace App\Models\Accounting;

use App\Models\Contacts\Contact;
use App\Models\Settings\Company;
use App\Models\User;
use App\Traits\HasChatter;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;

use Illuminate\Database\Eloquent\SoftDeletes;

class AccountPayment extends Model
{
    use HasChatter, SoftDeletes;

    public const STATES = [
        'draft'     => 'Draft',
        'posted'    => 'In Process',
        'cancelled' => 'Cancelled',
    ];

    public const PAYMENT_METHODS = [
        'manual' => 'Manual',
        'cheque' => 'Cheque',
        'bank_transfer' => 'Bank Transfer',
    ];

    public array $chatterTracked = [
        'payment_type' => 'Type',
        'date'         => 'Payment Date',
        'amount'       => 'Amount',
        'currency'     => 'Currency',
        'journal_id'   => ['label' => 'Journal',  'table' => 'account_journals', 'column' => 'name'],
        'partner_id'   => ['label' => 'Partner',  'table' => 'contacts',         'column' => 'name'],
        'memo'         => 'Memo',
    ];

    public array $sortable = [
        'date'         => 'date',
        'amount'       => 'amount',
        'payment_type' => 'payment_type',
        'currency'     => 'currency',
        'created_at'   => 'created_at',
    ];

    public array $searchable = [
        'memo'         => ['label' => 'Memo',         'column' => 'memo',         'type' => 'string'],
        'date'         => ['label' => 'Date',          'column' => 'date',         'type' => 'date'],
        'amount'       => ['label' => 'Amount',        'column' => 'amount',       'type' => 'numeric'],
        'currency'     => ['label' => 'Currency',      'column' => 'currency',     'type' => 'string'],
        'payment_type' => ['label' => 'Payment Type',  'column' => 'payment_type', 'type' => 'string'],
        'journal_id'   => [
            'label'    => 'Journal',
            'column'   => 'journal_id',
            'type'     => 'relation',
            'relation' => ['table' => 'account_journals', 'field' => 'name'],
        ],
        'partner_id'   => [
            'label'    => 'Partner',
            'column'   => 'partner_id',
            'type'     => 'relation',
            'relation' => ['table' => 'contacts', 'field' => 'name'],
        ],
        'company_id'   => [
            'label'    => 'Company',
            'column'   => 'company_id',
            'type'     => 'relation',
            'relation' => ['table' => 'companies', 'field' => 'name'],
        ],
        'created_at'   => ['label' => 'Created on', 'column' => 'created_at', 'type' => 'datetime'],
    ];

    protected $fillable = [
        'company_id',
        'journal_id',
        'move_id',
        'partner_id',
        'paired_document_id',
        'payment_type',
        'date',
        'amount',
        'currency',
        'memo',
        'state',
        'payment_method',
        'bank_reference',
        'cheque_number',
        'destination_account_id',
    ];

    protected $casts = [
        'date' => 'date',
        'amount' => 'decimal:4',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function journal(): BelongsTo
    {
        return $this->belongsTo(AccountJournal::class, 'journal_id');
    }

    public function move(): BelongsTo
    {
        return $this->belongsTo(AccountMove::class, 'move_id');
    }

    public function partner(): BelongsTo
    {
        return $this->belongsTo(Contact::class, 'partner_id');
    }

    public function pairedDocument(): BelongsTo
    {
        return $this->belongsTo(AccountMove::class, 'paired_document_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function destinationAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'destination_account_id');
    }

    public function isDraft(): bool    { return $this->state === 'draft'; }
    public function isPosted(): bool   { return $this->state === 'posted'; }
    public function isPaid(): bool     { return $this->payment_state === 'paid'; }

    public function getStateLabelAttribute(): string
    {
        // 'posted' renders as "In Process" for AccountPayment (Odoo parity:
        // a payment isn't truly settled until bank reconciliation clears it),
        // which is why the lookup uses `status_in_process` instead of
        // `status_posted` like AccountMove does.
        $map = ['draft' => 'status_draft', 'posted' => 'status_in_process', 'cancelled' => 'status_cancelled'];
        $key = 'accounting.' . ($map[$this->state] ?? '');
        return $key !== 'accounting.' && trans()->has($key)
            ? __($key)
            : (self::STATES[$this->state] ?? ucfirst($this->state));
    }

    public function getPaymentMethodLabelAttribute(): string
    {
        if (!$this->payment_method) return '';
        $key = 'accounting.payment_method_' . $this->payment_method;
        return trans()->has($key) ? __($key) : (self::PAYMENT_METHODS[$this->payment_method] ?? $this->payment_method);
    }

    public function scopeForCompanies(Builder $query, array $companyIds): Builder
    {
        return $query->whereIn('company_id', $companyIds);
    }
}
