<?php

namespace App\Http\Controllers\Workflow;

use App\Http\Controllers\Controller;
use App\Helpers\GroupsQuery;
use App\Helpers\SearchFilters;
use App\Helpers\SortsTable;
use App\Models\User;
use App\Models\Workflow\WorkflowUser;
use App\Services\Workflow\WorkflowConfigService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class WorkflowUserController extends Controller
{
    public function __construct(
        private readonly WorkflowConfigService $configService
    ) {}

    public function read(Request $request)
    {
        $this->authorize('viewAny', WorkflowUser::class);

        $query = User::query()
            ->where('active', true)
            ->with(['workflowUser.defaultDepartment', 'workflowUser.groups']);

        SearchFilters::apply($query, $request);

        $groupBy = $request->query('group_by');
        if ($groupBy) {
            $fields = SearchFilters::fieldsFor(User::class);
            if (isset($fields[$groupBy])) {
                $records = (clone $query)->with(['workflowUser.defaultDepartment', 'workflowUser.groups'])->orderBy('id')->get();
                $groups  = GroupsQuery::apply($records, $fields[$groupBy]);
                return view('workflow.configuration.users.index', compact('groups'));
            }
        }

        SortsTable::apply($query, $request, 'name');

        $users = $query->paginate(30)->withQueryString();

        return view('workflow.configuration.users.index', compact('users'));
    }

    public function show(User $user)
    {
        $this->authorize('viewAny', WorkflowUser::class);

        $wu = $user->workflowUser;
        if ($wu) {
            $wu->load(['defaultDepartment', 'groups', 'assignableDepartments', 'manager']);
        }
        return view('workflow.configuration.users.show', compact('user', 'wu'));
    }

    public function edit(User $user)
    {
        $this->authorize('create', WorkflowUser::class);

        $wu = $this->configService->ensureWorkflowUser($user);
        $wu->load(['groups', 'assignableDepartments']);

        return view('workflow.configuration.users.edit', compact('user', 'wu'));
    }

    public function write(Request $request, User $user)
    {
        $this->authorize('create', WorkflowUser::class);

        $data = $request->validate([
            'default_department_id' => 'required|exists:hr_departments,id',
            'groups'                => 'nullable|array',
            'groups.*'              => 'exists:workflow_groups,id,deleted_at,NULL',
            'departments'           => 'nullable|array',
            'departments.*'         => 'exists:hr_departments,id',
        ]);

        $data['active'] = $request->boolean('active');

        $groupIds = $data['groups'] ?? [];
        $deptIds  = $data['departments'] ?? [];
        unset($data['groups'], $data['departments']);

        $wu = $this->configService->ensureWorkflowUser($user);
        DB::transaction(fn () => $this->configService->updateWorkflowUser($wu, $data, $groupIds, $deptIds));

        return redirect()->route('workflow.config.users.show', $user)->with('success', 'Workflow profile saved.');
    }

    public function addComment(Request $request, User $user)
    {
        $this->authorize('create', WorkflowUser::class);

        $wu = $this->configService->ensureWorkflowUser($user);
        $request->validate(['body' => 'required|string|max:5000']);
        DB::transaction(fn () => $wu->logComment($request->body));

        return back()->with('success', 'Comment added.');
    }
}
