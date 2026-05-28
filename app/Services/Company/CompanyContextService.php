<?php

namespace App\Services\Company;

use App\Models\Settings\Company;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

class CompanyContextService
{
    private const SESSION_KEY = 'active_company_ids';

    /**
     * Returns the company IDs currently active for the authenticated user.
     * Validates against the user's allowed companies.
     */
    public function getActiveCompanyIds(): array
    {
        $user = Auth::user();

        if (!$user) {
            return [];
        }

        $allowedIds = $user->getAllowedCompanyIds();

        // If user has no allowed companies return empty (show nothing)
        if (empty($allowedIds)) {
            return [];
        }

        $stored = session(self::SESSION_KEY);

        // First request — bootstrap from user's default company or all allowed
        if ($stored === null) {
            $default = $user->company_id && in_array($user->company_id, $allowedIds)
                ? [$user->company_id]
                : $allowedIds;

            session([self::SESSION_KEY => $default]);
            return $default;
        }

        // Cast to int and validate against allowed list
        $valid = array_values(array_intersect(array_map('intval', (array) $stored), $allowedIds));

        if (empty($valid)) {
            session([self::SESSION_KEY => $allowedIds]);
            return $allowedIds;
        }

        return $valid;
    }

    /**
     * Switch the user's active company selection.
     * Only IDs from the user's allowed list are accepted.
     *
     * @param  int[]  $companyIds
     */
    public function switch(array $companyIds): void
    {
        $user = Auth::user();

        if (!$user) {
            return;
        }

        $allowedIds = $user->getAllowedCompanyIds();
        $filtered   = array_values(array_intersect(array_map('intval', $companyIds), $allowedIds));

        // Must have at least one company selected
        if (empty($filtered)) {
            $filtered = empty($allowedIds) ? [] : [$allowedIds[0]];
        }

        session([self::SESSION_KEY => $filtered]);
    }

    /**
     * Return Company models for the current active selection.
     */
    public function getActiveCompanies(): \Illuminate\Database\Eloquent\Collection
    {
        $ids = $this->getActiveCompanyIds();

        if (empty($ids)) {
            return Company::newModelInstance()->newCollection();
        }

        return Company::whereIn('id', $ids)->orderBy('name')->get();
    }

    /**
     * Human-readable label for the navbar (e.g. "Acme Corp" or "2 Companies").
     */
    public function getLabel(): string
    {
        $companies = $this->getActiveCompanies();

        if ($companies->isEmpty()) {
            return 'No Company';
        }

        if ($companies->count() === 1) {
            return $companies->first()->name;
        }

        return $companies->count() . ' Companies';
    }

}
