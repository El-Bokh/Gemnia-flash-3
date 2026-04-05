<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\RegisterRequest;
use App\Models\Plan;
use App\Models\Role;
use App\Models\Subscription;
use App\Models\User;
use App\Services\NotificationService;
use App\Services\UsageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    private const TOKEN_NAME = 'web-session';

    /**
     * POST /api/auth/register
     *
     * Create a public account and immediately issue a Sanctum token.
     */
    public function register(RegisterRequest $request): JsonResponse
    {
        $data = $request->validated();

        $user = DB::transaction(function () use ($data) {
            $user = User::create([
                'name'     => $data['name'],
                'email'    => Str::lower(trim($data['email'])),
                'password' => Hash::make($data['password']),
                'status'   => 'active',
                'locale'   => $data['locale'] ?? app()->getLocale(),
                'timezone' => $data['timezone'] ?? config('app.timezone', 'UTC'),
            ]);

            $defaultRole = Role::query()->where('is_default', true)->first();

            if ($defaultRole) {
                $user->roles()->attach($defaultRole->id);
            }

            // Auto-subscribe to the Free plan
            $freePlan = Plan::where('is_free', true)->where('is_active', true)->first();
            if ($freePlan) {
                Subscription::create([
                    'user_id'           => $user->id,
                    'plan_id'           => $freePlan->id,
                    'billing_cycle'     => 'monthly',
                    'status'            => 'active',
                    'price'             => 0,
                    'currency'          => $freePlan->currency ?? 'USD',
                    'starts_at'         => now(),
                    'ends_at'           => now()->addMonth(),
                    'credits_remaining' => $freePlan->credits_monthly,
                    'credits_total'     => $freePlan->credits_monthly,
                    'auto_renew'        => true,
                ]);
            }

            return $user->load('roles');
        });

        // Send notifications
        $notificationService = app(NotificationService::class);
        $notificationService->sendWelcome($user);
        $notificationService->notifyNewUserRegistered($user);

        return $this->authenticatedResponse(
            $user,
            $request,
            201,
            'Account created successfully.'
        );
    }

    /**
     * POST /api/auth/login
     *
     * Issue a Sanctum token for API usage.
     */
    public function login(Request $request): JsonResponse
    {
        $request->validate([
            'email'    => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $email = Str::lower(trim((string) $request->input('email')));

        $user = User::where('email', $email)->first();

        if (! $user || ! Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        if ($user->status !== 'active') {
            return response()->json([
                'success' => false,
                'message' => 'Account is not active.',
            ], 403);
        }

        return $this->authenticatedResponse($user, $request);
    }

    /**
     * POST /api/auth/logout
     *
     * Revoke the current token.
     */
    public function logout(Request $request): JsonResponse
    {
        $user = $request->user();

        $token = $user?->currentAccessToken();

        if ($token) {
            $token->delete();
        } else {
            $user?->tokens()->delete();
        }

        return response()->json([
            'success' => true,
            'message' => 'Logged out successfully.',
        ]);
    }

    /**
     * GET /api/auth/me
     *
     * Return the authenticated user profile.
     */
    public function me(Request $request): JsonResponse
    {
        $user = $request->user();
        $user->load('roles');

        $usageService = new UsageService();
        $quota = $usageService->getUsageStats($user);

        return response()->json([
            'success' => true,
            'data'    => [
                'id'     => $user->id,
                'name'   => $user->name,
                'email'  => $user->email,
                'phone'  => $user->phone,
                'avatar' => $user->avatarUrl(),
                'status' => $user->status,
                'roles'  => $user->roles->pluck('slug')->toArray(),
                'locale' => $user->locale,
                'timezone' => $user->timezone,
                'last_login_at' => $user->last_login_at?->toIso8601String(),
                'quota'  => $quota,
            ],
        ]);
    }

    private function authenticatedResponse(
        User $user,
        Request $request,
        int $statusCode = 200,
        ?string $message = null,
    ): JsonResponse {
        $user->tokens()
            ->where('name', self::TOKEN_NAME)
            ->delete();

        $token = $user->createToken(self::TOKEN_NAME, ['*'])->plainTextToken;

        $user->update([
            'last_login_at' => now(),
            'last_login_ip' => $request->ip(),
        ]);

        $user->loadMissing('roles');

        $response = [
            'success' => true,
            'data'    => [
                'token' => $token,
                'user'  => [
                    'id'     => $user->id,
                    'name'   => $user->name,
                    'email'  => $user->email,
                    'avatar' => $user->avatarUrl(),
                    'roles'  => $user->roles->pluck('slug')->values()->all(),
                ],
            ],
        ];

        if ($message !== null) {
            $response['message'] = $message;
        }

        return response()->json($response, $statusCode);
    }
}
