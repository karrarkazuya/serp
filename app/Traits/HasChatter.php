<?php

namespace App\Traits;

use App\Models\Chatter\ChatterMessage;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Auth;

trait HasChatter
{
    private const SYSTEM_USER_ID = 0;

    public function chatterMessages(): HasMany
    {
        return $this->hasMany(ChatterMessage::class, 'model_id')
            ->where('model_type', static::class)
            ->orderBy('created_at', 'desc');
    }

    public function comments(): HasMany
    {
        return $this->hasMany(ChatterMessage::class, 'model_id')
            ->where('model_type', static::class)
            ->where('message_type', 'comment')
            ->orderBy('created_at', 'desc');
    }

    public function logMessage(string $body, string $type = 'log', array $metadata = []): ChatterMessage
    {
        return ChatterMessage::create([
            'model_type'   => static::class,
            'model_id'     => $this->getKey(),
            'user_id'      => Auth::id() ?? self::SYSTEM_USER_ID,
            'message_type' => $type,
            'body'         => $body,
            'metadata'     => empty($metadata) ? null : $metadata,
        ]);
    }

    public function logSystemMessage(string $body, array $metadata = []): ChatterMessage
    {
        return $this->logMessage($body, 'system', $metadata);
    }

    public function logComment(string $body): ChatterMessage
    {
        return $this->logMessage($body, 'comment');
    }
}
