<?php

namespace App\Observers;

use App\Models\Settings\Company;
use Database\Seeders\CoreSeeder;

/**
 * Auto-installs the Iraqi Unified Accounting System chart of accounts + journals
 * into every newly-created company.
 *
 * Fires on Company::created so the company already has its primary key when the
 * accounts are written. Idempotent — re-firing on an existing company just
 * refreshes the rows (matched by company_id + code).
 */
class CompanyAccountingObserver
{
    public function __construct(
        private readonly CoreSeeder $core,
    ) {}

    public function created(Company $company): void
    {
        $this->core->installAccountingForCompany($company);
    }
}
