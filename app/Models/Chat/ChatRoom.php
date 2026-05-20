<?php

namespace App\Models\Chat;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class ChatRoom extends Model
{
    protected $table = 'chat_rooms';
    protected $fillable = ['name', 'description', 'created_by_user_id', 'active', 'type'];

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

    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'chat_room_members', 'room_id', 'user_id')
            ->withPivot('last_read_at')
            ->withTimestamps();
    }

    public function isDirect(): bool  { return $this->type === 'direct'; }
    public function isGroup(): bool   { return $this->type === 'group'; }
    public function isChannel(): bool { return $this->type === 'channel'; }
    public function isTicket(): bool  { return $this->type === 'ticket'; }

    public function displayName(User $forUser): string
    {
        if ($this->isChannel() || $this->isTicket()) return $this->name;
        $others = $this->members->filter(fn ($u) => $u->id !== $forUser->id);
        if ($others->isEmpty()) return $this->name ?: 'Note to self';
        return $others->pluck('name')->join(', ');
    }

    public function unreadCountFor(User $user): int
    {
        $member = $this->members->firstWhere('id', $user->id);
        if (!$member) return 0;
        $lastRead = $member->pivot->last_read_at;
        $query = $this->messages()->where('user_id', '!=', $user->id);
        if ($lastRead) {
            $query->where('created_at', '>', $lastRead);
        }
        return $query->count();
    }
}
