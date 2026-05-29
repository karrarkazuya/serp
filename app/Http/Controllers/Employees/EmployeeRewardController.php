<?php

namespace App\Http\Controllers\Employees;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Employees\Concerns\ScopesEmployeeAllocation;
use App\Models\Employees\Employee;
use App\Models\Employees\EmployeeReward;
use App\Services\Company\CompanyContextService;
use App\Services\FileService;
use App\Helpers\GroupsQuery;
use App\Helpers\SearchFilters;
use App\Helpers\SortsTable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class EmployeeRewardController extends Controller
{
    use ScopesEmployeeAllocation;

    public function __construct(
        private readonly FileService $fileService,
        private readonly CompanyContextService $companyContext,
    ) {}

    public function read(Request $request)
    {
        $this->authorize('viewAny', Employee::class);

        $query = $this->scopeAllocationListing(EmployeeReward::query());

        SearchFilters::apply($query, $request);

        if ($request->query('filter') === 'archived') {
            $query->where('active', false);
        } elseif ($request->query('filter') === 'all') {
        } else {
            $query->active();
        }

        $groupBy = $request->query('group_by');
        if ($groupBy) {
            $fields = SearchFilters::fieldsFor(EmployeeReward::class);
            if (isset($fields[$groupBy])) {
                $records = (clone $query)->orderBy('name')->get();
                $groups  = GroupsQuery::apply($records, $fields[$groupBy]);
                return view('employees.rewards.index', compact('groups'));
            }
        }

        SortsTable::apply($query, $request);
        $records = $query->paginate(50)->withQueryString();

        return view('employees.rewards.index', compact('records'));
    }

    public function show(EmployeeReward $reward)
    {
        $this->authorize('viewAny', Employee::class);
        $this->assertAllocationInScope($reward);

        $this->loadAllocationWithScopedEmployees($reward, [
            'creator', 'updater', 'chatterMessages.user', 'attachedFile',
        ]);

        return view('employees.rewards.show', compact('reward'));
    }

    public function create()
    {
        $this->authorize('create', Employee::class);

        return view('employees.rewards.create');
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
            $fileRecord        = $this->fileService->store($request->file('file'), 'documents/rewards', 'employees.read');
            $data['file_path'] = $fileRecord->uuid;
        }
        unset($data['file']);

        $record = DB::transaction(function () use ($data, $fileRecord) {
            $record = EmployeeReward::create($data);
            $fileRecord?->update(['source_type' => $record->getTable(), 'source_id' => $record->id]);
            $record->logSystemMessage('Record created.');
            return $record;
        });

        return redirect()->route('employees.rewards.show', $record)->with('success', __('employees.reward_created'));
    }

    public function edit(EmployeeReward $reward)
    {
        $this->authorize('update', Employee::class);
        $this->assertAllocationInScope($reward);

        $reward->load('attachedFile');

        return view('employees.rewards.edit', compact('reward'));
    }

    public function write(Request $request, EmployeeReward $reward)
    {
        $this->authorize('update', Employee::class);
        $this->assertAllocationInScope($reward);

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
            if ($reward->file_path) {
                $this->fileService->deleteByUuid($reward->file_path);
            }
            $fileRecord        = $this->fileService->store($request->file('file'), 'documents/rewards', 'employees.read', null, $reward);
            $data['file_path'] = $fileRecord->uuid;
        }
        unset($data['file']);

        DB::transaction(function () use ($reward, $data) {
            $changes = $this->diffChanges($reward, $data);
            $reward->update($data);
            if ($changes) {
                $reward->logSystemMessage('Record updated: ' . implode(', ', $changes) . '.');
            }
        });

        return redirect()->route('employees.rewards.show', $reward)->with('success', __('employees.reward_updated'));
    }

    public function syncEmployees(Request $request, EmployeeReward $reward)
    {
        $this->authorize('update', Employee::class);
        $this->assertAllocationInScope($reward);

        $data = $request->validate([
            'employee_ids'   => 'nullable|array',
            'employee_ids.*' => 'integer|exists:hr_employees,id',
        ]);

        // Silent-keep pattern (see ScopesEmployeeAllocation): scope requested
        // IDs to the actor's active companies and preserve any out-of-scope
        // pivot rows so a single-company actor can't strip cross-tenant rows.
        $newIds = $this->scopeRequestedEmployeeIds($data['employee_ids'] ?? [], $reward->employees());

        DB::transaction(function () use ($reward, $newIds) {
            $oldIds  = $reward->employees()->pluck('hr_employees.id');
            $added   = Employee::whereIn('id', $newIds->diff($oldIds))->pluck('name');
            $removed = Employee::whereIn('id', $oldIds->diff($newIds))->pluck('name');

            $reward->employees()->sync($newIds->all());

            if ($added->isNotEmpty()) {
                $reward->logSystemMessage('Added: ' . $added->join(', ') . '.');
            }
            if ($removed->isNotEmpty()) {
                $reward->logSystemMessage('Removed: ' . $removed->join(', ') . '.');
            }
        });

        return back()->with('success', __('employees.position_employees_saved'));
    }

    public function replaceDocument(Request $request, EmployeeReward $reward)
    {
        $this->authorize('update', Employee::class);
        $this->assertAllocationInScope($reward);

        $request->validate(['file' => 'required|file|max:10240']);

        DB::transaction(function () use ($request, $reward) {
            if ($reward->file_path) {
                $this->fileService->deleteByUuid($reward->file_path);
            }
            $fileRecord = $this->fileService->store($request->file('file'), 'documents/rewards', 'employees.read', null, $reward);
            $reward->update(['file_path' => $fileRecord->uuid]);
            $reward->logSystemMessage('Document replaced.');
        });

        return back()->with('success', __('employees.document_replaced'));
    }

    public function deleteDocument(EmployeeReward $reward)
    {
        $this->authorize('update', Employee::class);
        $this->assertAllocationInScope($reward);

        DB::transaction(function () use ($reward) {
            if ($reward->file_path) {
                $this->fileService->deleteByUuid($reward->file_path);
            }
            $reward->update(['file_path' => null]);
            $reward->logSystemMessage('Document removed.');
        });

        return back()->with('success', __('employees.document_deleted'));
    }

    public function archive(EmployeeReward $reward)
    {
        $this->authorize('update', Employee::class);
        $this->assertAllocationInScope($reward);

        DB::transaction(function () use ($reward) {
            $reward->update(['active' => false]);
            $reward->logSystemMessage('Record archived.');
        });

        return redirect()->route('employees.rewards.index')->with('success', __('employees.reward_archived'));
    }

    public function unarchive(EmployeeReward $reward)
    {
        $this->authorize('update', Employee::class);
        $this->assertAllocationInScope($reward);

        DB::transaction(function () use ($reward) {
            $reward->update(['active' => true]);
            $reward->logSystemMessage('Record restored.');
        });

        return redirect()->route('employees.rewards.show', $reward)->with('success', __('employees.reward_unarchived'));
    }

    public function bulkUnlink(Request $request): RedirectResponse
    {
        $this->authorize('delete', Employee::class);

        $activeCompanyIds = $this->companyContext->getActiveCompanyIds();
        $selectAll = $request->boolean('select_all');
        $ids = $request->input('ids', []);

        DB::transaction(function () use ($selectAll, $ids, $activeCompanyIds) {
            $query = EmployeeReward::whereHas('employees', function ($q) use ($activeCompanyIds) {
                empty($activeCompanyIds)
                    ? $q->whereRaw('1 = 0')
                    : $q->whereIn('hr_employees.company_id', $activeCompanyIds);
            });
            if (!$selectAll) {
                $query->whereIn('id', $ids);
            }
            foreach ($query->get() as $reward) {
                if ($reward->file_path) {
                    $this->fileService->deleteByUuid($reward->file_path);
                }
                $reward->delete();
            }
        });

        return redirect()->route('employees.rewards.index')->with('success', __('employees.rewards_deleted'));
    }

    public function unlink(EmployeeReward $reward)
    {
        $this->authorize('delete', Employee::class);
        $this->assertAllocationInScope($reward);

        DB::transaction(function () use ($reward) {
            if ($reward->file_path) {
                $this->fileService->deleteByUuid($reward->file_path);
            }
            $reward->delete();
        });

        return redirect()->route('employees.rewards.index')->with('success', __('employees.reward_deleted'));
    }

    public function addComment(Request $request, EmployeeReward $reward)
    {
        $this->authorize('update', Employee::class);
        $this->assertAllocationInScope($reward);

        $request->validate(['body' => 'required|string|max:5000']);
        DB::transaction(fn () => $reward->logComment($request->body));

        return back()->with('success', __('employees.comment_added'));
    }

    private function diffChanges(EmployeeReward $record, array $data): array
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
