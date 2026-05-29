<?php

namespace App\Http\Controllers\Employees;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Employees\Concerns\ScopesEmployeeAllocation;
use App\Models\Employees\Employee;
use App\Models\Employees\EmployeePosition;
use App\Services\Company\CompanyContextService;
use App\Helpers\GroupsQuery;
use App\Helpers\SearchFilters;
use App\Helpers\SortsTable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class EmployeePositionController extends Controller
{
    use ScopesEmployeeAllocation;

    public function __construct(
        private readonly CompanyContextService $companyContext,
    ) {}

    public function read(Request $request)
    {
        $this->authorize('viewAny', Employee::class);

        $query = $this->scopeAllocationListing(EmployeePosition::query());

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
            $fields = SearchFilters::fieldsFor(EmployeePosition::class);
            if (isset($fields[$groupBy])) {
                $records = (clone $query)->orderBy('organizational_structure')->get();
                $groups  = GroupsQuery::apply($records, $fields[$groupBy]);
                return view('employees.positions.index', compact('groups'));
            }
        }

        SortsTable::apply($query, $request);

        $positions = $query->paginate(50)->withQueryString();

        return view('employees.positions.index', compact('positions'));
    }

    public function show(EmployeePosition $position)
    {
        $this->authorize('viewAny', Employee::class);
        $this->assertAllocationInScope($position);

        $activeCompanyIds = $this->companyContext->getActiveCompanyIds();
        // Scope the `employees` eager-load by active companies so out-of-tenant
        // employees aren't disclosed in the view. `employees.company` keeps the
        // nested company relation for the few in-scope employees shown.
        $position->load([
            'employees' => function ($q) use ($activeCompanyIds) {
                empty($activeCompanyIds)
                    ? $q->whereRaw('1 = 0')
                    : $q->whereIn('hr_employees.company_id', $activeCompanyIds);
            },
            'employees.company',
            'creator', 'updater', 'chatterMessages.user',
        ]);

        return view('employees.positions.show', compact('position'));
    }

    public function create()
    {
        $this->authorize('create', Employee::class);

        return view('employees.positions.create');
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

        $position = DB::transaction(function () use ($data) {
            $position = EmployeePosition::create($data);
            $position->logSystemMessage('Position created.');
            return $position;
        });

        return redirect()->route('employees.positions.show', $position)->with('success', __('employees.position_created'));
    }

    public function edit(EmployeePosition $position)
    {
        $this->authorize('update', Employee::class);
        $this->assertAllocationInScope($position);

        return view('employees.positions.edit', compact('position'));
    }

    public function write(Request $request, EmployeePosition $position)
    {
        $this->authorize('update', Employee::class);
        $this->assertAllocationInScope($position);

        $data = $request->validate([
            'organizational_structure' => 'nullable|string|max:255',
            'assignment_type'          => 'nullable|string|max:255',
            'data_status'              => 'nullable|in:current,previous',
            'financial_specialization' => 'nullable|numeric|min:0',
            'affective_date'           => 'nullable|date',
        ]);

        DB::transaction(function () use ($position, $data) {
            $changes = $this->diffChanges($position, $data);
            $position->update($data);
            if ($changes) {
                $position->logSystemMessage('Position updated: ' . implode(', ', $changes) . '.');
            }
        });

        return redirect()->route('employees.positions.show', $position)->with('success', __('employees.position_updated'));
    }

    public function archive(EmployeePosition $position)
    {
        $this->authorize('update', Employee::class);
        $this->assertAllocationInScope($position);

        DB::transaction(function () use ($position) {
            $position->update(['active' => false]);
            $position->logSystemMessage('Position archived.');
        });

        return redirect()->route('employees.positions.index')->with('success', __('employees.position_archived'));
    }

    public function unarchive(EmployeePosition $position)
    {
        $this->authorize('update', Employee::class);
        $this->assertAllocationInScope($position);

        DB::transaction(function () use ($position) {
            $position->update(['active' => true]);
            $position->logSystemMessage('Position restored.');
        });

        return redirect()->route('employees.positions.show', $position)->with('success', __('employees.position_unarchived'));
    }

    public function unlink(EmployeePosition $position)
    {
        $this->authorize('delete', Employee::class);
        $this->assertAllocationInScope($position);

        DB::transaction(fn () => $position->delete());

        return redirect()->route('employees.positions.index')->with('success', __('employees.position_deleted'));
    }

    public function syncEmployees(Request $request, EmployeePosition $position)
    {
        $this->authorize('update', Employee::class);
        $this->assertAllocationInScope($position);

        $data = $request->validate([
            'employee_ids'   => 'nullable|array',
            'employee_ids.*' => 'integer|exists:hr_employees,id',
        ]);

        // Silent-keep pattern (see ScopesEmployeeAllocation). Previously this
        // method used `abort_unless($validCount === $requestedCount)`, which
        // didn't protect cross-tenant pivot rows attached by a wider-access
        // user from being silently stripped by a single-company submission.
        $newIds = $this->scopeRequestedEmployeeIds($data['employee_ids'] ?? [], $position->employees());

        DB::transaction(function () use ($position, $newIds) {
            $oldIds   = $position->employees()->pluck('hr_employees.id');
            $added    = Employee::whereIn('id', $newIds->diff($oldIds))->pluck('name');
            $removed  = Employee::whereIn('id', $oldIds->diff($newIds))->pluck('name');

            $position->employees()->sync($newIds->all());

            if ($added->isNotEmpty()) {
                $position->logSystemMessage('Added: ' . $added->join(', ') . '.');
            }
            if ($removed->isNotEmpty()) {
                $position->logSystemMessage('Removed: ' . $removed->join(', ') . '.');
            }
        });

        return back()->with('success', __('employees.position_employees_saved'));
    }

    public function addComment(Request $request, EmployeePosition $position)
    {
        $this->authorize('update', Employee::class);
        $this->assertAllocationInScope($position);

        $request->validate(['body' => 'required|string|max:5000']);

        DB::transaction(fn () => $position->logComment($request->body));

        return back()->with('success', __('employees.comment_added'));
    }

    private function diffChanges(EmployeePosition $position, array $data): array
    {
        $changes = [];
        foreach ($position->chatterTracked as $field => $label) {
            if (!array_key_exists($field, $data)) continue;
            $old = (string) ($position->{$field} ?? '');
            $new = (string) ($data[$field] ?? '');
            if ($old === $new) continue;
            $changes[] = "{$label}: {$old} → {$new}";
        }
        return $changes;
    }
}
