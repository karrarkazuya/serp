<?php

namespace App\Http\Controllers\Employees;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Employees\Concerns\ScopesEmployeeAllocation;
use App\Models\Employees\Employee;
use App\Models\Employees\EmployeeSanction;
use App\Services\Company\CompanyContextService;
use App\Services\FileService;
use App\Helpers\GroupsQuery;
use App\Helpers\SearchFilters;
use App\Helpers\SortsTable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class EmployeeSanctionController extends Controller
{
    use ScopesEmployeeAllocation;

    public function __construct(
        private readonly FileService $fileService,
        private readonly CompanyContextService $companyContext,
    ) {}

    public function read(Request $request)
    {
        $this->authorize('viewAny', Employee::class);

        $query = $this->scopeAllocationListing(EmployeeSanction::query());

        SearchFilters::apply($query, $request);

        if ($request->query('filter') === 'archived') {
            $query->where('active', false);
        } elseif ($request->query('filter') === 'all') {
        } else {
            $query->active();
        }

        $groupBy = $request->query('group_by');
        if ($groupBy) {
            $fields = SearchFilters::fieldsFor(EmployeeSanction::class);
            if (isset($fields[$groupBy])) {
                $records = (clone $query)->orderBy('name')->get();
                $groups  = GroupsQuery::apply($records, $fields[$groupBy]);
                return view('employees.sanctions.index', compact('groups'));
            }
        }

        SortsTable::apply($query, $request);
        $records = $query->paginate(50)->withQueryString();

        return view('employees.sanctions.index', compact('records'));
    }

    public function show(EmployeeSanction $sanction)
    {
        $this->authorize('viewAny', Employee::class);
        $this->assertAllocationInScope($sanction);

        $this->loadAllocationWithScopedEmployees($sanction, [
            'creator', 'updater', 'chatterMessages.user', 'attachedFile',
        ]);

        return view('employees.sanctions.show', compact('sanction'));
    }

    public function create()
    {
        $this->authorize('create', Employee::class);

        return view('employees.sanctions.create');
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
            'financial_specialization' => 'nullable|numeric|min:0',
            'affective_date'           => 'nullable|date',
            'issue_date'               => 'nullable|date',
            'expiry_date'              => 'nullable|date',
            'notify_before_days'       => 'nullable|integer|min:0|max:365',
            'notes'                    => 'nullable|string',
            'file'                     => 'nullable|file|max:10240|mimetypes:image/jpeg,image/png,image/gif,image/webp,application/pdf,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document,application/vnd.ms-excel,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,application/vnd.ms-powerpoint,application/vnd.openxmlformats-officedocument.presentationml.presentation,application/vnd.oasis.opendocument.text,application/vnd.oasis.opendocument.spreadsheet,text/plain,text/csv|mimes:jpg,jpeg,png,gif,webp,pdf,doc,docx,xls,xlsx,ppt,pptx,odt,ods,txt,csv',
        ]);

        $fileRecord = null;
        if ($request->hasFile('file')) {
            $fileRecord        = $this->fileService->store($request->file('file'), 'documents/sanctions', 'employees.read');
            $data['file_path'] = $fileRecord->uuid;
        }
        unset($data['file']);

        $record = DB::transaction(function () use ($data, $fileRecord) {
            $record = EmployeeSanction::create($data);
            $fileRecord?->update(['source_type' => $record->getTable(), 'source_id' => $record->id]);
            $record->logSystemMessage('Record created.');
            return $record;
        });

        return redirect()->route('employees.sanctions.show', $record)->with('success', __('employees.sanction_created'));
    }

    public function edit(EmployeeSanction $sanction)
    {
        $this->authorize('update', Employee::class);
        $this->assertAllocationInScope($sanction);

        $sanction->load('attachedFile');

        return view('employees.sanctions.edit', compact('sanction'));
    }

    public function write(Request $request, EmployeeSanction $sanction)
    {
        $this->authorize('update', Employee::class);
        $this->assertAllocationInScope($sanction);

        $data = $request->validate([
            'name'                     => 'nullable|string|max:255',
            'document_type'            => 'nullable|in:contract,id_card,passport,certificate,resume,medical,other',
            'issued_by'                => 'nullable|string|max:255',
            'document_number'          => 'nullable|string|max:255',
            'organizational_structure' => 'nullable|string|max:255',
            'assignment_type'          => 'nullable|string|max:255',
            'data_status'              => 'nullable|in:current,previous',
            'financial_specialization' => 'nullable|numeric|min:0',
            'affective_date'           => 'nullable|date',
            'issue_date'               => 'nullable|date',
            'expiry_date'              => 'nullable|date',
            'notify_before_days'       => 'nullable|integer|min:0|max:365',
            'notes'                    => 'nullable|string',
            'file'                     => 'nullable|file|max:10240|mimetypes:image/jpeg,image/png,image/gif,image/webp,application/pdf,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document,application/vnd.ms-excel,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,application/vnd.ms-powerpoint,application/vnd.openxmlformats-officedocument.presentationml.presentation,application/vnd.oasis.opendocument.text,application/vnd.oasis.opendocument.spreadsheet,text/plain,text/csv|mimes:jpg,jpeg,png,gif,webp,pdf,doc,docx,xls,xlsx,ppt,pptx,odt,ods,txt,csv',
        ]);

        if ($request->hasFile('file')) {
            if ($sanction->file_path) {
                $this->fileService->deleteByUuid($sanction->file_path);
            }
            $fileRecord        = $this->fileService->store($request->file('file'), 'documents/sanctions', 'employees.read', null, $sanction);
            $data['file_path'] = $fileRecord->uuid;
        }
        unset($data['file']);

        DB::transaction(function () use ($sanction, $data) {
            $changes = $this->diffChanges($sanction, $data);
            $sanction->update($data);
            if ($changes) {
                $sanction->logSystemMessage('Record updated: ' . implode(', ', $changes) . '.');
            }
        });

        return redirect()->route('employees.sanctions.show', $sanction)->with('success', __('employees.sanction_updated'));
    }

    public function syncEmployees(Request $request, EmployeeSanction $sanction)
    {
        $this->authorize('update', Employee::class);
        $this->assertAllocationInScope($sanction);

        $data = $request->validate([
            'employee_ids'   => 'nullable|array',
            'employee_ids.*' => 'integer|exists:hr_employees,id',
        ]);

        // Silent-keep pattern (see ScopesEmployeeAllocation). Sanctions are
        // disciplinary records — cross-tenant tampering here would create false
        // audit history, so preserve out-of-scope pivot rows untouched.
        $newIds = $this->scopeRequestedEmployeeIds($data['employee_ids'] ?? [], $sanction->employees());

        DB::transaction(function () use ($sanction, $newIds) {
            $oldIds  = $sanction->employees()->pluck('hr_employees.id');
            $added   = Employee::whereIn('id', $newIds->diff($oldIds))->pluck('name');
            $removed = Employee::whereIn('id', $oldIds->diff($newIds))->pluck('name');

            $sanction->employees()->sync($newIds->all());

            if ($added->isNotEmpty()) {
                $sanction->logSystemMessage('Added: ' . $added->join(', ') . '.');
            }
            if ($removed->isNotEmpty()) {
                $sanction->logSystemMessage('Removed: ' . $removed->join(', ') . '.');
            }
        });

        return back()->with('success', __('employees.position_employees_saved'));
    }

    public function replaceDocument(Request $request, EmployeeSanction $sanction)
    {
        $this->authorize('update', Employee::class);
        $this->assertAllocationInScope($sanction);

        $request->validate(['file' => 'required|file|max:10240']);

        DB::transaction(function () use ($request, $sanction) {
            if ($sanction->file_path) {
                $this->fileService->deleteByUuid($sanction->file_path);
            }
            $fileRecord = $this->fileService->store($request->file('file'), 'documents/sanctions', 'employees.read', null, $sanction);
            $sanction->update(['file_path' => $fileRecord->uuid]);
            $sanction->logSystemMessage('Document replaced.');
        });

        return back()->with('success', __('employees.document_replaced'));
    }

    public function deleteDocument(EmployeeSanction $sanction)
    {
        $this->authorize('update', Employee::class);
        $this->assertAllocationInScope($sanction);

        DB::transaction(function () use ($sanction) {
            if ($sanction->file_path) {
                $this->fileService->deleteByUuid($sanction->file_path);
            }
            $sanction->update(['file_path' => null]);
            $sanction->logSystemMessage('Document removed.');
        });

        return back()->with('success', __('employees.document_deleted'));
    }

    public function archive(EmployeeSanction $sanction)
    {
        $this->authorize('update', Employee::class);
        $this->assertAllocationInScope($sanction);

        DB::transaction(function () use ($sanction) {
            $sanction->update(['active' => false]);
            $sanction->logSystemMessage('Record archived.');
        });

        return redirect()->route('employees.sanctions.index')->with('success', __('employees.sanction_archived'));
    }

    public function unarchive(EmployeeSanction $sanction)
    {
        $this->authorize('update', Employee::class);
        $this->assertAllocationInScope($sanction);

        DB::transaction(function () use ($sanction) {
            $sanction->update(['active' => true]);
            $sanction->logSystemMessage('Record restored.');
        });

        return redirect()->route('employees.sanctions.show', $sanction)->with('success', __('employees.sanction_unarchived'));
    }

    public function bulkUnlink(Request $request): RedirectResponse
    {
        $this->authorize('delete', Employee::class);

        $activeCompanyIds = $this->companyContext->getActiveCompanyIds();
        $selectAll = $request->boolean('select_all');
        $ids = $request->input('ids', []);

        DB::transaction(function () use ($selectAll, $ids, $activeCompanyIds) {
            $query = EmployeeSanction::whereHas('employees', function ($q) use ($activeCompanyIds) {
                empty($activeCompanyIds)
                    ? $q->whereRaw('1 = 0')
                    : $q->whereIn('hr_employees.company_id', $activeCompanyIds);
            });
            if (!$selectAll) {
                $query->whereIn('id', $ids);
            }
            foreach ($query->get() as $sanction) {
                if ($sanction->file_path) {
                    $this->fileService->deleteByUuid($sanction->file_path);
                }
                $sanction->delete();
            }
        });

        return redirect()->route('employees.sanctions.index')->with('success', __('employees.sanctions_deleted'));
    }

    public function unlink(EmployeeSanction $sanction)
    {
        $this->authorize('delete', Employee::class);
        $this->assertAllocationInScope($sanction);

        DB::transaction(function () use ($sanction) {
            if ($sanction->file_path) {
                $this->fileService->deleteByUuid($sanction->file_path);
            }
            $sanction->delete();
        });

        return redirect()->route('employees.sanctions.index')->with('success', __('employees.sanction_deleted'));
    }

    public function addComment(Request $request, EmployeeSanction $sanction)
    {
        $this->authorize('update', Employee::class);
        $this->assertAllocationInScope($sanction);

        $request->validate(['body' => 'required|string|max:5000']);
        DB::transaction(fn () => $sanction->logComment($request->body));

        return back()->with('success', __('employees.comment_added'));
    }

    private function diffChanges(EmployeeSanction $record, array $data): array
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
