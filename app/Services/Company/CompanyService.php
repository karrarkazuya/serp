<?php

namespace App\Services\Company;

use App\Models\Settings\Company;
use App\Services\Chatter\ChatterService;
use Illuminate\Support\Facades\Storage;

class CompanyService
{
    public function __construct(
        private readonly ChatterService $chatterService
    ) {}

    public function create(array $data): Company
    {
        $company = Company::create($data);

        $this->chatterService->logCreated($company, 'Company');

        return $company;
    }

    public function update(Company $company, array $data): Company
    {
        $changes = $this->detectChanges($company, $data);

        $company->update($data);

        if (!empty($changes)) {
            $this->chatterService->logUpdated($company, $changes, 'Company');
        }

        return $company->fresh();
    }

    public function archive(Company $company): Company
    {
        $company->update(['active' => false]);
        $this->chatterService->logArchived($company, 'Company');

        return $company;
    }

    public function unarchive(Company $company): Company
    {
        $company->update(['active' => true]);
        $this->chatterService->logUnarchived($company, 'Company');

        return $company;
    }

    public function delete(Company $company): void
    {
        $this->chatterService->log($company, 'Company deleted.', 'system');

        if ($company->logo) {
            Storage::disk('public')->delete($company->logo);
        }

        $company->delete();
    }

    private function detectChanges(Company $company, array $data): array
    {
        $changes = [];

        foreach ($company->chatterTracked as $field => $label) {
            if (!array_key_exists($field, $data)) continue;

            $old = (string) ($company->{$field} ?? '');
            $new = (string) ($data[$field] ?? '');

            if ($old === $new) continue;

            $changes[] = [
                'field' => $field,
                'label' => $label,
                'from'  => $old ?: '—',
                'to'    => $new ?: '—',
            ];
        }

        return $changes;
    }
}
