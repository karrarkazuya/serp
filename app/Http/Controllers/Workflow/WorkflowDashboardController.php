<?php

namespace App\Http\Controllers\Workflow;

use App\Http\Controllers\Controller;
use App\Models\Workflow\Ticket;
use App\Models\Workflow\WorkflowUser;
use Carbon\Carbon;
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

        $overdueCount = Ticket::where('state', 'pending')
            ->where('active', true)
            ->whereNotNull('resolve_deadline')
            ->where('resolve_deadline', '<', now())
            ->forUser($user)
            ->count();

        $completedTodayCount = Ticket::where('state', 'completed')
            ->whereDate('updated_at', today())
            ->forUser($user)
            ->count();

        // Chart: completions over last 7 days
        $last7Dates    = collect(range(6, 0))->map(fn ($i) => now()->subDays($i)->format('Y-m-d'));
        $completedByDay = Ticket::forUser($user)
            ->where('state', 'completed')
            ->where('updated_at', '>=', now()->subDays(6)->startOfDay())
            ->selectRaw('DATE(updated_at) as date, count(*) as count')
            ->groupBy('date')
            ->pluck('count', 'date');
        $chartLabels   = $last7Dates->map(fn ($d) => Carbon::parse($d)->format('D'))->values();
        $chartActivity = $last7Dates->map(fn ($d) => (int) $completedByDay->get($d, 0))->values();

        // Chart: standalone ticket state distribution
        $ticketStates = Ticket::forUser($user)
            ->whereNull('procedure_id')
            ->where('active', true)
            ->selectRaw('state, count(*) as count')
            ->groupBy('state')
            ->pluck('count', 'state');
        $chartStatus = [
            (int) $ticketStates->get('pending', 0),
            (int) $ticketStates->get('completed', 0),
            (int) $ticketStates->get('closed', 0),
            (int) $ticketStates->get('rejected', 0),
        ];

        return view('workflow.dashboard', compact(
            'ticketsAssignedCount',
            'procedureTicketsAssignedCount',
            'overdueCount',
            'completedTodayCount',
            'pendingTickets',
            'pendingProcedureTickets',
            'chartLabels',
            'chartActivity',
            'chartStatus'
        ));
    }
}
