<?php

namespace Database\Seeders;

use App\Models\Contacts\Tag;
use Illuminate\Database\Seeder;

class TagSeeder extends Seeder
{
    public function run(): void
    {
        $tags = [
            ['name' => 'Customer',   'color' => '#10B981'],
            ['name' => 'Supplier',   'color' => '#3B82F6'],
            ['name' => 'Partner',    'color' => '#8B5CF6'],
            ['name' => 'VIP',        'color' => '#F59E0B'],
            ['name' => 'Lead',       'color' => '#EF4444'],
            ['name' => 'Prospect',   'color' => '#F97316'],
            ['name' => 'Archived',   'color' => '#6B7280'],
        ];

        foreach ($tags as $tag) {
            Tag::firstOrCreate(['name' => $tag['name']], $tag);
        }
    }
}
