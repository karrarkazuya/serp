<?php

namespace App\Http\Controllers\Employees\Concerns;

use App\Models\Employees\Employee;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Collection;

/**
 * Shared scope helpers for the employee-allocation controllers
 * (Bonus / Appreciation / Sanction / Reward / JobGrade / Position).
 *
 * Allocation records themselves have no `company_id` column — they live in
 * `hr_*` tables and link to employees via a pivot. Multi-tenant scope is
 * therefore "the actor and the allocation share at least one employee":
 *
 *   - Listing: only show allocations that have at least one employee in the
 *     actor's active companies (and count those employees only).
 *   - Show/edit/etc.: 403 if no shared employee — otherwise the actor would
 *     be able to mutate (or merely view) an allocation that exists only in
 *     other tenants.
 *   - syncEmployees: scope requested IDs to active companies and keep
 *     out-of-scope pivot rows untouched (silent-keep pattern) so a single-
 *     company actor can't strip cross-tenant attachments.
 *
 * Requires the host controller to expose `$this->companyContext`.
 */
trait ScopesEmployeeAllocation
{
    /**
     * Apply the "shared-employee" filter + scoped count to a listing query.
     */
    protected function scopeAllocationListing(Builder $query): Builder
    {
        $activeCompanyIds = $this->companyContext->getActiveCompanyIds();

        return $query
            ->whereHas('employees', function ($q) use ($activeCompanyIds) {
                empty($activeCompanyIds)
                    ? $q->whereRaw('1 = 0')
                    : $q->whereIn('hr_employees.company_id', $activeCompanyIds);
            })
            ->withCount(['employees' => function ($q) use ($activeCompanyIds) {
                empty($activeCompanyIds)
                    ? $q->whereRaw('1 = 0')
                    : $q->whereIn('hr_employees.company_id', $activeCompanyIds);
            }]);
    }

    /**
     * Eager-load `employees` (and any extra relations) scoped to the actor's
     * active companies. Use this in show/edit so out-of-tenant employees
     * aren't disclosed in the view.
     */
    protected function loadAllocationWithScopedEmployees(Model $allocation, array $extraRelations = []): Model
    {
        $activeCompanyIds = $this->companyContext->getActiveCompanyIds();

        $allocation->load(array_merge([
            'employees' => function ($q) use ($activeCompanyIds) {
                empty($activeCompanyIds)
                    ? $q->whereRaw('1 = 0')
                    : $q->whereIn('hr_employees.company_id', $activeCompanyIds);
            },
        ], $extraRelations));

        return $allocation;
    }

    /**
     * 403 if the allocation has no employees in the actor's active companies.
     * Empty active list is treated as no access (fail-closed) — matches the
     * Accounting/Inventory modules' company-scope behavior.
     */
    protected function assertAllocationInScope(Model $allocation): void
    {
        $activeCompanyIds = $this->companyContext->getActiveCompanyIds();

        abort_unless(
            !empty($activeCompanyIds)
                && $allocation->employees()
                    ->whereIn('hr_employees.company_id', $activeCompanyIds)
                    ->exists(),
            403
        );
    }

    /**
     * Compute the new pivot ID list for an allocation::syncEmployees() call,
     * using the silent-keep pattern: scope requested IDs to active companies
     * and preserve any out-of-scope rows already attached.
     *
     * @param  iterable<int|string>  $requestedIds  raw posted IDs
     * @param  BelongsToMany         $employees     `$allocation->employees()` relation
     */
    protected function scopeRequestedEmployeeIds(iterable $requestedIds, BelongsToMany $employees): Collection
    {
        $activeCompanyIds = $this->companyContext->getActiveCompanyIds();
        $requested = collect($requestedIds)->map(fn ($id) => (int) $id)->unique();

        $scopedRequested = empty($activeCompanyIds)
            ? collect()
            : Employee::whereIn('id', $requested)
                ->whereIn('company_id', $activeCompanyIds)
                ->pluck('id');

        $outOfScopeKept = empty($activeCompanyIds)
            ? collect()
            : $employees->whereNotIn('company_id', $activeCompanyIds)->pluck('hr_employees.id');

        return $scopedRequested->merge($outOfScopeKept)->unique()->values();
    }
}
