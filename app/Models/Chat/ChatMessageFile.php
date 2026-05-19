<?php

namespace App\Models\Chat;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChatMessageFile extends Model
{
    protected $table = 'chat_message_files';
    protected $fillable = ['message_id', 'disk', 'path', 'original_name', 'mime_type', 'size'];

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
