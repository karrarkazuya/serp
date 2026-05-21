<?php

namespace App\Http\Controllers\Chatter;

use App\Http\Controllers\Controller;
use App\Models\Chatter\ChatterMessage;
use App\Models\Workflow\Procedure;
use App\Models\Workflow\Ticket;

class ChatterFileController extends Controller
{
    private const ALLOWED_TYPES = [
        'App\Models\Contacts\Contact'              => 'contacts.read',
        'App\Models\Settings\Company'              => 'companies.read',
        'App\Models\User'                          => 'users.read',
        'App\Models\Workflow\Ticket'               => 'workflow.tickets.read',
        'App\Models\Workflow\Procedure'            => 'workflow.procedures.read',
        'App\Models\Workflow\TicketTemplate'       => 'workflow.config.read',
        'App\Models\Workflow\ProcedureTemplate'    => 'workflow.config.read',
        'App\Models\Workflow\Group'                => 'workflow.config.read',
        'App\Models\Employees\Department'           => 'employees.read',
        'App\Models\Workflow\Manager'              => 'workflow.config.read',
        'App\Models\Workflow\WorkflowUser'         => 'workflow.config.read',
    ];

    /**
     * Resolve the File UUID from chatter metadata and redirect to the unified file route.
     * Metadata uses from_file_uuid / to_file_uuid keys (written by TicketController::saveInputs).
     */
    public function serve(ChatterMessage $chatterMessage, int $index, string $side)
    {
        abort_unless(in_array($side, ['from', 'to']), 404);

        $modelType = $chatterMessage->model_type;
        abort_unless(array_key_exists($modelType, self::ALLOWED_TYPES), 403);
        abort_unless(auth()->user()->hasPermission(self::ALLOWED_TYPES[$modelType]), 403);

        // For ticket/procedure, enforce viewer-level access on the specific record
        $record = match ($modelType) {
            'App\Models\Workflow\Ticket'    => Ticket::findOrFail($chatterMessage->model_id),
            'App\Models\Workflow\Procedure' => Procedure::findOrFail($chatterMessage->model_id),
            default                         => null,
        };
        if ($record !== null) {
            $this->authorize('view', $record);
        }

        $changes = $chatterMessage->metadata['changes'] ?? [];
        abort_unless(isset($changes[$index]), 404);

        $change  = $changes[$index];
        $uuidKey = $side === 'from' ? 'from_file_uuid' : 'to_file_uuid';
        $uuid    = $change[$uuidKey] ?? null;

        abort_unless($uuid, 404);

        // Access control (permission + context ownership) is enforced by FileController
        return redirect()->route('files.serve', $uuid);
    }
}
