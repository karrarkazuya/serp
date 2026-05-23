<?php

namespace App\Models\Workflow;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

use Illuminate\Database\Eloquent\SoftDeletes;

class WorkflowAllowedUser extends Model
{
    use SoftDeletes;

    protected $table = 'workflow_allowed_users';

    protected $fillable = ['user_id', 'record_id', 'record_type'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
