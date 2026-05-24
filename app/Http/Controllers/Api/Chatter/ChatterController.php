<?php

namespace App\Http\Controllers\Api\Chatter;

use App\Http\Controllers\Controller;
use App\Models\Chatter\ChatterMessage;
use App\Services\Company\CompanyContextService;
use App\Support\Chatter\AllowedTypes;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ChatterController extends Controller
{
    public function __construct(private readonly CompanyContextService $companyContext) {}

    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'model_type' => 'required|string',
            'model_id'   => 'required|integer',
        ]);

        $modelType = $request->model_type;
        abort_unless(array_key_exists($modelType, AllowedTypes::READ_PERMISSIONS), 403);
        abort_unless($request->user()->hasPermission(AllowedTypes::READ_PERMISSIONS[$modelType]), 403);

        AllowedTypes::authorizeRecordAccess(
            $modelType,
            (int) $request->model_id,
            'view',
            $this->companyContext,
            $request,
        );

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
        abort_unless(array_key_exists($modelType, AllowedTypes::WRITE_PERMISSIONS), 403);
        abort_unless($request->user()->hasPermission(AllowedTypes::WRITE_PERMISSIONS[$modelType]), 403);

        AllowedTypes::authorizeRecordAccess(
            $modelType,
            (int) $request->model_id,
            'comment',
            $this->companyContext,
            $request,
        );

        $message = DB::transaction(fn () => ChatterMessage::create([
            'model_type'   => $modelType,
            'model_id'     => $request->model_id,
            'user_id'      => auth()->id(),
            'message_type' => $request->input('message_type', 'comment'),
            'body'         => $request->body,
        ]));

        return response()->json(['message' => 'Message added.', 'data' => $message->load('user')], 201);
    }
}
