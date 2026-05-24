<?php

namespace App\Http\Controllers\Employees;

use App\Http\Controllers\Controller;
use App\Models\Employees\Employee;
use App\Models\Employees\EmployeeJobGrade;
use App\Helpers\GroupsQuery;
use App\Helpers\SearchFilters;
use App\Helpers\SortsTable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class EmployeeJobGradeController extends Controller
{
    public function read(Request $request)
    {
        $this->authorize('viewAny', Employee::class);

        $query = EmployeeJobGrade::query()->withCount('employees');

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

        $jobGrade->load(['employees', 'creator', 'updater', 'chatterMessages.user']);

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

        return view('employees.job-grades.edit', compact('jobGrade'));
    }

    public function write(Request $request, EmployeeJobGrade $jobGrade)
    {
        $this->authorize('update', Employee::class);

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

        $data = $request->validate([
            'employee_ids'   => 'nullable|array',
            'employee_ids.*' => 'exists:hr_employees,id',
        ]);

        $newIds = collect($data['employee_ids'] ?? [])->map(fn ($id) => (int) $id);

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

        DB::transaction(function () use ($jobGrade) {
            $jobGrade->update(['active' => false]);
            $jobGrade->logSystemMessage('Record archived.');
        });

        return redirect()->route('employees.job-grades.index')->with('success', __('employees.job_grade_archived'));
    }

    public function unarchive(EmployeeJobGrade $jobGrade)
    {
        $this->authorize('update', Employee::class);

        DB::transaction(function () use ($jobGrade) {
            $jobGrade->update(['active' => true]);
            $jobGrade->logSystemMessage('Record restored.');
        });

        return redirect()->route('employees.job-grades.show', $jobGrade)->with('success', __('employees.job_grade_unarchived'));
    }

    public function unlink(EmployeeJobGrade $jobGrade)
    {
        $this->authorize('delete', Employee::class);

        DB::transaction(fn () => $jobGrade->delete());

        return redirect()->route('employees.job-grades.index')->with('success', __('employees.job_grade_deleted'));
    }

    public function addComment(Request $request, EmployeeJobGrade $jobGrade)
    {
        $this->authorize('update', Employee::class);

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
