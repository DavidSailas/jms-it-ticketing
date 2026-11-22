<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Lets a request through when the signed-in person's role grants at least one of the listed permissions. */
class PermissionMiddleware
{
    public function handle(Request $request, Closure $next, string ...$permissions): Response
    {
        $user = $request->user();

        if (! $user || ! collect($permissions)->contains(fn ($p) => $user->hasPermission($p))) {
            abort(403, 'You do not have permission to access this page.');
        }

        return $next($request);
    }
}
