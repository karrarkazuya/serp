<?php

namespace App\Http\Controllers;

use App\Models\Notification;
use Illuminate\Http\JsonResponse;

class NotificationController extends Controller
{
    public function unreadCount(): JsonResponse
    {
        $count = auth()->user()->notifications()->whereNull('seen_at')->count();
        return response()->json(['count' => $count]);
    }

    public function recent(): JsonResponse
    {
        $notifications = auth()->user()->notifications()->latest()->take(15)->get();
        return response()->json(['notifications' => $notifications]);
    }

    public function markSeen(Notification $notification): JsonResponse
    {
        abort_unless($notification->user_id === auth()->id(), 403);
        $notification->markSeen();
        return response()->json(['ok' => true]);
    }

    public function markAllSeen(): JsonResponse
    {
        auth()->user()->notifications()->whereNull('seen_at')->update(['seen_at' => now()]);
        return response()->json(['ok' => true]);
    }
}
