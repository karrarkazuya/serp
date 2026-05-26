<?php

namespace App\Http\Controllers\Workflow;

use App\Http\Controllers\Controller;
use App\Helpers\GroupsQuery;
use App\Helpers\SearchFilters;
use App\Helpers\SortsTable;
use App\Http\Requests\Workflow\StoreTicketTemplateRequest;
use App\Models\Employees\Department;
use App\Models\Workflow\Group;
use App\Models\Workflow\TicketTemplate;
use App\Services\Company\CompanyContextService;
use App\Services\Workflow\WorkflowConfigService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class TicketTemplateController extends Controller
{
    public function __construct(
        private readonly WorkflowConfigService $configService
    ) {}

    public function read(Request $request)
    {
        $this->authorize('viewAny', TicketTemplate::class);
        $query = TicketTemplate::query()->with(['defaultGroup', 'defaultDepartment']);
        SearchFilters::apply($query, $request);

        $groupBy = $request->query('group_by');
        if ($groupBy) {
            $fields = SearchFilters::fieldsFor(TicketTemplate::class);
            if (isset($fields[$groupBy])) {
                $records = (clone $query)->with(['defaultGroup', 'defaultDepartment'])->orderBy('id')->get();
                $groups  = GroupsQuery::apply($records, $fields[$groupBy]);
                return view('workflow.configuration.ticket-templates.index', compact('groups'));
            }
        }

        SortsTable::apply($query, $request);
        $templates = $query->paginate(20)->withQueryString();

        return view('workflow.configuration.ticket-templates.index', compact('templates'));
    }

    public function show(TicketTemplate $ticketTemplate)
    {
        $this->authorize('view', $ticketTemplate);
        $ticketTemplate->load(['defaultGroup', 'defaultDepartment', 'departments', 'inputs.options']);
        return view('workflow.configuration.ticket-templates.show', compact('ticketTemplate'));
    }

    public function create()
    {
        $this->authorize('create', TicketTemplate::class);
        $groups = Group::where('active', true)->orderBy('name')->get();
        $departments = Department::where('active', true)->orderBy('name')->get();

        return view('workflow.configuration.ticket-templates.create', compact('groups', 'departments'));
    }

    public function store(StoreTicketTemplateRequest $request)
    {
        $data = $request->validated();
        $deptIds  = $data['departments'] ?? [];
        $inputsData = $data['inputs'] ?? [];
        unset($data['departments'], $data['inputs']);

        $tpl = DB::transaction(function () use ($data, $deptIds, $inputsData) {
            $tpl = $this->configService->createTicketTemplate($data, $deptIds);
            $this->configService->syncTicketTemplateInputs($tpl, $inputsData);
            return $tpl;
        });

        return redirect()->route('workflow.config.ticket-templates.show', $tpl)->with('success', 'Ticket template created.');
    }

    public function edit(TicketTemplate $ticketTemplate)
    {
        $this->authorize('update', $ticketTemplate);
        $ticketTemplate->load(['departments', 'inputs.options']);
        $groups = Group::where('active', true)->orderBy('name')->get();
        $departments = Department::where('active', true)->orderBy('name')->get();

        return view('workflow.configuration.ticket-templates.edit', compact('ticketTemplate', 'groups', 'departments'));
    }

    public function write(Request $request, TicketTemplate $ticketTemplate)
    {
        $this->authorize('update', $ticketTemplate);

        // Rule 11: hr_departments has company_id, so dept FKs must be scoped
        // to the actor's active companies. Templates are global config records;
        // without this, a workflow admin in company A could attach company B's
        // departments and silently route B's tickets through this template.
        $activeCompanyIds = app(CompanyContextService::class)->getActiveCompanyIds();
        $deptRule = Rule::exists('hr_departments', 'id')->where(function ($q) use ($activeCompanyIds) {
            empty($activeCompanyIds)
                ? $q->whereRaw('1 = 0')
                : $q->whereIn('company_id', $activeCompanyIds);
        });

        $data = $request->validate([
            'name'                  => 'required|string|max:255',
            'description'           => 'nullable|string|max:5000',
            'default_group_id'      => 'nullable|exists:workflow_groups,id,deleted_at,NULL',
            'default_department_id' => ['nullable', $deptRule],
            'resolve_max_duration'  => 'nullable|integer|min:1',
            'enabled'               => 'boolean',
            'departments'           => 'nullable|array',
            'departments.*'         => $deptRule,
            'inputs'                => 'nullable|array',
            'inputs.*.id'           => 'nullable|integer',
            'inputs.*.name'         => 'required_with:inputs.*|string|max:255',
            'inputs.*.type'         => 'required_with:inputs.*|string|in:char,int,float,date,datetime,boolean,select,multiselect,textarea,file,label',
            'inputs.*.is_required'  => 'nullable|boolean',
            'inputs.*.sort_order'   => 'nullable|integer',
            'inputs.*.options'      => 'nullable|string',
        ]);
        $deptIds    = $data['departments'] ?? [];
        $inputsData = $data['inputs'] ?? [];
        unset($data['departments'], $data['inputs']);

        DB::transaction(function () use ($ticketTemplate, $data, $deptIds, $inputsData) {
            $this->configService->updateTicketTemplate($ticketTemplate, $data, $deptIds);
            $this->configService->syncTicketTemplateInputs($ticketTemplate, $inputsData);
        });

        return redirect()->route('workflow.config.ticket-templates.show', $ticketTemplate)->with('success', 'Ticket template updated.');
    }

    public function unlink(TicketTemplate $ticketTemplate)
    {
        $this->authorize('delete', $ticketTemplate);
        DB::transaction(fn () => $this->configService->deleteTicketTemplate($ticketTemplate));

        return redirect()->route('workflow.config.ticket-templates.index')->with('success', 'Ticket template deleted.');
    }

    public function addComment(Request $request, TicketTemplate $ticketTemplate)
    {
        $this->authorize('comment', $ticketTemplate);

        $request->validate(['body' => 'required|string|max:5000']);
        DB::transaction(fn () => $ticketTemplate->logComment($request->body));

        return back()->with('success', 'Comment added.');
    }
}
