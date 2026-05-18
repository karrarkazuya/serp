<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\StoreCompanyRequest;
use App\Http\Requests\Settings\UpdateCompanyRequest;
use App\Models\Settings\Company;
use App\Services\Company\CompanyService;
use App\Helpers\SearchFilters;
use App\Helpers\SortsTable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CompanyController extends Controller
{
    public function __construct(private readonly CompanyService $companyService) {}

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

        SortsTable::apply($query, $request);

        $companies = $query->paginate(20)->withQueryString();

        return view('settings.companies.index', compact('companies'));
    }

    public function show(Company $company)
    {
        $this->authorize('view', $company);

        $company->load(['creator', 'updater', 'users']);
        $messages = $company->chatterMessages()->with('user')->get();

        return view('settings.companies.show', compact('company', 'messages'));
    }

    public function create()
    {
        $this->authorize('create', Company::class);

        return view('settings.companies.create');
    }

    public function store(StoreCompanyRequest $request)
    {
        $company = DB::transaction(fn () => $this->companyService->create($request->validated()));

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
        DB::transaction(fn () => $this->companyService->update($company, $request->validated()));

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
}
