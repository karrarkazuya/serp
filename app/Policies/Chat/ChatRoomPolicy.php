<?php

namespace App\Policies\Chat;

use App\Models\Chat\ChatRoom;
use App\Models\User;

class ChatRoomPolicy
{
    /**
     * Channels are open to all workspace members.
     * DMs and group chats are restricted to their members only.
     */
    public function view(User $user, ChatRoom $room): bool
    {
        if (!$room->active) {
            return false;
        }

        if ($room->isChannel()) {
            return true;
        }

        return $room->members()->where('user_id', $user->id)->exists();
    }

    public function post(User $user, ChatRoom $room): bool
    {
        return $this->view($user, $room);
    }
}
