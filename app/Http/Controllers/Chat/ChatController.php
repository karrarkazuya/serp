<?php

namespace App\Http\Controllers\Chat;

use App\Http\Controllers\Controller;
use App\Models\Chat\ChatMessage;
use App\Models\Chat\ChatMessageFile;
use App\Models\Chat\ChatRoom;
use App\Models\User;
use App\Services\FileService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
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

    public function __construct(private readonly FileService $fileService) {}

    public function index()
    {
        [$channels, $dms, $unreadCounts, $users] = $this->sidebarData();
        return view('chat.show', [
            'channels'     => $channels,
            'dms'          => $dms,
            'unreadCounts' => $unreadCounts,
            'users'        => $users,
            'room'         => null,
            'grouped'      => [],
        ]);
    }

    public function show(ChatRoom $room)
    {
        $this->authorize('view', $room);
        $auth = Auth::user();

        [$channels, $dms, $unreadCounts, $users] = $this->sidebarData();

        $messages = $room->messages()->with(['user', 'files'])->orderBy('created_at')->get();
        $grouped  = $this->groupMessages($messages);

        // Mark as read for all room types
        DB::transaction(fn () => $room->members()->updateExistingPivot($auth->id, ['last_read_at' => now()]));
        $unreadCounts[$room->id] = 0;

        $room->load('members');

        return view('chat.show', compact('room', 'channels', 'dms', 'grouped', 'unreadCounts', 'users'));
    }

    public function store(Request $request, ChatRoom $room)
    {
        $this->authorize('post', $room);
        $auth = Auth::user();

        $request->validate([
            'body'    => 'nullable|string|max:5000',
            'files'   => 'nullable|array|max:10',
            'files.*' => ['nullable', 'file', 'max:10240', 'mimetypes:' . implode(',', self::ALLOWED_MIMES)],
        ]);

        $body  = trim($request->input('body', ''));
        $files = array_values(array_filter($request->file('files', []) ?: []));

        abort_if($body === '' && empty($files), 422);

        DB::transaction(function () use ($room, $auth, $body, $files) {
            $message = ChatMessage::create([
                'room_id' => $room->id,
                'user_id' => $auth->id,
                'body'    => $body !== '' ? $body : null,
            ]);

            foreach ($files as $file) {
                // Belt-and-braces: validation already enforces the MIME allowlist.
                if (!in_array($file->getMimeType(), self::ALLOWED_MIMES, true)) {
                    abort(422, 'File type not allowed.');
                }
                $fileRecord = $this->fileService->store($file, "chat/{$room->id}", null, $room, $message);
                ChatMessageFile::create([
                    'message_id'    => $message->id,
                    'disk'          => $fileRecord->disk,
                    'path'          => $fileRecord->uuid,
                    'original_name' => $fileRecord->original_name,
                    'mime_type'     => $fileRecord->mime_type,
                    'size'          => $fileRecord->size,
                ]);
            }

            // Notify all other members
            $preview = Str::limit($body !== '' ? $body : '📎 Sent a file', 80);
            $url     = route('chat.show', $room);
            foreach ($room->members()->where('user_id', '!=', $auth->id)->get() as $member) {
                $member->notify($auth->name, $preview, $url);
            }
        });

        return redirect()->route('chat.show', $room)->with('scrollToBottom', true);
    }

    public function createRoom(Request $request)
    {
        $request->validate([
            'name'         => 'required|string|max:100',
            'description'  => 'nullable|string|max:255',
            'member_ids'   => 'nullable|array|max:200',
            'member_ids.*' => 'integer|exists:users,id',
        ]);

        $requestedIds = collect($request->input('member_ids', []))
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        // Reject inactive users — off-boarded accounts must not be re-attached to new rooms.
        $activeIds = User::whereIn('id', $requestedIds)
            ->where('active', true)
            ->whereKeyNot(0)
            ->pluck('id')
            ->all();

        $memberIds = collect($activeIds)->push(Auth::id())->unique()->values()->all();

        $room = DB::transaction(function () use ($request, $memberIds) {
            $room = ChatRoom::create([
                'name'               => $request->name,
                'description'        => $request->description,
                'created_by_user_id' => Auth::id(),
                'type'               => 'channel',
            ]);
            $room->members()->attach($memberIds);
            return $room;
        });

        return redirect()->route('chat.show', $room);
    }

    public function openDirect(User $user)
    {
        abort_unless($user->active && $user->id !== 0, 404);
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

    public function addMember(Request $request, ChatRoom $room)
    {
        abort_unless($room->isChannel(), 403);
        $this->authorize('view', $room);

        $request->validate(['user_id' => 'required|integer|exists:users,id']);
        $user = User::findOrFail($request->user_id);
        abort_unless($user->active && $user->id !== 0, 422, 'User is not active.');

        if (!$room->members()->where('user_id', $user->id)->exists()) {
            DB::transaction(fn () => $room->members()->attach($user->id));
        }

        return redirect()->route('chat.show', $room);
    }

    public function removeMember(ChatRoom $room, User $user)
    {
        abort_unless($room->isChannel(), 403);
        $this->authorize('view', $room);  // any current member can manage the roster (symmetric with addMember)

        $auth   = Auth::user();
        $isSelf = $auth->id === $user->id;

        DB::transaction(fn () => $room->members()->detach($user->id));

        if ($isSelf) {
            return redirect()->route('chat.index')->with('success', "You left #{$room->name}.");
        }

        return redirect()->route('chat.show', $room);
    }

    /** Redirects to unified file route; access enforced there via ChatRoom context. */
    public function file(ChatRoom $room, ChatMessageFile $file)
    {
        abort_unless($file->message->room_id === $room->id, 403);
        abort_unless($file->path, 404);

        return redirect()->route('files.serve', $file->path);
    }

    private function sidebarData(): array
    {
        $auth = Auth::user();

        $channels = ChatRoom::where('active', true)
            ->where('type', 'channel')
            ->whereHas('members', fn ($q) => $q->where('user_id', $auth->id))
            ->with(['lastMessage.user', 'members'])
            ->orderBy('name')
            ->get();

        $dms = ChatRoom::where('active', true)
            ->whereIn('type', ['direct', 'group'])
            ->whereHas('members', fn ($q) => $q->where('user_id', $auth->id))
            ->with(['members', 'lastMessage.user'])
            ->get();

        $unreadCounts = $channels->merge($dms)
            ->mapWithKeys(fn ($r) => [$r->id => $r->unreadCountFor($auth)])
            ->toArray();

        $users = User::where('active', true)->whereKeyNot(0)->orderBy('name')->get();

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
