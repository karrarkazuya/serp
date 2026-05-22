<?php

namespace App\Http\Controllers\Workflow;

use App\Http\Controllers\Controller;
use App\Models\Workflow\Procedure;
use App\Models\Workflow\Ticket;
use App\Models\Workflow\WorkflowSharedLink;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ShareController extends Controller
{
    // ── Tickets ──────────────────────────────────────────────────────────────

    public function toggleTicket(Ticket $ticket): RedirectResponse
    {
        $this->authorize('update', $ticket);
        $link = WorkflowSharedLink::forModel($ticket);
        DB::transaction(fn () => $link->update(['enabled' => !$link->enabled]));

        return back()->with('success', $link->enabled ? 'Sharing enabled.' : 'Sharing disabled.');
    }

    public function messageTicket(Request $request, Ticket $ticket): RedirectResponse
    {
        $this->authorize('update', $ticket);
        $request->validate(['message' => 'nullable|string|max:2000']);

        DB::transaction(fn () => WorkflowSharedLink::forModel($ticket)->update(['message' => $request->message]));

        return back()->with('success', 'Share message saved.');
    }

    // ── Procedures ────────────────────────────────────────────────────────────

    public function toggleProcedure(Procedure $procedure): RedirectResponse
    {
        $this->authorize('update', $procedure);
        $link = WorkflowSharedLink::forModel($procedure);
        DB::transaction(fn () => $link->update(['enabled' => !$link->enabled]));

        return back()->with('success', $link->enabled ? 'Sharing enabled.' : 'Sharing disabled.');
    }

    public function messageProcedure(Request $request, Procedure $procedure): RedirectResponse
    {
        $this->authorize('update', $procedure);
        $request->validate(['message' => 'nullable|string|max:2000']);

        DB::transaction(fn () => WorkflowSharedLink::forModel($procedure)->update(['message' => $request->message]));

        return back()->with('success', 'Share message saved.');
    }

    // ── Procedure Tickets ─────────────────────────────────────────────────────

    public function toggleProcedureTicket(): RedirectResponse
    {
        abort(403);
    }

    public function messageProcedureTicket(): RedirectResponse
    {
        abort(403);
    }
}
