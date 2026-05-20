<?php

namespace App\Http\Controllers;

use App\Models\Workflow\WorkflowSharedLink;
use App\Models\Workflow\Procedure;
use App\Models\Workflow\Ticket;

class SharedLinkController extends Controller
{
    public function show(string $token)
    {
        $link = WorkflowSharedLink::where('token', $token)
            ->where('enabled', true)
            ->first();

        if (!$link) {
            abort(404);
        }

        $shareable = $link->shareable;

        if (!$shareable) {
            abort(404);
        }

        return match (true) {
            $shareable instanceof Ticket    => $this->showTicket($link, $shareable),
            $shareable instanceof Procedure => $this->showProcedure($link, $shareable),
            default                         => abort(404),
        };
    }

    private function showTicket(WorkflowSharedLink $link, Ticket $ticket)
    {
        $ticket->load(['assignedDepartment', 'assignedUser', 'template', 'procedureStep', 'inputs', 'procedure']);
        $company = $ticket->company;

        return view('shared.ticket', compact('link', 'ticket', 'company'));
    }

    private function showProcedure(WorkflowSharedLink $link, Procedure $procedure)
    {
        $procedure->load(['procedureTemplate', 'tickets' => fn ($q) => $q->orderBy('id'), 'tickets.inputs']);
        $company = $procedure->company;

        return view('shared.procedure', compact('link', 'procedure', 'company'));
    }
}
