<?php

namespace App\Http\Controllers\Workflow;

use App\Http\Controllers\Controller;
use App\Helpers\SearchFilters;
use App\Helpers\SortsTable;
use App\Http\Requests\Workflow\StoreGroupRequest;
use App\Models\Workflow\Group;
use App\Services\Workflow\WorkflowConfigService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class GroupController extends Controller
{
    public function __construct(
        private readonly WorkflowConfigService $configService
    ) {}

    public function read(Request $request)
    {
        $this->authorize('viewAny', Group::class);
        $query = Group::query();
        SearchFilters::apply($query, $request);
        SortsTable::apply($query, $request);
        $groups = $query->paginate(30)->withQueryString();

        return view('workflow.configuration.groups.index', compact('groups'));
    }

    public function create()
    {
        $this->authorize('create', Group::class);

        return view('workflow.configuration.groups.create');
    }

    public function show(Group $group)
    {
        $this->authorize('view', $group);

        $group->load('workflowUsers.user');
        $messages = $group->chatterMessages()->with('user')->latest()->get();

        return view('workflow.configuration.groups.show', compact('group', 'messages'));
    }

    public function store(StoreGroupRequest $request)
    {
        DB::transaction(fn () => $this->configService->createGroup($request->validated()));

        return redirect()->route('workflow.config.groups.index')->with('success', 'Group created.');
    }

    public function edit(Group $group)
    {
        $this->authorize('update', $group);

        return view('workflow.configuration.groups.edit', compact('group'));
    }

    public function write(Request $request, Group $group)
    {
        $this->authorize('update', $group);
        $data = $request->validate([
            'name'   => "required|string|max:255|unique:workflow_groups,name,{$group->id}",
            'active' => 'boolean',
        ]);
        DB::transaction(fn () => $this->configService->updateGroup($group, $data));

        return redirect()->route('workflow.config.groups.index')->with('success', 'Group updated.');
    }

    public function unlink(Group $group)
    {
        $this->authorize('delete', $group);
        DB::transaction(fn () => $this->configService->deleteGroup($group));

        return redirect()->route('workflow.config.groups.index')->with('success', 'Group deleted.');
    }

    public function addComment(Request $request, Group $group)
    {
        $this->authorize('comment', $group);

        $request->validate(['body' => 'required|string|max:5000']);
        DB::transaction(fn () => $group->logComment($request->body));

        return back()->with('success', 'Comment added.');
    }
}
