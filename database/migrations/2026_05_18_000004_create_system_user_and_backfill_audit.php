<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    private const SYSTEM_USER_ID = 0;

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

    public function up(): void
    {
        DB::table('users')->updateOrInsert(
            ['id' => self::SYSTEM_USER_ID],
            [
                'uuid' => '00000000-0000-0000-0000-000000000000',
                'name' => 'System',
                'email' => 'system@example.com',
                'password' => Hash::make(Str::random(64)),
                'active' => false,
                'job_position' => 'System User',
                'created_by' => self::SYSTEM_USER_ID,
                'updated_by' => self::SYSTEM_USER_ID,
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );

        foreach ($this->tables as $table) {
            if (Schema::hasColumn($table, 'created_by')) {
                DB::table($table)->whereNull('created_by')->update(['created_by' => self::SYSTEM_USER_ID]);
            }

            if (Schema::hasColumn($table, 'updated_by')) {
                DB::table($table)->whereNull('updated_by')->update(['updated_by' => self::SYSTEM_USER_ID]);
            }
        }
    }

    public function down(): void
    {
        foreach ($this->tables as $table) {
            if (Schema::hasColumn($table, 'created_by')) {
                DB::table($table)->where('created_by', self::SYSTEM_USER_ID)->update(['created_by' => null]);
            }

            if (Schema::hasColumn($table, 'updated_by')) {
                DB::table($table)->where('updated_by', self::SYSTEM_USER_ID)->update(['updated_by' => null]);
            }
        }

        DB::table('users')->where('id', self::SYSTEM_USER_ID)->delete();
    }
};
