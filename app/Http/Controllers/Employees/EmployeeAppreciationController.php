<?php

namespace App\Http\Controllers\Employees;

use App\Http\Controllers\Controller;
use App\Models\Employees\Employee;
use App\Models\Employees\EmployeeAppreciation;
use App\Services\FileService;
use App\Helpers\GroupsQuery;
use App\Helpers\SearchFilters;
use App\Helpers\SortsTable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class EmployeeAppreciationController extends Controller
{
    public function __construct(
        private readonly FileService $fileService,
    ) {}

    public function read(Request $request)
    {
        $this->authorize('viewAny', Employee::class);

        $query = EmployeeAppreciation::query()->withCount('employees');

        SearchFilters::apply($query, $request);

        if ($request->query('filter') === 'archived') {
            $query->where('active', false);
        } elseif ($request->query('filter') === 'all') {
        } else {
            $query->active();
        }

        $groupBy = $request->query('group_by');
        if ($groupBy) {
            $fields = SearchFilters::fieldsFor(EmployeeAppreciation::class);
            if (isset($fields[$groupBy])) {
                $records = (clone $query)->orderBy('name')->get();
                $groups  = GroupsQuery::apply($records, $fields[$groupBy]);
                return view('employees.appreciations.index', compact('groups'));
            }
        }

        SortsTable::apply($query, $request);
        $records = $query->paginate(50)->withQueryString();

        return view('employees.appreciations.index', compact('records'));
    }

    public function show(EmployeeAppreciation $appreciation)
    {
        $this->authorize('viewAny', Employee::class);

        $appreciation->load(['employees', 'creator', 'updater', 'chatterMessages.user', 'attachedFile']);

        return view('employees.appreciations.show', compact('appreciation'));
    }

    public function create()
    {
        $this->authorize('create', Employee::class);

        return view('employees.appreciations.create');
    }

    public function store(Request $request)
    {
        $this->authorize('create', Employee::class);

        $data = $request->validate([
            'name'                     => 'nullable|string|max:255',
            'document_type'            => 'nullable|in:contract,id_card,passport,certificate,resume,medical,other',
            'issued_by'                => 'nullable|string|max:255',
            'document_number'          => 'nullable|string|max:255',
            'organizational_structure' => 'nullable|string|max:255',
            'assignment_type'          => 'nullable|string|max:255',
            'data_status'              => 'nullable|in:current,previous',
            'specialization_type'      => 'nullable|in:amount,percentage,seniority',
            'financial_specialization' => 'nullable|numeric|min:0',
            'employee_seniority'       => 'nullable|integer|min:0',
            'affective_date'           => 'nullable|date',
            'issue_date'               => 'nullable|date',
            'expiry_date'              => 'nullable|date',
            'notify_before_days'       => 'nullable|integer|min:0|max:365',
            'notes'                    => 'nullable|string',
            'file'                     => 'nullable|file|max:10240',
        ]);

        $fileRecord = null;
        if ($request->hasFile('file')) {
            $fileRecord        = $this->fileService->store($request->file('file'), 'documents/appreciations', 'employees.read');
            $data['file_path'] = $fileRecord->uuid;
        }
        unset($data['file']);

        $record = DB::transaction(function () use ($data, $fileRecord) {
            $record = EmployeeAppreciation::create($data);
            $fileRecord?->update(['source_type' => $record->getTable(), 'source_id' => $record->id]);
            $record->logSystemMessage('Record created.');
            return $record;
        });

        return redirect()->route('employees.appreciations.show', $record)->with('success', __('employees.appreciation_created'));
    }

    public function edit(EmployeeAppreciation $appreciation)
    {
        $this->authorize('update', Employee::class);

        $appreciation->load('attachedFile');

        return view('employees.appreciations.edit', compact('appreciation'));
    }

    public function write(Request $request, EmployeeAppreciation $appreciation)
    {
        $this->authorize('update', Employee::class);

        $data = $request->validate([
            'name'                     => 'nullable|string|max:255',
            'document_type'            => 'nullable|in:contract,id_card,passport,certificate,resume,medical,other',
            'issued_by'                => 'nullable|string|max:255',
            'document_number'          => 'nullable|string|max:255',
            'organizational_structure' => 'nullable|string|max:255',
            'assignment_type'          => 'nullable|string|max:255',
            'data_status'              => 'nullable|in:current,previous',
            'specialization_type'      => 'nullable|in:amount,percentage,seniority',
            'financial_specialization' => 'nullable|numeric|min:0',
            'employee_seniority'       => 'nullable|integer|min:0',
            'affective_date'           => 'nullable|date',
            'issue_date'               => 'nullable|date',
            'expiry_date'              => 'nullable|date',
            'notify_before_days'       => 'nullable|integer|min:0|max:365',
            'notes'                    => 'nullable|string',
            'file'                     => 'nullable|file|max:10240',
        ]);

        if ($request->hasFile('file')) {
            if ($appreciation->file_path) {
                $this->fileService->deleteByUuid($appreciation->file_path);
            }
            $fileRecord        = $this->fileService->store($request->file('file'), 'documents/appreciations', 'employees.read', null, $appreciation);
            $data['file_path'] = $fileRecord->uuid;
        }
        unset($data['file']);

        DB::transaction(function () use ($appreciation, $data) {
            $changes = $this->diffChanges($appreciation, $data);
            $appreciation->update($data);
            if ($changes) {
                $appreciation->logSystemMessage('Record updated: ' . implode(', ', $changes) . '.');
            }
        });

        return redirect()->route('employees.appreciations.show', $appreciation)->with('success', __('employees.appreciation_updated'));
    }

    public function syncEmployees(Request $request, EmployeeAppreciation $appreciation)
    {
        $this->authorize('update', Employee::class);

        $data = $request->validate([
            'employee_ids'   => 'nullable|array',
            'employee_ids.*' => 'exists:hr_employees,id',
        ]);

        $newIds = collect($data['employee_ids'] ?? [])->map(fn ($id) => (int) $id);

        DB::transaction(function () use ($appreciation, $newIds) {
            $oldIds  = $appreciation->employees()->pluck('hr_employees.id');
            $added   = Employee::whereIn('id', $newIds->diff($oldIds))->pluck('name');
            $removed = Employee::whereIn('id', $oldIds->diff($newIds))->pluck('name');

            $appreciation->employees()->sync($newIds->all());

            if ($added->isNotEmpty()) {
                $appreciation->logSystemMessage('Added: ' . $added->join(', ') . '.');
            }
            if ($removed->isNotEmpty()) {
                $appreciation->logSystemMessage('Removed: ' . $removed->join(', ') . '.');
            }
        });

        return back()->with('success', __('employees.position_employees_saved'));
    }

    public function replaceDocument(Request $request, EmployeeAppreciation $appreciation)
    {
        $this->authorize('update', Employee::class);

        $request->validate(['file' => 'required|file|max:10240']);

        DB::transaction(function () use ($request, $appreciation) {
            if ($appreciation->file_path) {
                $this->fileService->deleteByUuid($appreciation->file_path);
            }
            $fileRecord = $this->fileService->store($request->file('file'), 'documents/appreciations', 'employees.read', null, $appreciation);
            $appreciation->update(['file_path' => $fileRecord->uuid]);
            $appreciation->logSystemMessage('Document replaced.');
        });

        return back()->with('success', __('employees.document_replaced'));
    }

    public function deleteDocument(EmployeeAppreciation $appreciation)
    {
        $this->authorize('update', Employee::class);

        DB::transaction(function () use ($appreciation) {
            if ($appreciation->file_path) {
                $this->fileService->deleteByUuid($appreciation->file_path);
            }
            $appreciation->update(['file_path' => null]);
            $appreciation->logSystemMessage('Document removed.');
        });

        return back()->with('success', __('employees.document_deleted'));
    }

    public function archive(EmployeeAppreciation $appreciation)
    {
        $this->authorize('update', Employee::class);

        DB::transaction(function () use ($appreciation) {
            $appreciation->update(['active' => false]);
            $appreciation->logSystemMessage('Record archived.');
        });

        return redirect()->route('employees.appreciations.index')->with('success', __('employees.appreciation_archived'));
    }

    public function unarchive(EmployeeAppreciation $appreciation)
    {
        $this->authorize('update', Employee::class);

        DB::transaction(function () use ($appreciation) {
            $appreciation->update(['active' => true]);
            $appreciation->logSystemMessage('Record restored.');
        });

        return redirect()->route('employees.appreciations.show', $appreciation)->with('success', __('employees.appreciation_unarchived'));
    }

    public function unlink(EmployeeAppreciation $appreciation)
    {
        $this->authorize('delete', Employee::class);

        DB::transaction(function () use ($appreciation) {
            if ($appreciation->file_path) {
                $this->fileService->deleteByUuid($appreciation->file_path);
            }
            $appreciation->delete();
        });

        return redirect()->route('employees.appreciations.index')->with('success', __('employees.appreciation_deleted'));
    }

    public function addComment(Request $request, EmployeeAppreciation $appreciation)
    {
        $this->authorize('update', Employee::class);

        $request->validate(['body' => 'required|string|max:5000']);
        DB::transaction(fn () => $appreciation->logComment($request->body));

        return back()->with('success', 'Comment added.');
    }

    private function diffChanges(EmployeeAppreciation $record, array $data): array
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
