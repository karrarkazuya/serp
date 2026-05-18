<?php

namespace Database\Seeders;

use App\Models\Contacts\Contact;
use App\Models\Contacts\Tag;
use App\Models\Settings\Company;
use App\Models\User;
use Faker\Factory as Faker;
use Illuminate\Database\Seeder;

class ContactSeeder extends Seeder
{
    public function run(): void
    {
        $faker    = Faker::create();
        $admin    = User::where('email', 'admin@example.com')->first();
        $acme     = Company::where('name', 'Acme Holdings')->first();
        $techStart = Company::where('name', 'TechStart Europe')->first();
        $gulf     = Company::where('name', 'Gulf Operations LLC')->first();
        $companies = collect([$acme, $techStart, $gulf])->filter();
        $tags     = Tag::all();

        // ── 5 fixed contacts ──────────────────────────────────────────────────

        $fixed = [
            [
                'name'         => 'Acme Corporation',
                'contact_type' => 'company',
                'email'        => 'info@acme.com',
                'phone'        => '+1 555-100-0001',
                'website'      => 'https://acme.com',
                'street'       => '123 Industrial Ave',
                'city'         => 'New York',
                'state'        => 'NY',
                'country'      => 'United States',
                'zip'          => '10001',
                'active'       => true,
                'company_id'   => $acme?->id,
                'created_by'   => $admin?->id,
                'updated_by'   => $admin?->id,
            ],
            [
                'name'         => 'Jane Smith',
                'company_name' => 'Acme Corporation',
                'contact_type' => 'individual',
                'email'        => 'jane.smith@acme.com',
                'phone'        => '+1 555-100-0002',
                'mobile'       => '+1 555-200-0002',
                'job_position' => 'Chief Executive Officer',
                'city'         => 'New York',
                'state'        => 'NY',
                'country'      => 'United States',
                'active'       => true,
                'company_id'   => $acme?->id,
                'created_by'   => $admin?->id,
                'updated_by'   => $admin?->id,
            ],
            [
                'name'         => 'TechStart Ltd',
                'contact_type' => 'company',
                'email'        => 'hello@techstart.io',
                'phone'        => '+44 20 1234 5678',
                'website'      => 'https://techstart.io',
                'street'       => '10 Silicon Way',
                'city'         => 'London',
                'country'      => 'United Kingdom',
                'zip'          => 'EC1A 1BB',
                'active'       => true,
                'company_id'   => $techStart?->id,
                'created_by'   => $admin?->id,
                'updated_by'   => $admin?->id,
            ],
            [
                'name'         => 'Mohammed Al-Rashid',
                'contact_type' => 'individual',
                'email'        => 'm.alrashid@example.com',
                'phone'        => '+971 50 123 4567',
                'job_position' => 'Sales Director',
                'city'         => 'Dubai',
                'country'      => 'UAE',
                'active'       => true,
                'company_id'   => $gulf?->id,
                'created_by'   => $admin?->id,
                'updated_by'   => $admin?->id,
            ],
            [
                'name'         => 'Old Supplier Co',
                'contact_type' => 'company',
                'email'        => 'contact@oldsupplier.com',
                'city'         => 'Chicago',
                'state'        => 'IL',
                'country'      => 'United States',
                'active'       => false,
                'notes'        => 'Archived — contract ended in 2024.',
                'company_id'   => $acme?->id,
                'created_by'   => $admin?->id,
                'updated_by'   => $admin?->id,
            ],
        ];

        foreach ($fixed as $data) {
            $contact = Contact::updateOrCreate(
                ['name' => $data['name'], 'email' => $data['email'] ?? null],
                $data
            );
            $contact->logMessage('Contact created.', 'log');
        }

        // ── 195 generated contacts (200 total) ───────────────────────────────

        $jobTitles = [
            'Account Manager', 'Sales Representative', 'Marketing Manager',
            'Product Manager', 'Software Engineer', 'Financial Analyst',
            'Operations Manager', 'Business Development Manager', 'HR Manager',
            'Project Manager', 'Director of Sales', 'CTO', 'CFO', 'COO',
            'Procurement Officer', 'Supply Chain Manager', 'Legal Counsel',
            'Customer Success Manager', 'UX Designer', 'Data Analyst',
        ];

        for ($i = 0; $i < 195; $i++) {
            $isCompany = $faker->boolean(25);
            $company   = $companies->isNotEmpty() ? $companies->random() : null;
            $active    = $faker->boolean(88);

            $data = [
                'name'         => $isCompany ? $faker->company : $faker->name,
                'contact_type' => $isCompany ? 'company' : 'individual',
                'email'        => $faker->unique()->safeEmail,
                'phone'        => $faker->boolean(75) ? $faker->phoneNumber : null,
                'mobile'       => !$isCompany && $faker->boolean(55) ? $faker->phoneNumber : null,
                'job_position' => !$isCompany && $faker->boolean(65) ? $faker->randomElement($jobTitles) : null,
                'company_name' => !$isCompany && $faker->boolean(45) ? $faker->company : null,
                'website'      => $isCompany && $faker->boolean(55) ? 'https://www.' . $faker->domainName : null,
                'street'       => $faker->boolean(65) ? $faker->streetAddress : null,
                'city'         => $faker->boolean(65) ? $faker->city : null,
                'state'        => $faker->boolean(60) ? $faker->state : null,
                'country'      => $faker->boolean(65) ? $faker->country : null,
                'zip'          => $faker->boolean(60) ? $faker->postcode : null,
                'tax_id'       => $isCompany && $faker->boolean(35) ? $faker->numerify('##-#######') : null,
                'notes'        => $faker->boolean(15) ? $faker->sentences(rand(1, 3), true) : null,
                'active'       => $active,
                'company_id'   => $company?->id,
                'created_by'   => $admin?->id,
                'updated_by'   => $admin?->id,
            ];

            $contact = Contact::create($data);

            // Attach random tags (0–2)
            if ($tags->isNotEmpty() && $faker->boolean(40)) {
                $contact->tags()->sync(
                    $tags->random(rand(1, min(2, $tags->count())))->pluck('id')->toArray()
                );
            }
        }
    }
}
