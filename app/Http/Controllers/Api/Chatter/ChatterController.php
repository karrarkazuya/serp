<?php

namespace App\Http\Controllers\Api\Chatter;

use App\Http\Controllers\Controller;
use App\Models\Chatter\ChatterMessage;
use App\Models\Workflow\Ticket;
use App\Models\Workflow\Procedure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ChatterController extends Controller
{
    /**
     * Maps allowed model_type values to the permission required to read/write them.
     * Any model_type not in this list is rejected with 403.
     */
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

    private const WRITE_PERMISSION = [
        'App\Models\Contacts\Contact'              => 'contacts.write',
        'App\Models\Settings\Company'              => 'companies.write',
        'App\Models\User'                          => 'users.write',
        'App\Models\Workflow\Ticket'               => 'workflow.tickets.write',
        'App\Models\Workflow\Procedure'            => 'workflow.procedures.write',
        'App\Models\Workflow\TicketTemplate'       => 'workflow.config.write',
        'App\Models\Workflow\ProcedureTemplate'    => 'workflow.config.write',
        'App\Models\Workflow\Group'                => 'workflow.config.write',
        'App\Models\Employees\Department'           => 'employees.write',
        'App\Models\Workflow\Manager'              => 'workflow.config.write',
        'App\Models\Workflow\WorkflowUser'         => 'workflow.config.write',
    ];

    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'model_type' => 'required|string',
            'model_id'   => 'required|integer',
        ]);

        $modelType = $request->model_type;
        abort_unless(array_key_exists($modelType, self::ALLOWED_TYPES), 403);
        abort_unless($request->user()->hasPermission(self::ALLOWED_TYPES[$modelType]), 403);

        $this->authorizeRecordAccess($modelType, (int) $request->model_id, 'view');

        $messages = ChatterMessage::where('model_type', $modelType)
            ->where('model_id', $request->model_id)
            ->with('user')
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json($messages);
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'model_type'   => 'required|string',
            'model_id'     => 'required|integer',
            'body'         => 'required|string|max:5000',
            'message_type' => 'in:log,comment,system',
        ]);

        $modelType = $request->model_type;
        abort_unless(array_key_exists($modelType, self::WRITE_PERMISSION), 403);
        abort_unless($request->user()->hasPermission(self::WRITE_PERMISSION[$modelType]), 403);

        $this->authorizeRecordAccess($modelType, (int) $request->model_id, 'comment');

        $message = ChatterMessage::create([
            'model_type'   => $modelType,
            'model_id'     => $request->model_id,
            'user_id'      => auth()->id(),
            'message_type' => $request->get('message_type', 'comment'),
            'body'         => $request->body,
        ]);

        return response()->json(['message' => 'Message added.', 'data' => $message->load('user')], 201);
    }

    /**
     * For record types that enforce viewer-level access (tickets, procedures),
     * verify the authenticated user can actually see/act on that specific record.
     * Other types (contacts, companies, users, config) are permission-only.
     */
    private function authorizeRecordAccess(string $modelType, int $modelId, string $ability): void
    {
        $record = match ($modelType) {
            'App\Models\Workflow\Ticket'    => Ticket::findOrFail($modelId),
            'App\Models\Workflow\Procedure' => Procedure::findOrFail($modelId),
            default                         => null,
        };

        if ($record !== null) {
            $this->authorize($ability, $record);
        }
    }
}
