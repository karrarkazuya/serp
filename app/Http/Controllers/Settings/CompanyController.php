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

    /** Manage which users are allowed to access this company */
    public function syncUsers(Request $request, Company $company)
    {
        $this->authorize('update', $company);

        $request->validate([
            'users'   => 'nullable|array',
            'users.*' => 'exists:users,id',
        ]);

        DB::transaction(fn () => $company->users()->sync($request->users ?? []));

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
