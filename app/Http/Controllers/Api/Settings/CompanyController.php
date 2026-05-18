<?php

namespace App\Http\Controllers\Api\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\StoreCompanyRequest;
use App\Http\Requests\Settings\UpdateCompanyRequest;
use App\Models\Settings\Company;
use App\Services\Company\CompanyService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CompanyController extends Controller
{
    public function __construct(private readonly CompanyService $companyService) {}

    public function read(Request $request): JsonResponse
    {
        $query = Company::query();

        if ($search = $request->get('search')) {
            $query->search($search);
        }

        if ($request->get('filter') !== 'all') {
            $query->active();
        }

        return response()->json(
            $query->orderBy('name')->paginate($request->integer('per_page', 20))
        );
    }

    public function show(Company $company): JsonResponse
    {
        return response()->json($company->load(['creator', 'updater']));
    }

    public function create(StoreCompanyRequest $request): JsonResponse
    {
        $company = DB::transaction(fn () => $this->companyService->create($request->validated()));

        return response()->json([
            'message' => 'Company created successfully.',
            'data'    => $company,
        ], 201);
    }

    public function write(UpdateCompanyRequest $request, Company $company): JsonResponse
    {
        $company = DB::transaction(fn () => $this->companyService->update($company, $request->validated()));

        return response()->json([
            'message' => 'Company updated successfully.',
            'data'    => $company,
        ]);
    }

    public function unlink(Company $company): JsonResponse
    {
        DB::transaction(fn () => $this->companyService->delete($company));

        return response()->json(['message' => 'Company deleted.']);
    }
}
