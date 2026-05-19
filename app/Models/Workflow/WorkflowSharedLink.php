<?php

namespace App\Models\Workflow;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Str;

class WorkflowSharedLink extends Model
{
    protected $table = 'workflow_shared_links';

    protected $fillable = [
        'uuid',
        'shareable_type',
        'shareable_id',
        'token',
        'message',
        'enabled',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'enabled' => 'boolean',
    ];

    public function shareable(): MorphTo
    {
        return $this->morphTo();
    }

    public function shareUrl(): string
    {
        return route('share.show', $this->token);
    }

    public static function forModel(Model $model): self
    {
        return static::firstOrCreate(
            ['shareable_type' => $model->getMorphClass(), 'shareable_id' => $model->getKey()],
            ['token' => Str::random(48)]
        );
    }
}
