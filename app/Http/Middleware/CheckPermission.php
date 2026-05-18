<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckPermission
{
    public function handle(Request $request, Closure $next, string $permission): Response
    {
        $user = $request->user();

        if (!$user) {
            return $this->unauthorized($request, 'Unauthenticated.');
        }

        if (!$user->active) {
            return $this->unauthorized($request, 'Account is disabled.');
        }

        if (!$user->hasPermission($permission)) {
            return $this->forbidden($request, "Permission denied: {$permission}");
        }

        return $next($request);
    }

    private function unauthorized(Request $request, string $message): Response
    {
        if ($request->expectsJson()) {
            return response()->json(['message' => $message], 401);
        }
        return redirect()->route('login');
    }

    private function forbidden(Request $request, string $message): Response
    {
        if ($request->expectsJson()) {
            return response()->json(['message' => $message], 403);
        }
        abort(403, $message);
    }
}
