<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workflow_shared_links', function (Blueprint $table) {
            $table->id();
            $table->string('uuid')->unique()->nullable();
            $table->morphs('shareable');          // shareable_type + shareable_id
            $table->string('token', 64)->unique();
            $table->text('message')->nullable();
            $table->boolean('enabled')->default(false);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workflow_shared_links');
    }
};
