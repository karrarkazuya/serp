<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Mark ticket-linked rooms as type='ticket' so they don't appear in workspace chat
        $ticketRoomIds = DB::table('workflow_tickets')
            ->whereNotNull('chat_room_id')
            ->pluck('chat_room_id');

        if ($ticketRoomIds->isNotEmpty()) {
            DB::table('chat_rooms')
                ->whereIn('id', $ticketRoomIds)
                ->update(['type' => 'ticket']);
        }

        // Seed creator membership for existing channel rooms
        DB::table('chat_rooms')
            ->where('type', 'channel')
            ->whereNotNull('created_by_user_id')
            ->get()
            ->each(function ($room) {
                $exists = DB::table('chat_room_members')
                    ->where('room_id', $room->id)
                    ->where('user_id', $room->created_by_user_id)
                    ->exists();

                if (!$exists) {
                    DB::table('chat_room_members')->insert([
                        'room_id'    => $room->id,
                        'user_id'    => $room->created_by_user_id,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            });
    }

    public function down(): void {}
};
