<?php

namespace App\Models\Accounting;

use App\Models\Contacts\Contact;
use App\Models\Settings\Company;
use App\Models\User;
use App\Traits\HasChatter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

use Illuminate\Database\Eloquent\SoftDeletes;

class AccountMove extends Model
{
    use HasChatter, SoftDeletes;

    public const STATES = [
        'draft'     => 'Draft',
        'posted'    => 'Posted',
        'cancelled' => 'Cancelled',
    ];

    public const PAYMENT_STATES = [
        'not_paid'   => 'Not Paid',
        'partial'    => 'Partially Paid',
        'in_payment' => 'In Payment',
        'paid'       => 'Paid',
        'reversed'   => 'Reversed',
    ];

    public const MOVE_TYPES = [
        'entry'       => 'Journal Entry',
        'out_invoice' => 'Customer Invoice',
        'in_invoice'  => 'Vendor Bill',
        'out_refund'  => 'Customer Credit Note',
        'in_refund'   => 'Vendor Credit Note',
    ];

    public array $chatterTracked = [
        'name'             => 'Number',
        'ref'              => 'Reference',
        'date'             => 'Date',
        'invoice_date_due' => 'Due Date',
        'payment_term_id'  => 'Payment Terms',
        'incoterm_id'      => 'Incoterm',
        'invoice_origin'   => 'Source Document',
        'journal_id'       => 'Journal',
        'partner_id'       => 'Partner',
        'state'            => 'State',
        'narration'        => 'Narration',
    ];

    public array $sortable = [
        'name'         => 'name',
        'date'         => 'date',
        'state'        => 'state',
        'amount_total' => 'amount_total',
    ];

    public array $searchable = [
        'name' => ['label' => 'Number', 'column' => 'name', 'type' => 'string'],
        'ref'  => ['label' => 'Reference', 'column' => 'ref', 'type' => 'string'],
        'date' => ['label' => 'Date', 'column' => 'date', 'type' => 'date'],
        'state'=> ['label' => 'State', 'column' => 'state', 'options' => self::STATES],
        'payment_state'=> ['label' => 'Payment State', 'column' => 'payment_state', 'options' => self::PAYMENT_STATES],
        'amount_total' => ['label' => 'Amount', 'column' => 'amount_total', 'type' => 'numeric'],
        'journal_id' => [
            'label'    => 'Journal',
            'column'   => 'journal_id',
            'type'     => 'relation',
            'relation' => ['table' => 'account_journals', 'field' => 'name'],
        ],
        'partner_id' => [
            'label'    => 'Partner',
            'column'   => 'partner_id',
            'type'     => 'relation',
            'relation' => ['table' => 'contacts', 'field' => 'name'],
        ],
        'company_id' => [
            'label'    => 'Company',
            'column'   => 'company_id',
            'type'     => 'relation',
            'relation' => ['table' => 'companies', 'field' => 'name'],
        ],
    ];

    protected $fillable = [
        'company_id',
        'journal_id',
        'partner_id',
        'reversed_move_id',
        'payment_term_id',
        'incoterm_id',
        'name',
        'ref',
        'date',
        'invoice_date',
        'invoice_date_due',
        'invoice_origin',
        'state',
        'payment_state',
        'move_type',
        'currency',
        'amount_total',
        'narration',
        'posted_at',
        'posted_by',
    ];

    protected $casts = [
        'date'             => 'date',
        'invoice_date'     => 'date',
        'invoice_date_due' => 'date',
        'posted_at'        => 'datetime',
        'amount_total'     => 'decimal:4',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function journal(): BelongsTo
    {
        return $this->belongsTo(AccountJournal::class, 'journal_id');
    }

    public function partner(): BelongsTo
    {
        return $this->belongsTo(Contact::class, 'partner_id');
    }

    public function paymentTerm(): BelongsTo
    {
        return $this->belongsTo(AccountingPaymentTerm::class, 'payment_term_id');
    }

    public function incoterm(): BelongsTo
    {
        return $this->belongsTo(AccountingIncoterm::class, 'incoterm_id');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(AccountMoveLine::class, 'move_id')->orderBy('sequence')->orderBy('id');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(AccountPayment::class, 'paired_document_id');
    }

    public function reversedMove(): BelongsTo
    {
        return $this->belongsTo(AccountMove::class, 'reversed_move_id');
    }

    public function reversal(): HasMany
    {
        return $this->hasMany(AccountMove::class, 'reversed_move_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function poster(): BelongsTo
    {
        return $this->belongsTo(User::class, 'posted_by');
    }

    public function scopeDraft(Builder $query): Builder
    {
        return $query->where('state', 'draft');
    }

    public function scopePosted(Builder $query): Builder
    {
        return $query->where('state', 'posted');
    }

    public function scopeCancelled(Builder $query): Builder
    {
        return $query->where('state', 'cancelled');
    }

    public function scopeForCompanies(Builder $query, array $companyIds): Builder
    {
        // Fail-closed: empty list = no access. See Account::scopeForCompanies.
        return $query->whereIn('company_id', $companyIds);
    }

    public function scopeSearch(Builder $query, string $search): Builder
    {
        return $query->where(function ($q) use ($search) {
            $q->where('name', 'like', "%{$search}%")
              ->orWhere('ref', 'like', "%{$search}%")
              ->orWhere('narration', 'like', "%{$search}%");
        });
    }

    public function isDraft(): bool
    {
        return $this->state === 'draft';
    }

    public function isPosted(): bool
    {
        return $this->state === 'posted';
    }

    public function isCancelled(): bool
    {
        return $this->state === 'cancelled';
    }

    public function getStateLabelAttribute(): string
    {
        // The STATES constant is kept as English-labeled for back-compat with
        // any direct array consumers (`AccountMove::STATES['draft']` returns
        // 'Draft'). For UI-facing rendering, prefer this accessor — it routes
        // through the lang files so Arabic locale gets "مسودة" / "مرحَّل"
        // instead of the raw English. Falls back to the raw state code if a
        // future state lands in the column but no translation key exists yet.
        $key = 'accounting.status_' . $this->state;
        return trans()->has($key) ? __($key) : $this->state;
    }

    public function getPaymentStateLabelAttribute(): string
    {
        $paymentState = $this->payment_state ?: 'not_paid';
        $key = 'accounting.status_' . $paymentState;
        return trans()->has($key) ? __($key) : (string) $paymentState;
    }

    public function getMoveTypeLabelAttribute(): string
    {
        $key = 'accounting.move_type_' . $this->move_type;
        return trans()->has($key) ? __($key) : (self::MOVE_TYPES[$this->move_type] ?? $this->move_type);
    }

    public function isPaid(): bool
    {
        return ($this->payment_state ?: 'not_paid') === 'paid';
    }

    public function getDisplayNameAttribute(): string
    {
        // D9 (Odoo parity): drafts use '/' as a placeholder until they're
        // posted and reserve a real sequence number. Localised label so
        // Arabic users see "(مسودة)" rather than "(Draft)".
        return ($this->name && $this->name !== '/') ? $this->name : __('accounting.draft_placeholder');
    }
}
