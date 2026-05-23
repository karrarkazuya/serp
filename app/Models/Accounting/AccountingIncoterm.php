<?php

namespace App\Models\Accounting;

use App\Models\User;
use App\Traits\HasChatter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

use Illuminate\Database\Eloquent\SoftDeletes;

class AccountingIncoterm extends Model
{
    use HasChatter, SoftDeletes;

    protected $table = 'accounting_incoterms';

    public array $chatterTracked = [
        'code' => 'Code',
        'name' => 'Name',
    ];

    public array $sortable = [
        'code'       => 'code',
        'name'       => 'name',
        'created_at' => 'created_at',
    ];

    public array $searchable = [
        'code'       => ['label' => 'Code',       'column' => 'code',       'type' => 'string'],
        'name'       => ['label' => 'Name',        'column' => 'name',       'type' => 'string'],
        'created_at' => ['label' => 'Created on',  'column' => 'created_at', 'type' => 'datetime'],
    ];

    protected $fillable = [
        'uuid',
        'code',
        'name',
        'created_by',
        'updated_by',
    ];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
