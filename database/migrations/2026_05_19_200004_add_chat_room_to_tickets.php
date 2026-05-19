<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('workflow_tickets', function (Blueprint $table) {
            $table->foreignId('chat_room_id')->nullable()->after('id')->constrained('chat_rooms')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('workflow_tickets', function (Blueprint $table) {
            $table->dropForeignIdFor(\App\Models\Chat\ChatRoom::class, 'chat_room_id');
            $table->dropColumn('chat_room_id');
        });
    }
};
