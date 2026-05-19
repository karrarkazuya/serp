<?php

namespace App\Http\Controllers\Workflow;

use App\Http\Controllers\Controller;
use App\Models\Workflow\Ticket;
use App\Models\Workflow\WorkflowUser;
use Illuminate\Http\Request;

class WorkflowDashboardController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('viewAny', Ticket::class);

        $user = $request->user();
        $workflowUser = WorkflowUser::where('user_id', $user->id)->where('active', true)->first();

        $ticketsAssignedCount = $workflowUser
            ? Ticket::where('state', 'pending')->where('active', true)->whereNull('procedure_id')->where('assigned_to_user_id', $workflowUser->id)->count()
            : 0;

        $deptIds = $workflowUser ? $workflowUser->getAssignableDepartmentIds() : [];

        $procedureTicketsAssignedCount = Ticket::whereNotNull('procedure_id')
            ->where('state', 'pending')
            ->where('active', true)
            ->when(!$user->hasPermission('workflow.admin') && !empty($deptIds),
                fn ($q) => $q->whereIn('assigned_to_department_id', $deptIds)
            )
            ->when(!$user->hasPermission('workflow.admin') && empty($deptIds),
                fn ($q) => $q->where('assigned_to_user_id', $user->id)
            )
            ->count();

        $pendingTickets = Ticket::query()
            ->with(['template', 'assignedDepartment', 'company'])
            ->whereNull('procedure_id')
            ->where('state', 'pending')
            ->where('active', true)
            ->forUser($user)
            ->orderBy('created_at', 'desc')
            ->limit(15)
            ->get();

        $procedureTicketQuery = Ticket::query()
            ->with(['procedure.company', 'assignedDepartment'])
            ->whereNotNull('procedure_id')
            ->where('state', 'pending');

        if ($user->hasPermission('workflow.admin')) {
            // show all
        } elseif (!empty($deptIds)) {
            $procedureTicketQuery->whereIn('assigned_to_department_id', $deptIds);
        } else {
            $procedureTicketQuery->where('assigned_to_user_id', $user->id);
        }

        $pendingProcedureTickets = $procedureTicketQuery->orderBy('resolve_deadline')->limit(15)->get();

        return view('workflow.dashboard', compact(
            'ticketsAssignedCount',
            'procedureTicketsAssignedCount',
            'pendingTickets',
            'pendingProcedureTickets'
        ));
    }
}
