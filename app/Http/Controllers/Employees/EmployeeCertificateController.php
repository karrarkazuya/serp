<?php

namespace App\Http\Controllers\Employees;

use App\Http\Controllers\Controller;
use App\Models\Employees\Employee;
use App\Models\Employees\EmployeeCertificate;
use App\Services\Company\CompanyContextService;
use App\Helpers\SearchFilters;
use App\Helpers\SortsTable;
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

        if (!empty($activeCompanyIds)) {
            $query->whereHas('employee', fn ($q) => $q->whereIn('company_id', $activeCompanyIds));
        }

        SearchFilters::apply($query, $request);

        if ($request->query('filter') === 'archived') {
            $query->where('active', false);
        } elseif ($request->query('filter') === 'all') {
            // no filter
        } else {
            $query->active();
        }

        SortsTable::apply($query, $request);

        $certificates = $query->paginate(50)->withQueryString();

        return view('employees.certificates.index', compact('certificates'));
    }

    public function show(EmployeeCertificate $certificate)
    {
        $this->authorize('viewAny', Employee::class);

        $activeCompanyIds = $this->companyContext->getActiveCompanyIds();
        abort_unless(
            empty($activeCompanyIds) || in_array($certificate->employee?->company_id, $activeCompanyIds),
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
            'financial_specialization' => 'nullable|numeric|min:0',
        ]);

        $employee = Employee::findOrFail($data['employee_id']);
        abort_unless(empty($activeCompanyIds) || in_array($employee->company_id, $activeCompanyIds), 403);

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
            empty($activeCompanyIds) || in_array($certificate->employee?->company_id, $activeCompanyIds),
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
            empty($activeCompanyIds) || in_array($certificate->employee?->company_id, $activeCompanyIds),
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
            empty($activeCompanyIds) || in_array($certificate->employee?->company_id, $activeCompanyIds),
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
            empty($activeCompanyIds) || in_array($certificate->employee?->company_id, $activeCompanyIds),
            403
        );

        DB::transaction(function () use ($certificate) {
            $certificate->update(['active' => true]);
            $certificate->logSystemMessage('Certificate restored.');
        });

        return redirect()->route('employees.certificates.show', $certificate)->with('success', 'Certificate restored.');
    }

    public function unlink(EmployeeCertificate $certificate)
    {
        $this->authorize('delete', Employee::class);

        $activeCompanyIds = $this->companyContext->getActiveCompanyIds();
        abort_unless(
            empty($activeCompanyIds) || in_array($certificate->employee?->company_id, $activeCompanyIds),
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
            empty($activeCompanyIds) || in_array($certificate->employee?->company_id, $activeCompanyIds),
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
