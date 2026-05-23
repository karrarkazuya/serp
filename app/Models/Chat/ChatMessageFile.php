<?php

namespace App\Models\Chat;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

use Illuminate\Database\Eloquent\SoftDeletes;

class ChatMessageFile extends Model
{
    use SoftDeletes;

    protected $table = 'chat_message_files';
    protected $fillable = ['uuid', 'message_id', 'disk', 'path', 'original_name', 'mime_type', 'size'];

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function message(): BelongsTo
    {
        return $this->belongsTo(ChatMessage::class);
    }

    public function isImage(): bool
    {
        return str_starts_with($this->mime_type, 'image/');
    }

    public function humanSize(): string
    {
        $bytes = $this->size;
        if ($bytes < 1024) return $bytes . ' B';
        if ($bytes < 1048576) return round($bytes / 1024, 1) . ' KB';
        return round($bytes / 1048576, 1) . ' MB';
    }
}
