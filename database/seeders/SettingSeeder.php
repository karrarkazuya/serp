<?php

namespace Database\Seeders;

use App\Models\Settings\Setting;
use Illuminate\Database\Seeder;

class SettingSeeder extends Seeder
{
    public function run(): void
    {
        $defaults = [
            ['key' => 'company_name',    'value' => 'SERP ERP',           'group' => 'general', 'type' => 'string', 'label' => 'Company Name'],
            ['key' => 'company_email',   'value' => 'admin@example.com',  'group' => 'general', 'type' => 'string', 'label' => 'Company Email'],
            ['key' => 'company_phone',   'value' => '',                   'group' => 'general', 'type' => 'string', 'label' => 'Company Phone'],
            ['key' => 'company_website', 'value' => '',                   'group' => 'general', 'type' => 'string', 'label' => 'Website'],
            ['key' => 'company_address', 'value' => '',                   'group' => 'general', 'type' => 'string', 'label' => 'Address'],
        ];

        foreach ($defaults as $setting) {
            Setting::updateOrCreate(['key' => $setting['key']], $setting);
        }
    }
}
