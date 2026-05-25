<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Allow the request if the user holds ANY of the listed permissions.
 * Used on routes shared by HR + self-service flows where either side has
 * a different permission key.
 */
class CheckPermissionAny
{
    public function handle(Request $request, Closure $next, string ...$permissions): Response
    {
        $user = $request->user();
        if (!$user) {
            return $request->expectsJson()
                ? response()->json(['message' => 'Unauthenticated.'], 401)
                : redirect()->route('login');
        }
        if (!$user->active) {
            abort($request->expectsJson() ? 401 : 403, 'Account is disabled.');
        }
        foreach ($permissions as $perm) {
            if ($user->hasPermission($perm)) return $next($request);
        }
        $message = 'Permission denied: requires any of ' . implode(', ', $permissions);
        if ($request->expectsJson()) {
            return response()->json(['message' => $message], 403);
        }
        abort(403, $message);
    }
}
