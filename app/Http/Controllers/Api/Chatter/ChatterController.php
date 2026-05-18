<?php

namespace App\Http\Controllers\Api\Chatter;

use App\Http\Controllers\Controller;
use App\Models\Chatter\ChatterMessage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ChatterController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'model_type' => 'required|string',
            'model_id'   => 'required|integer',
        ]);

        $messages = ChatterMessage::where('model_type', $request->model_type)
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

        $message = ChatterMessage::create([
            'model_type'   => $request->model_type,
            'model_id'     => $request->model_id,
            'user_id'      => auth()->id(),
            'message_type' => $request->get('message_type', 'comment'),
            'body'         => $request->body,
        ]);

        return response()->json(['message' => 'Message added.', 'data' => $message->load('user')], 201);
    }
}
