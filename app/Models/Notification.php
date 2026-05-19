<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Notification extends Model
{
    protected $fillable = [
        'uuid',
        'user_id',
        'title',
        'body',
        'url',
        'seen_at',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'seen_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isSeen(): bool
    {
        return $this->seen_at !== null;
    }

    public function markSeen(): void
    {
        if (!$this->seen_at) {
            $this->update(['seen_at' => now()]);
        }
    }
}
