<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

use Illuminate\Database\Eloquent\SoftDeletes;

class File extends Model
{
    use SoftDeletes;

    protected $table = 'files';

    protected $fillable = [
        'uuid',
        'disk',
        'path',
        'thumbnail_path',
        'original_name',
        'mime_type',
        'extension',
        'size',
        'permission_key',
        'context_type',
        'context_id',
        'source_type',
        'source_id',
        'created_by',
        'updated_by',
    ];

    public function context(): MorphTo
    {
        return $this->morphTo();
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isImage(): bool
    {
        return str_starts_with($this->mime_type ?? '', 'image/');
    }

    public function url(): string
    {
        return route('files.serve', $this->uuid);
    }

    public function thumbnailUrl(): ?string
    {
        if (!$this->thumbnail_path) {
            return null;
        }

        return route('files.thumbnail', $this->uuid);
    }

    public function sizeForHumans(): string
    {
        $bytes = (int) $this->size;
        if ($bytes < 1024) return "{$bytes} B";
        if ($bytes < 1_048_576) return round($bytes / 1024, 1) . ' KB';
        return round($bytes / 1_048_576, 1) . ' MB';
    }
}
