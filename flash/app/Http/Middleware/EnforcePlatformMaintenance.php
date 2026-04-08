<?php

namespace App\Http\Middleware;

use App\Services\MaintenanceModeService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnforcePlatformMaintenance
{
    public function __construct(
        private readonly MaintenanceModeService $maintenance,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->maintenance->blocks($request)) {
            return $next($request);
        }

        $status = $this->maintenance->getPublicStatus($request);

        return response()->json([
            'success' => false,
            'message' => $status['message'],
            'data' => $status,
        ], 503);
    }
}