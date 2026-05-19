<?php

namespace App\Http\Controllers\Chat;

use App\Http\Controllers\Controller;
use App\Models\Chat\ChatMessage;
use App\Models\Chat\ChatMessageFile;
use App\Models\Chat\ChatRoom;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class ChatController extends Controller
{
    private const ALLOWED_MIMES = [
        'image/jpeg', 'image/png', 'image/gif', 'image/webp',
        'application/pdf',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.ms-excel',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'text/plain', 'text/csv',
    ];

    public function index()
    {
        $room = ChatRoom::where('active', true)->orderBy('created_at')->first();
        if ($room) {
            return redirect()->route('chat.show', $room);
        }
        $rooms = collect();
        return view('chat.show', ['rooms' => $rooms, 'room' => null, 'grouped' => []]);
    }

    public function show(ChatRoom $room)
    {
        abort_unless($room->active, 404);

        $rooms   = ChatRoom::where('active', true)->with('lastMessage.user')->orderBy('name')->get();
        $messages = $room->messages()->with(['user', 'files'])->orderBy('created_at')->get();
        $grouped = $this->groupMessages($messages);

        return view('chat.show', compact('room', 'rooms', 'grouped'));
    }

    public function store(Request $request, ChatRoom $room)
    {
        abort_unless($room->active, 404);

        $request->validate([
            'body'    => 'nullable|string|max:5000',
            'files.*' => ['nullable', 'file', 'max:10240'],
        ]);

        $body  = trim($request->input('body', ''));
        $files = $request->file('files', []);

        abort_if(empty($body) && empty(array_filter($files)), 422);

        $message = ChatMessage::create([
            'room_id' => $room->id,
            'user_id' => Auth::id(),
            'body'    => $body ?: null,
        ]);

        foreach ($files as $file) {
            if (!$file || !in_array($file->getMimeType(), self::ALLOWED_MIMES)) {
                continue;
            }

            $path = $file->store("chat/{$room->id}", 'local');

            ChatMessageFile::create([
                'message_id'    => $message->id,
                'disk'          => 'local',
                'path'          => $path,
                'original_name' => $file->getClientOriginalName(),
                'mime_type'     => $file->getMimeType(),
                'size'          => $file->getSize(),
            ]);
        }

        return redirect()->route('chat.show', $room)->with('scrollToBottom', true);
    }

    public function createRoom(Request $request)
    {
        $request->validate([
            'name'        => 'required|string|max:100',
            'description' => 'nullable|string|max:255',
        ]);

        $room = ChatRoom::create([
            'name'               => $request->name,
            'description'        => $request->description,
            'created_by_user_id' => Auth::id(),
        ]);

        return redirect()->route('chat.show', $room);
    }

    public function file(ChatRoom $room, ChatMessageFile $file)
    {
        abort_unless($file->message->room_id === $room->id, 403);
        abort_unless($room->active, 404);

        return Storage::disk($file->disk)->download($file->path, $file->original_name);
    }

    private function groupMessages($messages): array
    {
        $grouped     = [];
        $prevUserId  = null;
        $prevDate    = null;

        foreach ($messages as $msg) {
            $date      = $msg->created_at->format('Y-m-d');
            $showDate  = $date !== $prevDate;
            $showHeader = $msg->user_id !== $prevUserId || $showDate;

            $grouped[] = [
                'message'     => $msg,
                'show_date'   => $showDate,
                'show_header' => $showHeader,
                'date_label'  => $this->dateLabel($msg->created_at),
            ];

            $prevUserId = $msg->user_id;
            $prevDate   = $date;
        }

        return $grouped;
    }

    private function dateLabel(\Carbon\Carbon $date): string
    {
        if ($date->isToday())     return 'Today';
        if ($date->isYesterday()) return 'Yesterday';
        return $date->format('F j, Y');
    }
}
