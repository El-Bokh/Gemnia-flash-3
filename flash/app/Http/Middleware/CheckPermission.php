<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckPermission
{
    /**
     * Check the authenticated user owns the given permission slug.
     *
     * Usage in routes: ->middleware('permission:view_users')
     *
     * Admin / super_admin roles bypass the check (they own all permissions).
     */
    public function handle(Request $request, Closure $next, string $permission): Response
    {
        $user = $request->user();

        if (! $user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated.',
            ], 401);
        }

        // Admin & super_admin bypass all permission checks
        $isAdmin = $user->roles()
            ->whereIn('slug', ['admin', 'super_admin'])
            ->exists();

        if ($isAdmin) {
            return $next($request);
        }

        if (! $user->hasPermission($permission)) {
            return response()->json([
                'success' => false,
                'message' => 'Forbidden. You do not have the required permission.',
            ], 403);
        }

        return $next($request);
    }
}
