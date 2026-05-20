<?php

namespace App\Http\Controllers\Workflow;

use App\Http\Controllers\Controller;
use App\Helpers\SearchFilters;
use App\Helpers\SortsTable;
use App\Models\Workflow\Manager;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ManagerController extends Controller
{
    public function read(Request $request)
    {
        $this->authorize('viewAny', Manager::class);

        $query = Manager::query()->with(['workflowUser.user', 'departments']);
        SearchFilters::apply($query, $request);
        SortsTable::apply($query, $request, 'workflow_user');
        $managers = $query->paginate(24)->withQueryString();

        return view('workflow.configuration.managers.index', compact('managers'));
    }

    public function show(Manager $manager)
    {
        $this->authorize('view', $manager);

        $manager->load(['workflowUser.user', 'workflowUser.defaultDepartment', 'departments']);
        return view('workflow.configuration.managers.show', compact('manager'));
    }

    public function addComment(Request $request, Manager $manager)
    {
        $this->authorize('comment', $manager);

        $request->validate(['body' => 'required|string|max:5000']);
        DB::transaction(fn () => $manager->logComment($request->body));

        return back()->with('success', 'Comment added.');
    }
}
