<?php

namespace App\Models\Accounting;

use App\Models\Contacts\Contact;
use App\Models\Settings\Company;
use App\Models\User;
use App\Traits\HasChatter;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AccountPayment extends Model
{
    use HasChatter;

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
        'uuid',
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
        'created_by',
        'updated_by',
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
}
