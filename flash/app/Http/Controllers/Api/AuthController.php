<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\RegisterRequest;
use App\Models\CreditLedger;
use App\Models\Plan;
use App\Models\Role;
use App\Models\Subscription;
use App\Models\User;
use App\Services\MaintenanceModeService;
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

    public function __construct(
        private readonly MaintenanceModeService $maintenance,
    ) {}

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
                $subscription = Subscription::create([
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

                // Record initial credits in ledger for audit consistency
                CreditLedger::create([
                    'user_id'         => $user->id,
                    'subscription_id' => $subscription->id,
                    'type'            => 'credit',
                    'amount'          => $freePlan->credits_monthly,
                    'balance_after'   => $freePlan->credits_monthly,
                    'source'          => 'subscription',
                    'reference_type'  => Subscription::class,
                    'reference_id'    => $subscription->id,
                    'description'     => "Initial credits from {$freePlan->name} plan (monthly)",
                ]);
            } else {
                \Illuminate\Support\Facades\Log::critical('No active free plan found during registration', [
                    'user_id' => $user->id,
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

        if ($this->maintenance->blocks($request, $user)) {
            $status = $this->maintenance->getPublicStatus($request, $user);

            return response()->json([
                'success' => false,
                'message' => $status['message'],
                'data' => $status,
            ], 503);
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
        $user->load('roles.permissions');

        $usageService = new UsageService();
        $quota = $usageService->getUsageStats($user);

        // Collect unique permission slugs across all user roles
        $permissions = $user->roles
            ->flatMap(fn ($role) => $role->permissions->pluck('slug'))
            ->unique()
            ->values()
            ->toArray();

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
                'permissions' => $permissions,
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

        $user->loadMissing('roles.permissions');

        // Collect unique permission slugs across all user roles
        $permissions = $user->roles
            ->flatMap(fn ($role) => $role->permissions->pluck('slug'))
            ->unique()
            ->values()
            ->toArray();

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
                    'permissions' => $permissions,
                ],
            ],
        ];

        if ($message !== null) {
            $response['message'] = $message;
        }

        return response()->json($response, $statusCode);
    }
}
