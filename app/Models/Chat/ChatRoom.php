<?php

namespace App\Models\Chat;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class ChatRoom extends Model
{
    protected $table = 'chat_rooms';
    protected $fillable = ['name', 'description', 'created_by_user_id', 'active'];

    public function messages(): HasMany
    {
        return $this->hasMany(ChatMessage::class, 'room_id');
    }

    public function lastMessage(): HasOne
    {
        return $this->hasOne(ChatMessage::class, 'room_id')->latestOfMany();
    }

    public function createdByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }
}
