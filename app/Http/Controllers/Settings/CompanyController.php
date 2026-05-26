<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\StoreCompanyRequest;
use App\Http\Requests\Settings\UpdateCompanyRequest;
use App\Models\Settings\Company;
use App\Services\Company\CompanyService;
use App\Services\FileService;
use App\Helpers\GroupsQuery;
use App\Helpers\SearchFilters;
use App\Helpers\SortsTable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CompanyController extends Controller
{
    public function __construct(
        private readonly CompanyService $companyService,
        private readonly FileService $fileService,
    ) {}

    public function read(Request $request)
    {
        $this->authorize('viewAny', Company::class);

        $query = Company::withCount('users');

        SearchFilters::apply($query, $request);

        if ($request->get('filter') === 'inactive') {
            $query->where('active', false);
        } elseif ($request->get('filter') === 'all') {
            // no filter
        } else {
            $query->active();
        }

        $groupBy = $request->query('group_by');
        if ($groupBy) {
            $fields = SearchFilters::fieldsFor(Company::class);
            if (isset($fields[$groupBy])) {
                $records = (clone $query)->orderBy('id')->get();
                $groups  = GroupsQuery::apply($records, $fields[$groupBy]);
                return view('settings.companies.index', compact('groups'));
            }
        }

        SortsTable::apply($query, $request);

        $companies = $query->paginate(20)->withQueryString();

        return view('settings.companies.index', compact('companies'));
    }

    public function show(Company $company)
    {
        $this->authorize('view', $company);

        $company->load(['creator', 'updater', 'users']);
        return view('settings.companies.show', compact('company'));
    }

    public function create()
    {
        $this->authorize('create', Company::class);

        return view('settings.companies.create');
    }

    public function store(StoreCompanyRequest $request)
    {
        $data = $request->validated();

        $fileRecord = null;
        if ($request->hasFile('logo')) {
            $fileRecord   = $this->fileService->store($request->file('logo'), 'logos/companies', 'settings.read', null, null, 'public');
            $data['logo'] = $fileRecord->uuid;
        }

        try {
            $company = DB::transaction(fn () => $this->companyService->create($data));
        } catch (\Throwable $e) {
            // Don't leave an orphaned File row + disk file behind if the company create fails.
            if ($fileRecord) {
                $this->fileService->forceDelete($fileRecord);
            }
            throw $e;
        }

        $fileRecord?->update(['source_type' => $company->getTable(), 'source_id' => $company->id]);

        return redirect()
            ->route('settings.companies.show', $company)
            ->with('success', 'Company created successfully.');
    }

    public function edit(Company $company)
    {
        $this->authorize('update', $company);

        return view('settings.companies.edit', compact('company'));
    }

    public function write(UpdateCompanyRequest $request, Company $company)
    {
        $data = $request->validated();

        if ($request->hasFile('logo')) {
            if ($company->logo) {
                $this->fileService->deleteByUuid($company->logo);
            }
            $fileRecord   = $this->fileService->store($request->file('logo'), 'logos/companies', 'settings.read', null, $company, 'public');
            $data['logo'] = $fileRecord->uuid;
        } elseif ($request->input('remove_logo') === '1' && $company->logo) {
            $this->fileService->deleteByUuid($company->logo);
            $data['logo'] = null;
        }

        DB::transaction(fn () => $this->companyService->update($company, $data));

        return redirect()
            ->route('settings.companies.show', $company)
            ->with('success', 'Company updated successfully.');
    }

    public function archive(Request $request, Company $company)
    {
        $this->authorize('update', $company);

        DB::transaction(fn () => $this->companyService->archive($company));

        return redirect()
            ->route('settings.companies.index')
            ->with('success', 'Company archived.');
    }

    public function unarchive(Request $request, Company $company)
    {
        $this->authorize('update', $company);

        DB::transaction(fn () => $this->companyService->unarchive($company));

        return redirect()
            ->route('settings.companies.show', $company)
            ->with('success', 'Company restored.');
    }

    public function unlink(Company $company)
    {
        $this->authorize('delete', $company);

        DB::transaction(fn () => $this->companyService->delete($company));

        return redirect()
            ->route('settings.companies.index')
            ->with('success', 'Company deleted.');
    }

    /**
     * Manage which users are allowed to access this company.
     *
     * Defense-in-depth gating:
     *   1. `companies.write` (route middleware) authorizes editing this company.
     *   2. `users.read` is required as a second permission — without it the
     *      actor has no formal authority to see the user list at all, and
     *      arbitrary `users[]` IDs would be an enumeration vector.
     *   3. Added/removed users are logged into the company's chatter so the
     *      change is auditable. Sync would otherwise be silent.
     */
    public function syncUsers(Request $request, Company $company)
    {
        $this->authorize('update', $company);
        abort_unless($request->user()->hasPermission('users.read'), 403);

        $data = $request->validate([
            'users'   => 'nullable|array',
            'users.*' => 'integer|exists:users,id',
        ]);

        $newIds = collect($data['users'] ?? [])->map(fn ($id) => (int) $id)->unique()->values();

        DB::transaction(function () use ($company, $newIds) {
            $oldIds  = $company->users()->pluck('users.id');
            $added   = \App\Models\User::whereIn('id', $newIds->diff($oldIds))->pluck('name');
            $removed = \App\Models\User::whereIn('id', $oldIds->diff($newIds))->pluck('name');

            $company->users()->sync($newIds->all());

            if ($added->isNotEmpty()) {
                $company->logSystemMessage('User access granted: ' . $added->join(', ') . '.');
            }
            if ($removed->isNotEmpty()) {
                $company->logSystemMessage('User access revoked: ' . $removed->join(', ') . '.');
            }
        });

        return back()->with('success', 'Users updated.');
    }

    public function addComment(Request $request, Company $company)
    {
        $this->authorize('comment', $company);

        $request->validate(['body' => 'required|string|max:5000']);
        DB::transaction(fn () => $company->logComment($request->body));

        return back()->with('success', 'Comment added.');
    }
}
