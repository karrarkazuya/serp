<?php

namespace App\Models\Chatter;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class ChatterMessage extends Model
{
    protected $fillable = [
        'uuid',
        'model_type',
        'model_id',
        'user_id',
        'message_type',
        'body',
        'metadata',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'metadata' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function model(): MorphTo
    {
        return $this->morphTo();
    }

    public function isLog(): bool
    {
        return $this->message_type === 'log';
    }

    public function isComment(): bool
    {
        return $this->message_type === 'comment';
    }

    public function isSystem(): bool
    {
        return $this->message_type === 'system';
    }
}
