<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    private array $tables = [
        'users',
        'companies',
        'contacts',
        'tags',
        'roles',
        'permissions',
        'settings',
        'chatter_messages',
    ];

    private array $auditColumnsAddedByThisMigration = [
        'users',
        'tags',
        'roles',
        'permissions',
        'settings',
        'chatter_messages',
    ];

    public function up(): void
    {
        foreach ($this->tables as $tableName) {
            Schema::table($tableName, function (Blueprint $table) use ($tableName) {
                if (!Schema::hasColumn($tableName, 'uuid')) {
                    $table->uuid('uuid')->nullable()->unique()->after('id');
                }

                if (!Schema::hasColumn($tableName, 'created_by')) {
                    $table->foreignId('created_by')
                        ->nullable()
                        ->after('uuid')
                        ->constrained('users')
                        ->nullOnDelete();
                }

                if (!Schema::hasColumn($tableName, 'updated_by')) {
                    $table->foreignId('updated_by')
                        ->nullable()
                        ->after('created_by')
                        ->constrained('users')
                        ->nullOnDelete();
                }
            });

            DB::table($tableName)
                ->whereNull('uuid')
                ->orderBy('id')
                ->lazyById()
                ->each(fn ($record) => DB::table($tableName)
                    ->where('id', $record->id)
                    ->update(['uuid' => (string) Str::uuid()]));
        }
    }

    public function down(): void
    {
        foreach (array_reverse($this->tables) as $tableName) {
            Schema::table($tableName, function (Blueprint $table) use ($tableName) {
                if (in_array($tableName, $this->auditColumnsAddedByThisMigration, true)
                    && Schema::hasColumn($tableName, 'updated_by')) {
                    $table->dropConstrainedForeignId('updated_by');
                }

                if (in_array($tableName, $this->auditColumnsAddedByThisMigration, true)
                    && Schema::hasColumn($tableName, 'created_by')) {
                    $table->dropConstrainedForeignId('created_by');
                }

                if (Schema::hasColumn($tableName, 'uuid')) {
                    $table->dropUnique("{$tableName}_uuid_unique");
                    $table->dropColumn('uuid');
                }
            });
        }
    }
};
