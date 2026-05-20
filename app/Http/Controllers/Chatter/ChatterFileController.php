<?php

namespace App\Http\Controllers\Chatter;

use App\Http\Controllers\Controller;
use App\Models\Chatter\ChatterMessage;
use App\Models\Workflow\Procedure;
use App\Models\Workflow\Ticket;
use Illuminate\Support\Facades\Storage;

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
        'App\Models\Workflow\Department'           => 'workflow.config.read',
        'App\Models\Workflow\Manager'              => 'workflow.config.read',
        'App\Models\Workflow\WorkflowUser'         => 'workflow.config.read',
    ];

    public function serve(ChatterMessage $chatterMessage, int $index, string $side)
    {
        abort_unless(in_array($side, ['from', 'to']), 404);

        $modelType = $chatterMessage->model_type;
        abort_unless(array_key_exists($modelType, self::ALLOWED_TYPES), 403);
        abort_unless(auth()->user()->hasPermission(self::ALLOWED_TYPES[$modelType]), 403);

        // For ticket/procedure, also enforce viewer-level access on the specific record
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

        $change   = $changes[$index];
        $pathKey  = $side === 'from' ? 'from_file_path' : 'to_file_path';
        $mimeKey  = $side === 'from' ? 'from_mime'      : 'to_mime';
        $nameKey  = $side === 'from' ? 'from'           : 'to';

        $path = $change[$pathKey] ?? null;
        abort_unless($path && Storage::disk('local')->exists($path), 404);

        $mime = $change[$mimeKey] ?? 'application/octet-stream';
        $name = $change[$nameKey] ?? 'file';

        if (str_starts_with($mime, 'image/')) {
            return Storage::disk('local')->response($path, $name, ['Content-Type' => $mime]);
        }

        return Storage::disk('local')->download($path, $name, ['Content-Type' => $mime]);
    }
}
