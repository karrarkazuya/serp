<?php

namespace App\Http\Controllers\Employees;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Employees\Concerns\ScopesEmployeeAllocation;
use App\Models\Employees\Employee;
use App\Models\Employees\EmployeeBonus;
use App\Services\Company\CompanyContextService;
use App\Services\FileService;
use App\Helpers\GroupsQuery;
use App\Helpers\SearchFilters;
use App\Helpers\SortsTable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class EmployeeBonusController extends Controller
{
    use ScopesEmployeeAllocation;

    public function __construct(
        private readonly FileService $fileService,
        private readonly CompanyContextService $companyContext,
    ) {}

    public function read(Request $request)
    {
        $this->authorize('viewAny', Employee::class);

        $query = $this->scopeAllocationListing(EmployeeBonus::query());

        SearchFilters::apply($query, $request);

        if ($request->query('filter') === 'archived') {
            $query->where('active', false);
        } elseif ($request->query('filter') === 'all') {
        } else {
            $query->active();
        }

        $groupBy = $request->query('group_by');
        if ($groupBy) {
            $fields = SearchFilters::fieldsFor(EmployeeBonus::class);
            if (isset($fields[$groupBy])) {
                $records = (clone $query)->orderBy('name')->get();
                $groups  = GroupsQuery::apply($records, $fields[$groupBy]);
                return view('employees.bonuses.index', compact('groups'));
            }
        }

        SortsTable::apply($query, $request);
        $records = $query->paginate(50)->withQueryString();

        return view('employees.bonuses.index', compact('records'));
    }

    public function show(EmployeeBonus $bonus)
    {
        $this->authorize('viewAny', Employee::class);
        $this->assertAllocationInScope($bonus);

        $this->loadAllocationWithScopedEmployees($bonus, [
            'creator', 'updater', 'chatterMessages.user', 'attachedFile',
        ]);

        return view('employees.bonuses.show', compact('bonus'));
    }

    public function create()
    {
        $this->authorize('create', Employee::class);

        return view('employees.bonuses.create');
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
            'file'                     => 'nullable|file|max:10240|mimetypes:image/jpeg,image/png,image/gif,image/webp,application/pdf,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document,application/vnd.ms-excel,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,application/vnd.ms-powerpoint,application/vnd.openxmlformats-officedocument.presentationml.presentation,application/vnd.oasis.opendocument.text,application/vnd.oasis.opendocument.spreadsheet,text/plain,text/csv|mimes:jpg,jpeg,png,gif,webp,pdf,doc,docx,xls,xlsx,ppt,pptx,odt,ods,txt,csv',
        ]);

        $fileRecord = null;
        if ($request->hasFile('file')) {
            $fileRecord        = $this->fileService->store($request->file('file'), 'documents/bonuses', 'employees.read');
            $data['file_path'] = $fileRecord->uuid;
        }
        unset($data['file']);

        $record = DB::transaction(function () use ($data, $fileRecord) {
            $record = EmployeeBonus::create($data);
            $fileRecord?->update(['source_type' => $record->getTable(), 'source_id' => $record->id]);
            $record->logSystemMessage('Record created.');
            return $record;
        });

        return redirect()->route('employees.bonuses.show', $record)->with('success', __('employees.bonus_created'));
    }

    public function edit(EmployeeBonus $bonus)
    {
        $this->authorize('update', Employee::class);
        $this->assertAllocationInScope($bonus);

        $bonus->load('attachedFile');

        return view('employees.bonuses.edit', compact('bonus'));
    }

    public function write(Request $request, EmployeeBonus $bonus)
    {
        $this->authorize('update', Employee::class);
        $this->assertAllocationInScope($bonus);

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
            'file'                     => 'nullable|file|max:10240|mimetypes:image/jpeg,image/png,image/gif,image/webp,application/pdf,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document,application/vnd.ms-excel,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,application/vnd.ms-powerpoint,application/vnd.openxmlformats-officedocument.presentationml.presentation,application/vnd.oasis.opendocument.text,application/vnd.oasis.opendocument.spreadsheet,text/plain,text/csv|mimes:jpg,jpeg,png,gif,webp,pdf,doc,docx,xls,xlsx,ppt,pptx,odt,ods,txt,csv',
        ]);

        if ($request->hasFile('file')) {
            if ($bonus->file_path) {
                $this->fileService->deleteByUuid($bonus->file_path);
            }
            $fileRecord        = $this->fileService->store($request->file('file'), 'documents/bonuses', 'employees.read', null, $bonus);
            $data['file_path'] = $fileRecord->uuid;
        }
        unset($data['file']);

        DB::transaction(function () use ($bonus, $data) {
            $changes = $this->diffChanges($bonus, $data);
            $bonus->update($data);
            if ($changes) {
                $bonus->logSystemMessage('Record updated: ' . implode(', ', $changes) . '.');
            }
        });

        return redirect()->route('employees.bonuses.show', $bonus)->with('success', __('employees.bonus_updated'));
    }

    public function syncEmployees(Request $request, EmployeeBonus $bonus)
    {
        $this->authorize('update', Employee::class);
        $this->assertAllocationInScope($bonus);

        $data = $request->validate([
            'employee_ids'   => 'nullable|array',
            'employee_ids.*' => 'integer|exists:hr_employees,id',
        ]);

        // Silent-keep pattern (see ScopesEmployeeAllocation): scope requested
        // IDs to the actor's active companies and preserve any out-of-scope
        // pivot rows so a single-company actor can't strip cross-tenant rows.
        $newIds = $this->scopeRequestedEmployeeIds($data['employee_ids'] ?? [], $bonus->employees());

        DB::transaction(function () use ($bonus, $newIds) {
            $oldIds  = $bonus->employees()->pluck('hr_employees.id');
            $added   = Employee::whereIn('id', $newIds->diff($oldIds))->pluck('name');
            $removed = Employee::whereIn('id', $oldIds->diff($newIds))->pluck('name');

            $bonus->employees()->sync($newIds->all());

            if ($added->isNotEmpty()) {
                $bonus->logSystemMessage('Added: ' . $added->join(', ') . '.');
            }
            if ($removed->isNotEmpty()) {
                $bonus->logSystemMessage('Removed: ' . $removed->join(', ') . '.');
            }
        });

        return back()->with('success', __('employees.position_employees_saved'));
    }

    public function replaceDocument(Request $request, EmployeeBonus $bonus)
    {
        $this->authorize('update', Employee::class);
        $this->assertAllocationInScope($bonus);

        $request->validate(['file' => 'required|file|max:10240']);

        DB::transaction(function () use ($request, $bonus) {
            if ($bonus->file_path) {
                $this->fileService->deleteByUuid($bonus->file_path);
            }
            $fileRecord = $this->fileService->store($request->file('file'), 'documents/bonuses', 'employees.read', null, $bonus);
            $bonus->update(['file_path' => $fileRecord->uuid]);
            $bonus->logSystemMessage('Document replaced.');
        });

        return back()->with('success', __('employees.document_replaced'));
    }

    public function deleteDocument(EmployeeBonus $bonus)
    {
        $this->authorize('update', Employee::class);
        $this->assertAllocationInScope($bonus);

        DB::transaction(function () use ($bonus) {
            if ($bonus->file_path) {
                $this->fileService->deleteByUuid($bonus->file_path);
            }
            $bonus->update(['file_path' => null]);
            $bonus->logSystemMessage('Document removed.');
        });

        return back()->with('success', __('employees.document_deleted'));
    }

    public function archive(EmployeeBonus $bonus)
    {
        $this->authorize('update', Employee::class);
        $this->assertAllocationInScope($bonus);

        DB::transaction(function () use ($bonus) {
            $bonus->update(['active' => false]);
            $bonus->logSystemMessage('Record archived.');
        });

        return redirect()->route('employees.bonuses.index')->with('success', __('employees.bonus_archived'));
    }

    public function unarchive(EmployeeBonus $bonus)
    {
        $this->authorize('update', Employee::class);
        $this->assertAllocationInScope($bonus);

        DB::transaction(function () use ($bonus) {
            $bonus->update(['active' => true]);
            $bonus->logSystemMessage('Record restored.');
        });

        return redirect()->route('employees.bonuses.show', $bonus)->with('success', __('employees.bonus_unarchived'));
    }

    public function unlink(EmployeeBonus $bonus)
    {
        $this->authorize('delete', Employee::class);
        $this->assertAllocationInScope($bonus);

        DB::transaction(function () use ($bonus) {
            if ($bonus->file_path) {
                $this->fileService->deleteByUuid($bonus->file_path);
            }
            $bonus->delete();
        });

        return redirect()->route('employees.bonuses.index')->with('success', __('employees.bonus_deleted'));
    }

    public function addComment(Request $request, EmployeeBonus $bonus)
    {
        $this->authorize('update', Employee::class);
        $this->assertAllocationInScope($bonus);

        $request->validate(['body' => 'required|string|max:5000']);
        DB::transaction(fn () => $bonus->logComment($request->body));

        return back()->with('success', 'Comment added.');
    }

    private function diffChanges(EmployeeBonus $record, array $data): array
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
