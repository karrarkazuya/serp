<?php

namespace App\Http\Controllers\Workflow;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class WorkflowReportController extends Controller
{
    public function index(Request $request)
    {
        $request->user()->hasPermission('workflow.tickets.read') || abort(403);

        return view('workflow.reports.index');
    }

    public function show(Request $request, string $report)
    {
        $request->user()->hasPermission('workflow.tickets.read') || abort(403);

        $reports = [
            'activity' => 'Activity Report',
            'procedure-performance' => 'Procedure Performance',
            'ticket-performance' => 'Ticket Performance',
            'task-performance' => 'Task Performance',
        ];

        abort_unless(array_key_exists($report, $reports), 404);

        //to do port the Odoo report client actions and report data endpoints.
        return view('workflow.reports.show', [
            'reportKey' => $report,
            'reportTitle' => $reports[$report],
        ]);
    }
}
