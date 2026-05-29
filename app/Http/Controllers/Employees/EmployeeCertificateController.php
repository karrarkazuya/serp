<?php

namespace App\Http\Controllers\Employees;

use App\Http\Controllers\Controller;
use App\Models\Employees\Employee;
use App\Models\Employees\EmployeeCertificate;
use App\Services\Company\CompanyContextService;
use App\Helpers\GroupsQuery;
use App\Helpers\SearchFilters;
use App\Helpers\SortsTable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class EmployeeCertificateController extends Controller
{
    public function __construct(
        private readonly CompanyContextService $companyContext,
    ) {}

    public function read(Request $request)
    {
        $this->authorize('viewAny', Employee::class);

        $activeCompanyIds = $this->companyContext->getActiveCompanyIds();

        $query = EmployeeCertificate::query()->with(['employee.company']);

        // Fail-closed multi-tenant gate (see EmployeeController::read).
        empty($activeCompanyIds)
            ? $query->whereRaw('1 = 0')
            : $query->whereHas('employee', fn ($q) => $q->whereIn('company_id', $activeCompanyIds));

        SearchFilters::apply($query, $request);

        if ($request->query('filter') === 'archived') {
            $query->where('active', false);
        } elseif ($request->query('filter') === 'all') {
            // no filter
        } else {
            $query->active();
        }

        $groupBy = $request->query('group_by');
        if ($groupBy) {
            $fields = SearchFilters::fieldsFor(EmployeeCertificate::class);
            if (isset($fields[$groupBy])) {
                $records = (clone $query)->with('employee')->orderBy('certificate_type')->get();
                $groups  = GroupsQuery::apply($records, $fields[$groupBy]);
                return view('employees.certificates.index', compact('groups'));
            }
        }

        SortsTable::apply($query, $request);

        $records = $query->paginate(50)->withQueryString();

        return view('employees.certificates.index', compact('records'));
    }

    public function show(EmployeeCertificate $certificate)
    {
        $this->authorize('viewAny', Employee::class);

        $activeCompanyIds = $this->companyContext->getActiveCompanyIds();
        abort_unless(
            !empty($activeCompanyIds) && in_array($certificate->employee?->company_id, $activeCompanyIds),
            403
        );

        $certificate->load(['employee.company', 'creator', 'updater', 'chatterMessages.user']);

        return view('employees.certificates.show', compact('certificate'));
    }

    public function create(Request $request)
    {
        $this->authorize('create', Employee::class);

        $activeCompanyIds = $this->companyContext->getActiveCompanyIds();
        $preselectedEmployee = null;
        if ($request->query('employee_id')) {
            $preselectedEmployee = Employee::find((int) $request->query('employee_id'));
            if ($preselectedEmployee && !empty($activeCompanyIds)) {
                abort_unless(in_array($preselectedEmployee->company_id, $activeCompanyIds), 403);
            }
        }

        return view('employees.certificates.create', compact('preselectedEmployee'));
    }

    public function store(Request $request)
    {
        $this->authorize('create', Employee::class);

        $activeCompanyIds = $this->companyContext->getActiveCompanyIds();

        $data = $request->validate([
            'employee_id'              => 'required|exists:hr_employees,id',
            'certificate_type'         => 'nullable|string|max:255',
            'study_type'               => 'nullable|string|max:255',
            'issuing_institution'      => 'nullable|string|max:255',
            'country'                  => 'nullable|string|max:255',
            'data_status'              => 'nullable|in:current,previous',
            'graduate_date'            => 'nullable|date',
            'affective_date'           => 'nullable|date',
            'specialization_type'      => 'nullable|in:amount,percentage',
            'financial_specialization' => 'nullable|numeric|min:0',
        ]);

        $employee = Employee::findOrFail($data['employee_id']);
        abort_unless(!empty($activeCompanyIds) && in_array($employee->company_id, $activeCompanyIds), 403);

        $certificate = DB::transaction(function () use ($data) {
            $certificate = EmployeeCertificate::create($data);
            $certificate->logSystemMessage('Certificate created.');
            return $certificate;
        });

        return redirect()->route('employees.certificates.show', $certificate)->with('success', 'Certificate created.');
    }

    public function edit(EmployeeCertificate $certificate)
    {
        $this->authorize('update', Employee::class);

        $activeCompanyIds = $this->companyContext->getActiveCompanyIds();
        abort_unless(
            !empty($activeCompanyIds) && in_array($certificate->employee?->company_id, $activeCompanyIds),
            403
        );

        $certificate->load('employee');

        return view('employees.certificates.edit', compact('certificate'));
    }

    public function write(Request $request, EmployeeCertificate $certificate)
    {
        $this->authorize('update', Employee::class);

        $activeCompanyIds = $this->companyContext->getActiveCompanyIds();
        abort_unless(
            !empty($activeCompanyIds) && in_array($certificate->employee?->company_id, $activeCompanyIds),
            403
        );

        $data = $request->validate([
            'certificate_type'         => 'nullable|string|max:255',
            'study_type'               => 'nullable|string|max:255',
            'issuing_institution'      => 'nullable|string|max:255',
            'country'                  => 'nullable|string|max:255',
            'data_status'              => 'nullable|in:current,previous',
            'graduate_date'            => 'nullable|date',
            'affective_date'           => 'nullable|date',
            'specialization_type'      => 'nullable|in:amount,percentage',
            'financial_specialization' => 'nullable|numeric|min:0',
        ]);

        DB::transaction(function () use ($certificate, $data) {
            $changes = $this->diffChanges($certificate, $data);
            $certificate->update($data);
            if ($changes) {
                $certificate->logSystemMessage('Certificate updated: ' . implode(', ', $changes) . '.');
            }
        });

        return redirect()->route('employees.certificates.show', $certificate)->with('success', 'Certificate updated.');
    }

    public function archive(EmployeeCertificate $certificate)
    {
        $this->authorize('update', Employee::class);

        $activeCompanyIds = $this->companyContext->getActiveCompanyIds();
        abort_unless(
            !empty($activeCompanyIds) && in_array($certificate->employee?->company_id, $activeCompanyIds),
            403
        );

        DB::transaction(function () use ($certificate) {
            $certificate->update(['active' => false]);
            $certificate->logSystemMessage('Certificate archived.');
        });

        return redirect()->route('employees.certificates.index')->with('success', 'Certificate archived.');
    }

    public function unarchive(EmployeeCertificate $certificate)
    {
        $this->authorize('update', Employee::class);

        $activeCompanyIds = $this->companyContext->getActiveCompanyIds();
        abort_unless(
            !empty($activeCompanyIds) && in_array($certificate->employee?->company_id, $activeCompanyIds),
            403
        );

        DB::transaction(function () use ($certificate) {
            $certificate->update(['active' => true]);
            $certificate->logSystemMessage('Certificate restored.');
        });

        return redirect()->route('employees.certificates.show', $certificate)->with('success', 'Certificate restored.');
    }

    public function bulkUnlink(Request $request): RedirectResponse
    {
        $this->authorize('delete', Employee::class);

        $activeCompanyIds = $this->companyContext->getActiveCompanyIds();
        $selectAll = $request->boolean('select_all');
        $ids = $request->input('ids', []);

        DB::transaction(function () use ($selectAll, $ids, $activeCompanyIds) {
            $query = EmployeeCertificate::whereHas('employee', function ($q) use ($activeCompanyIds) {
                empty($activeCompanyIds)
                    ? $q->whereRaw('1 = 0')
                    : $q->whereIn('company_id', $activeCompanyIds);
            });
            if (!$selectAll) {
                $query->whereIn('id', $ids);
            }
            foreach ($query->get() as $certificate) {
                $certificate->delete();
            }
        });

        return redirect()->route('employees.certificates.index')->with('success', 'Selected certificates deleted.');
    }

    public function unlink(EmployeeCertificate $certificate)
    {
        $this->authorize('delete', Employee::class);

        $activeCompanyIds = $this->companyContext->getActiveCompanyIds();
        abort_unless(
            !empty($activeCompanyIds) && in_array($certificate->employee?->company_id, $activeCompanyIds),
            403
        );

        DB::transaction(fn () => $certificate->delete());

        return redirect()->route('employees.certificates.index')->with('success', 'Certificate deleted.');
    }

    public function addComment(Request $request, EmployeeCertificate $certificate)
    {
        $this->authorize('update', Employee::class);

        $activeCompanyIds = $this->companyContext->getActiveCompanyIds();
        abort_unless(
            !empty($activeCompanyIds) && in_array($certificate->employee?->company_id, $activeCompanyIds),
            403
        );

        $request->validate(['body' => 'required|string|max:5000']);

        DB::transaction(fn () => $certificate->logComment($request->body));

        return back()->with('success', 'Comment added.');
    }

    private function diffChanges(EmployeeCertificate $certificate, array $data): array
    {
        $changes = [];
        foreach ($certificate->chatterTracked as $field => $label) {
            if (!array_key_exists($field, $data)) continue;
            $old = (string) ($certificate->{$field} ?? '');
            $new = (string) ($data[$field] ?? '');
            if ($old === $new) continue;
            $changes[] = "{$label}: {$old} → {$new}";
        }
        return $changes;
    }
}
