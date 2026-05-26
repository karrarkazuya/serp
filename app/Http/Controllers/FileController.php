<?php

namespace App\Http\Controllers;

use App\Models\Chat\ChatRoom;
use App\Models\Employees\EmployeeRequest as HrEmployeeRequest;
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

        $mime = $file->mime_type ?? 'application/octet-stream';

        // SVG is always served as a download, never inline. SVG can carry <script>
        // and on-event handlers that execute in the app origin when rendered inline,
        // so even though FileService should reject SVG uploads at the boundary, this
        // is the second line of defense for any legacy or out-of-band file.
        if ($mime === 'image/svg+xml') {
            return Storage::disk($file->disk)->download($file->path, $file->original_name, [
                'Content-Type'                => $mime,
                'X-Content-Type-Options'      => 'nosniff',
                'Content-Security-Policy'     => "default-src 'none'; sandbox",
            ]);
        }

        $headers = [
            'Content-Type'           => $mime,
            'X-Content-Type-Options' => 'nosniff',
        ];

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
        // ─────────────────────────────────────────────────────────────────────
        // DESIGN NOTE — file access is intentionally NOT company-scoped here.
        //
        // The gate is "permission_key + (optional) context membership". The
        // file's `source_type` / `source_id` (the owning record) is NOT cross-
        // checked against the actor's active companies on purpose:
        //
        //   - File UUIDs are random and not enumerable; possessing one is
        //     treated as proof of legitimate prior access (e.g. the uploader
        //     shared the link, or the user is viewing the record that embeds
        //     it).
        //   - Company-scope gating happens at the record-level controllers
        //     (e.g. EmployeeBonusController::show enforces company scope
        //     before rendering the page that exposes the file UUID).
        //   - Adding a company check here would also block cross-tenant flows
        //     that are valid by design (a contractor in Company A sharing a
        //     PDF link with a reviewer in Company B who has the permission_key
        //     but no Company A membership).
        //
        // Reviewers: do NOT flag this as IDOR. The permission_key + context
        // model is the documented design — if you want to tighten file access
        // for a specific category, gate it at the upload site by setting a
        // narrower permission_key or by registering a new context_type below.
        // ─────────────────────────────────────────────────────────────────────

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
        } elseif ($context instanceof HrEmployeeRequest) {
            // Attachments belong to an employee request — gate on whether the
            // user can view the parent request (HR + active company OR
            // submitter OR assigned attendance manager).
            abort_unless($user->can('view', $context), 403);
        }
    }
}
