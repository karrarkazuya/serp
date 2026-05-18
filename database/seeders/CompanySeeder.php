<?php

namespace Database\Seeders;

use App\Models\Settings\Company;
use App\Models\User;
use Illuminate\Database\Seeder;

class CompanySeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::where('email', 'admin@example.com')->first();
        $basicUser = User::where('email', 'user@example.com')->first();

        $companies = [
            [
                'name'     => 'Acme Holdings',
                'email'    => 'info@acme-holdings.com',
                'phone'    => '+1 555-100-0010',
                'website'  => 'https://acme-holdings.com',
                'street'   => '1 Corporate Plaza',
                'city'     => 'New York',
                'state'    => 'NY',
                'country'  => 'United States',
                'zip'      => '10001',
                'currency' => 'USD',
                'active'   => true,
                'created_by' => $admin?->id,
                'updated_by' => $admin?->id,
            ],
            [
                'name'     => 'TechStart Europe',
                'email'    => 'hello@techstart.eu',
                'phone'    => '+44 20 9000 1234',
                'website'  => 'https://techstart.eu',
                'street'   => '10 Silicon Way',
                'city'     => 'London',
                'country'  => 'United Kingdom',
                'zip'      => 'EC1A 1BB',
                'currency' => 'GBP',
                'active'   => true,
                'created_by' => $admin?->id,
                'updated_by' => $admin?->id,
            ],
            [
                'name'     => 'Gulf Operations LLC',
                'email'    => 'ops@gulf-ops.ae',
                'phone'    => '+971 4 123 4567',
                'city'     => 'Dubai',
                'country'  => 'UAE',
                'currency' => 'AED',
                'active'   => true,
                'created_by' => $admin?->id,
                'updated_by' => $admin?->id,
            ],
        ];

        $createdCompanies = [];
        foreach ($companies as $data) {
            $company = Company::updateOrCreate(['name' => $data['name']], $data);
            $company->logMessage('Company created.', 'log');
            $createdCompanies[] = $company;
        }

        [$acme, $techStart, $gulf] = $createdCompanies;

        // Admin gets access to all companies; default is Acme
        if ($admin) {
            $admin->companies()->syncWithoutDetaching([$acme->id, $techStart->id, $gulf->id]);
            $admin->update(['company_id' => $acme->id]);
        }

        // Basic user gets Acme + TechStart; default is Acme
        if ($basicUser) {
            $basicUser->companies()->syncWithoutDetaching([$acme->id, $techStart->id]);
            $basicUser->update(['company_id' => $acme->id]);
        }
    }
}
