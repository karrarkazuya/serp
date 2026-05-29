<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class UserFavoriteSearch extends Model
{
    use SoftDeletes;

    protected $table = 'user_favorite_searches';

    protected $fillable = [
        'user_id',
        'model_class',
        'name',
        'query_string',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeForUser(Builder $query, int $userId): Builder
    {
        return $query->where('user_id', $userId);
    }

    public function scopeForModel(Builder $query, string $modelClass): Builder
    {
        return $query->where('model_class', $modelClass);
    }
}
