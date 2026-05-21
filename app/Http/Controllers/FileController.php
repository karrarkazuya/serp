<?php

namespace App\Http\Controllers;

use App\Models\Chat\ChatRoom;
use App\Models\File;
use App\Models\User;
use App\Models\Workflow\Ticket;
use Illuminate\Support\Facades\Storage;

class FileController extends Controller
{
    public function serve(string $uuid)
    {
        $file = File::where('uuid', $uuid)->firstOrFail();
        $user = auth()->user();

        $this->checkAccess($user, $file);

        abort_unless(Storage::disk($file->disk)->exists($file->path), 404);

        $headers = ['Content-Type' => $file->mime_type ?? 'application/octet-stream'];

        if ($file->isImage()) {
            return Storage::disk($file->disk)->response(
                $file->path,
                $file->original_name,
                array_merge($headers, ['Cache-Control' => 'private, max-age=3600'])
            );
        }

        return Storage::disk($file->disk)->download($file->path, $file->original_name, $headers);
    }

    public function thumbnail(string $uuid)
    {
        $file = File::where('uuid', $uuid)->firstOrFail();
        $user = auth()->user();

        $this->checkAccess($user, $file);

        abort_unless(
            $file->thumbnail_path && Storage::disk($file->disk)->exists($file->thumbnail_path),
            404
        );

        return Storage::disk($file->disk)->response(
            $file->thumbnail_path,
            null,
            ['Content-Type' => 'image/jpeg', 'Cache-Control' => 'private, max-age=3600']
        );
    }

    private function checkAccess(User $user, File $file): void
    {
        // Permission gate — null means context-only (e.g. chat room membership)
        if ($file->permission_key !== null) {
            abort_unless($user->hasPermission($file->permission_key), 403);
        }

        if (!$file->context_type || !$file->context_id) {
            return;
        }

        $context = $file->context;
        abort_unless($context, 403);

        if ($context instanceof Ticket) {
            // User must pass the ticket's forUser scope (viewer or assignee)
            abort_unless(Ticket::where('id', $context->id)->forUser($user)->exists(), 403);
        } elseif ($context instanceof ChatRoom) {
            // User must be a member of the chat room
            abort_unless($context->members()->where('user_id', $user->id)->exists(), 403);
        }
    }
}
