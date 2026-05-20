<?php

namespace App\Http\Controllers\Chat;

use App\Http\Controllers\Controller;
use App\Models\Chat\ChatMessage;
use App\Models\Chat\ChatMessageFile;
use App\Models\Chat\ChatRoom;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

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
        $room = ChatRoom::where('active', true)->where('type', 'channel')->orderBy('created_at')->first();
        if ($room) {
            return redirect()->route('chat.show', $room);
        }

        [$channels, $dms, $unreadCounts, $users] = $this->sidebarData();
        return view('chat.show', ['channels' => $channels, 'dms' => $dms, 'unreadCounts' => $unreadCounts, 'users' => $users, 'room' => null, 'grouped' => []]);
    }

    public function show(ChatRoom $room)
    {
        $this->authorize('view', $room);
        $auth = Auth::user();

        [$channels, $dms, $unreadCounts, $users] = $this->sidebarData();

        $messages = $room->messages()->with(['user', 'files'])->orderBy('created_at')->get();
        $grouped  = $this->groupMessages($messages);

        // Mark DM / group as read when opened
        if (!$room->isChannel()) {
            $room->members()->updateExistingPivot($auth->id, ['last_read_at' => now()]);
            $unreadCounts[$room->id] = 0;
            $room->load('members');
        }

        return view('chat.show', compact('room', 'channels', 'dms', 'grouped', 'unreadCounts', 'users'));
    }

    public function store(Request $request, ChatRoom $room)
    {
        $this->authorize('post', $room);
        $auth = Auth::user();

        $request->validate([
            'body'    => 'nullable|string|max:5000',
            'files.*' => ['nullable', 'file', 'max:10240'],
        ]);

        $body  = trim($request->input('body', ''));
        $files = $request->file('files', []);

        abort_if(empty($body) && empty(array_filter($files)), 422);

        $message = ChatMessage::create([
            'room_id' => $room->id,
            'user_id' => $auth->id,
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

        // Notify other members for DM / group rooms
        if (!$room->isChannel()) {
            $preview = Str::limit($body ?: '📎 Sent a file', 80);
            $url     = route('chat.show', $room);
            foreach ($room->members()->where('user_id', '!=', $auth->id)->get() as $member) {
                $member->notify($auth->name, $preview, $url);
            }
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
            'type'               => 'channel',
        ]);

        return redirect()->route('chat.show', $room);
    }

    public function openDirect(User $user)
    {
        $auth = Auth::user();

        // Find existing 1-on-1 DM between exactly these two users
        $room = ChatRoom::where('type', 'direct')
            ->whereHas('members', fn ($q) => $q->where('user_id', $auth->id))
            ->whereHas('members', fn ($q) => $q->where('user_id', $user->id))
            ->first();

        if (!$room) {
            $room = DB::transaction(function () use ($auth, $user) {
                $r = ChatRoom::create([
                    'name'               => '',
                    'type'               => 'direct',
                    'created_by_user_id' => $auth->id,
                ]);
                $r->members()->attach([$auth->id, $user->id]);
                return $r;
            });
        }

        return redirect()->route('chat.show', $room);
    }

    public function file(ChatRoom $room, ChatMessageFile $file)
    {
        $this->authorize('view', $room);
        abort_unless($file->message->room_id === $room->id, 403);

        return Storage::disk($file->disk)->download($file->path, $file->original_name);
    }

    private function sidebarData(): array
    {
        $auth = Auth::user();

        $channels = ChatRoom::where('active', true)
            ->where('type', 'channel')
            ->with('lastMessage.user')
            ->orderBy('name')
            ->get();

        $dms = ChatRoom::where('active', true)
            ->whereIn('type', ['direct', 'group'])
            ->whereHas('members', fn ($q) => $q->where('user_id', $auth->id))
            ->with(['members', 'lastMessage.user'])
            ->get();

        $unreadCounts = $dms->mapWithKeys(fn ($r) => [$r->id => $r->unreadCountFor($auth)])->toArray();

        $users = User::where('id', '!=', 0)->orderBy('name')->get();

        return [$channels, $dms, $unreadCounts, $users];
    }

    private function groupMessages($messages): array
    {
        $grouped    = [];
        $prevUserId = null;
        $prevDate   = null;

        foreach ($messages as $msg) {
            $date       = $msg->created_at->format('Y-m-d');
            $showDate   = $date !== $prevDate;
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
