<?php

namespace App\Http\Controllers\Employees;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Employees\Concerns\ScopesEmployeeAllocation;
use App\Models\Employees\Employee;
use App\Models\Employees\EmployeeJobGrade;
use App\Services\Company\CompanyContextService;
use App\Helpers\GroupsQuery;
use App\Helpers\SearchFilters;
use App\Helpers\SortsTable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class EmployeeJobGradeController extends Controller
{
    use ScopesEmployeeAllocation;

    public function __construct(
        private readonly CompanyContextService $companyContext,
    ) {}

    public function read(Request $request)
    {
        $this->authorize('viewAny', Employee::class);

        $query = $this->scopeAllocationListing(EmployeeJobGrade::query());

        SearchFilters::apply($query, $request);

        if ($request->query('filter') === 'archived') {
            $query->where('active', false);
        } elseif ($request->query('filter') === 'all') {
        } else {
            $query->active();
        }

        $groupBy = $request->query('group_by');
        if ($groupBy) {
            $fields = SearchFilters::fieldsFor(EmployeeJobGrade::class);
            if (isset($fields[$groupBy])) {
                $records = (clone $query)->orderBy('organizational_structure')->get();
                $groups  = GroupsQuery::apply($records, $fields[$groupBy]);
                return view('employees.job-grades.index', compact('groups'));
            }
        }

        SortsTable::apply($query, $request);
        $records = $query->paginate(50)->withQueryString();

        return view('employees.job-grades.index', compact('records'));
    }

    public function show(EmployeeJobGrade $jobGrade)
    {
        $this->authorize('viewAny', Employee::class);
        $this->assertAllocationInScope($jobGrade);

        $this->loadAllocationWithScopedEmployees($jobGrade, [
            'creator', 'updater', 'chatterMessages.user',
        ]);

        return view('employees.job-grades.show', compact('jobGrade'));
    }

    public function create()
    {
        $this->authorize('create', Employee::class);

        return view('employees.job-grades.create');
    }

    public function store(Request $request)
    {
        $this->authorize('create', Employee::class);

        $data = $request->validate([
            'organizational_structure' => 'nullable|string|max:255',
            'assignment_type'          => 'nullable|string|max:255',
            'data_status'              => 'nullable|in:current,previous',
            'financial_specialization' => 'nullable|numeric|min:0',
            'affective_date'           => 'nullable|date',
        ]);

        $record = DB::transaction(function () use ($data) {
            $record = EmployeeJobGrade::create($data);
            $record->logSystemMessage('Record created.');
            return $record;
        });

        return redirect()->route('employees.job-grades.show', $record)->with('success', __('employees.job_grade_created'));
    }

    public function edit(EmployeeJobGrade $jobGrade)
    {
        $this->authorize('update', Employee::class);
        $this->assertAllocationInScope($jobGrade);

        return view('employees.job-grades.edit', compact('jobGrade'));
    }

    public function write(Request $request, EmployeeJobGrade $jobGrade)
    {
        $this->authorize('update', Employee::class);
        $this->assertAllocationInScope($jobGrade);

        $data = $request->validate([
            'organizational_structure' => 'nullable|string|max:255',
            'assignment_type'          => 'nullable|string|max:255',
            'data_status'              => 'nullable|in:current,previous',
            'financial_specialization' => 'nullable|numeric|min:0',
            'affective_date'           => 'nullable|date',
        ]);

        DB::transaction(function () use ($jobGrade, $data) {
            $changes = $this->diffChanges($jobGrade, $data);
            $jobGrade->update($data);
            if ($changes) {
                $jobGrade->logSystemMessage('Record updated: ' . implode(', ', $changes) . '.');
            }
        });

        return redirect()->route('employees.job-grades.show', $jobGrade)->with('success', __('employees.job_grade_updated'));
    }

    public function syncEmployees(Request $request, EmployeeJobGrade $jobGrade)
    {
        $this->authorize('update', Employee::class);
        $this->assertAllocationInScope($jobGrade);

        $data = $request->validate([
            'employee_ids'   => 'nullable|array',
            'employee_ids.*' => 'integer|exists:hr_employees,id',
        ]);

        // Silent-keep pattern (see ScopesEmployeeAllocation): scope requested
        // IDs to the actor's active companies and preserve any out-of-scope
        // pivot rows so a single-company actor can't strip cross-tenant rows.
        $newIds = $this->scopeRequestedEmployeeIds($data['employee_ids'] ?? [], $jobGrade->employees());

        DB::transaction(function () use ($jobGrade, $newIds) {
            $oldIds  = $jobGrade->employees()->pluck('hr_employees.id');
            $added   = Employee::whereIn('id', $newIds->diff($oldIds))->pluck('name');
            $removed = Employee::whereIn('id', $oldIds->diff($newIds))->pluck('name');

            $jobGrade->employees()->sync($newIds->all());

            if ($added->isNotEmpty()) {
                $jobGrade->logSystemMessage('Added: ' . $added->join(', ') . '.');
            }
            if ($removed->isNotEmpty()) {
                $jobGrade->logSystemMessage('Removed: ' . $removed->join(', ') . '.');
            }
        });

        return back()->with('success', __('employees.position_employees_saved'));
    }

    public function archive(EmployeeJobGrade $jobGrade)
    {
        $this->authorize('update', Employee::class);
        $this->assertAllocationInScope($jobGrade);

        DB::transaction(function () use ($jobGrade) {
            $jobGrade->update(['active' => false]);
            $jobGrade->logSystemMessage('Record archived.');
        });

        return redirect()->route('employees.job-grades.index')->with('success', __('employees.job_grade_archived'));
    }

    public function unarchive(EmployeeJobGrade $jobGrade)
    {
        $this->authorize('update', Employee::class);
        $this->assertAllocationInScope($jobGrade);

        DB::transaction(function () use ($jobGrade) {
            $jobGrade->update(['active' => true]);
            $jobGrade->logSystemMessage('Record restored.');
        });

        return redirect()->route('employees.job-grades.show', $jobGrade)->with('success', __('employees.job_grade_unarchived'));
    }

    public function unlink(EmployeeJobGrade $jobGrade)
    {
        $this->authorize('delete', Employee::class);
        $this->assertAllocationInScope($jobGrade);

        DB::transaction(fn () => $jobGrade->delete());

        return redirect()->route('employees.job-grades.index')->with('success', __('employees.job_grade_deleted'));
    }

    public function addComment(Request $request, EmployeeJobGrade $jobGrade)
    {
        $this->authorize('update', Employee::class);
        $this->assertAllocationInScope($jobGrade);

        $request->validate(['body' => 'required|string|max:5000']);
        DB::transaction(fn () => $jobGrade->logComment($request->body));

        return back()->with('success', 'Comment added.');
    }

    private function diffChanges(EmployeeJobGrade $record, array $data): array
    {
        $changes = [];
        foreach ($record->chatterTracked as $field => $label) {
            if (!array_key_exists($field, $data)) continue;
            $old = (string) ($record->{$field} ?? '');
            $new = (string) ($data[$field] ?? '');
            if ($old === $new) continue;
            $changes[] = "{$label}: {$old} → {$new}";
        }
        return $changes;
    }
}
