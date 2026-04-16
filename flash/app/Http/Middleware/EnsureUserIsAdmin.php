<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsAdmin
{
    /**
     * Ensure the authenticated user has at least one admin-panel permission.
     *
     * Users with 'admin', 'super_admin', or any role that carries at least
     * one permission (e.g. 'support') are allowed through.
     * The plain 'user' role has zero permissions and is therefore blocked.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated.',
            ], 401);
        }

        // Super admins & admins always pass
        $isAdmin = $user->roles()
            ->whereIn('slug', ['admin', 'super_admin'])
            ->exists();

        if ($isAdmin) {
            return $next($request);
        }

        // Non-admin roles: allow if the user has at least one permission
        $hasAnyPermission = $user->roles()
            ->whereHas('permissions')
            ->exists();

        if (! $hasAnyPermission) {
            return response()->json([
                'success' => false,
                'message' => 'Forbidden. Admin access required.',
            ], 403);
        }

        return $next($request);
    }
}
