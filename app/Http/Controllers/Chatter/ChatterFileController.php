<?php

namespace App\Http\Controllers\Chatter;

use App\Http\Controllers\Controller;
use App\Models\Chatter\ChatterMessage;
use App\Services\Company\CompanyContextService;
use App\Support\Chatter\AllowedTypes;
use Illuminate\Http\Request;

class ChatterFileController extends Controller
{
    public function __construct(private readonly CompanyContextService $companyContext) {}

    /**
     * Resolve the File UUID from chatter metadata and redirect to the unified file route.
     * Metadata uses from_file_uuid / to_file_uuid keys (written by TicketController::saveInputs).
     */
    public function serve(Request $request, ChatterMessage $chatterMessage, int $index, string $side)
    {
        abort_unless(in_array($side, ['from', 'to'], true), 404);

        $modelType = $chatterMessage->model_type;
        abort_unless(array_key_exists($modelType, AllowedTypes::READ_PERMISSIONS), 403);
        abort_unless($request->user()->hasPermission(AllowedTypes::READ_PERMISSIONS[$modelType]), 403);

        AllowedTypes::authorizeRecordAccess(
            $modelType,
            (int) $chatterMessage->model_id,
            'view',
            $this->companyContext,
            $request,
        );

        $changes = $chatterMessage->metadata['changes'] ?? [];
        abort_unless(isset($changes[$index]), 404);

        $change  = $changes[$index];
        $uuidKey = $side === 'from' ? 'from_file_uuid' : 'to_file_uuid';
        $uuid    = $change[$uuidKey] ?? null;

        abort_unless($uuid, 404);

        // Final access control (permission + context ownership) is enforced by FileController
        return redirect()->route('files.serve', $uuid);
    }
}
