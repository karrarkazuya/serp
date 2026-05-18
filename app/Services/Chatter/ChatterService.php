<?php

namespace App\Services\Chatter;

use App\Models\Chatter\ChatterMessage;
use Illuminate\Support\Facades\Auth;

class ChatterService
{
    private const SYSTEM_USER_ID = 0;

    public function log(
        object $model,
        string $body,
        string $type = 'log',
        array $metadata = []
    ): ChatterMessage {
        return ChatterMessage::create([
            'model_type'   => get_class($model),
            'model_id'     => $model->getKey(),
            'user_id'      => Auth::id() ?? self::SYSTEM_USER_ID,
            'message_type' => $type,
            'body'         => $body,
            'metadata'     => empty($metadata) ? null : $metadata,
        ]);
    }

    public function logCreated(object $model, string $label = 'Record'): ChatterMessage
    {
        return $this->log($model, "{$label} created.", 'log');
    }

    public function logUpdated(object $model, array $changes, string $label = 'Record'): ChatterMessage
    {
        return $this->log($model, "{$label} updated.", 'log', ['changes' => $changes]);
    }

    public function logArchived(object $model, string $label = 'Record'): ChatterMessage
    {
        return $this->log($model, "{$label} archived.", 'system');
    }

    public function logUnarchived(object $model, string $label = 'Record'): ChatterMessage
    {
        return $this->log($model, "{$label} restored.", 'system');
    }

    public function getMessages(object $model): \Illuminate\Database\Eloquent\Collection
    {
        return ChatterMessage::where('model_type', get_class($model))
            ->where('model_id', $model->getKey())
            ->with('user')
            ->orderBy('created_at', 'desc')
            ->get();
    }
}
