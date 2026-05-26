<?php

namespace App\Policies\Concerns;

use App\Services\Company\CompanyContextService;
use Illuminate\Database\Eloquent\Model;

/**
 * Policy-level defense-in-depth for multi-tenant scope.
 *
 * Controllers enforce company scope today (Rule 5), but `@can(...)` checks in
 * Blade and standalone Gate calls outside a controller bypass that gate. This
 * trait makes the policy itself reject cross-tenant records so view-rendering
 * and external authorisation calls fail-closed without needing every caller to
 * remember the scope check.
 *
 * Usage:
 *   class MyPolicy { use ScopesByCompany;
 *       public function view(User $user, MyModel $model): bool {
 *           return $user->hasPermission('xxx.read')
 *               && $this->withinActiveCompany($model);
 *       }
 *   }
 *
 * The helper is fail-closed: empty active list = no access. Records with
 * `company_id = null` are treated as global (shared across tenants) and pass.
 */
trait ScopesByCompany
{
    /**
     * True if the model's `company_id` is in the actor's active companies
     * (or the column is null = global record). False if active list is empty
     * or the company_id is set but not in scope.
     */
    protected function withinActiveCompany(Model $model): bool
    {
        $companyId = $model->getAttribute('company_id');

        // Global / cross-company record (no company_id column or null value).
        if ($companyId === null) {
            return true;
        }

        $activeCompanyIds = app(CompanyContextService::class)->getActiveCompanyIds();

        return !empty($activeCompanyIds) && in_array((int) $companyId, $activeCompanyIds, true);
    }
}
