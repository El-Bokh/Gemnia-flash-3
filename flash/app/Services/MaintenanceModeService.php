<?php

namespace App\Services;

use App\Models\Setting;
use App\Models\User;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class MaintenanceModeService
{
    private const DEFAULT_MESSAGE = 'The platform is currently under maintenance. Please try again later.';

    public function getStatus(): array
    {
        return [
            'is_enabled' => (bool) Setting::getValue('maintenance_mode', false),
            'message' => $this->normalizeMessage(Setting::getValue('maintenance_message', self::DEFAULT_MESSAGE)),
            'allowed_ips' => $this->normalizeAllowedIps(Setting::getValue('maintenance_allowed_ips', [])),
        ];
    }

    public function getPublicStatus(Request $request, ?Authenticatable $user = null): array
    {
        $status = $this->getStatus();

        return [
            'is_enabled' => $status['is_enabled'],
            'message' => $status['message'],
            'can_bypass' => $status['is_enabled'] ? $this->canBypass($request, $user, $status) : false,
        ];
    }

    public function blocks(Request $request, ?Authenticatable $user = null): bool
    {
        $status = $this->getStatus();

        return $status['is_enabled'] && ! $this->canBypass($request, $user, $status);
    }

    public function canBypass(Request $request, ?Authenticatable $user = null, ?array $status = null): bool
    {
        $status ??= $this->getStatus();

        if ($this->ipIsAllowed($request, $status['allowed_ips'] ?? [])) {
            return true;
        }

        $user ??= $request->user() ?? Auth::guard('sanctum')->user();

        return $user instanceof User && $this->userIsAdmin($user);
    }

    private function ipIsAllowed(Request $request, array $allowedIps): bool
    {
        $requestIp = $request->ip();

        return $requestIp !== null && in_array($requestIp, $allowedIps, true);
    }

    private function userIsAdmin(User $user): bool
    {
        if ($user->relationLoaded('roles')) {
            return $user->roles->contains(fn ($role) => in_array($role->slug, ['admin', 'super_admin'], true));
        }

        return $user->isAdmin();
    }

    private function normalizeMessage(mixed $message): string
    {
        $message = is_string($message) ? trim($message) : '';

        return $message !== '' ? $message : self::DEFAULT_MESSAGE;
    }

    private function normalizeAllowedIps(mixed $allowedIps): array
    {
        if (is_string($allowedIps)) {
            $decoded = json_decode($allowedIps, true);

            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                $allowedIps = $decoded;
            } else {
                $allowedIps = preg_split('/\s*,\s*/', trim($allowedIps), -1, PREG_SPLIT_NO_EMPTY);
            }
        }

        if (! is_array($allowedIps)) {
            return [];
        }

        return collect($allowedIps)
            ->filter(fn ($ip) => is_string($ip))
            ->map(fn (string $ip) => trim($ip))
            ->filter(fn (string $ip) => filter_var($ip, FILTER_VALIDATE_IP) !== false)
            ->values()
            ->all();
    }
}