<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CreditLedger;
use App\Models\Plan;
use App\Models\Role;
use App\Models\Subscription;
use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;

class GoogleAuthController extends Controller
{
    /**
     * Redirect to Google OAuth consent screen.
     */
    public function redirect(): RedirectResponse|\Symfony\Component\HttpFoundation\RedirectResponse
    {
        return Socialite::driver('google')->stateless()->redirect();
    }

    /**
     * Handle Google OAuth callback.
     * Find or create user, issue Sanctum token, redirect to SPA.
     */
    public function callback(): RedirectResponse
    {
        try {
            $googleUser = Socialite::driver('google')->stateless()->user();
        } catch (\Exception $e) {
            Log::error('Google OAuth callback failed', ['error' => $e->getMessage()]);

            return redirect($this->spaUrl('/login?error=google_auth_failed'));
        }

        $user = User::where('google_id', $googleUser->getId())->first();

        if (! $user) {
            $user = User::where('email', Str::lower($googleUser->getEmail()))->first();
        }

        if ($user) {
            // Existing user — link Google ID if not already linked
            if (! $user->google_id) {
                $user->update([
                    'google_id' => $googleUser->getId(),
                    'provider'  => 'google',
                ]);
            }

            // Update avatar from Google if user has no avatar
            if (! $user->avatar && $googleUser->getAvatar()) {
                $user->update(['avatar' => $googleUser->getAvatar()]);
            }

            if ($user->status !== 'active') {
                return redirect($this->spaUrl('/login?error=account_inactive'));
            }
        } else {
            // New user — create account
            $user = DB::transaction(function () use ($googleUser) {
                $user = User::create([
                    'name'      => $googleUser->getName(),
                    'email'     => Str::lower($googleUser->getEmail()),
                    'google_id' => $googleUser->getId(),
                    'provider'  => 'google',
                    'avatar'    => $googleUser->getAvatar(),
                    'status'    => 'active',
                    'locale'    => app()->getLocale(),
                    'timezone'  => config('app.timezone', 'UTC'),
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
                    Log::critical('No active free plan found during Google registration', [
                        'user_id' => $user->id,
                    ]);
                }

                return $user->load('roles');
            });

            // Send notifications for new user
            $notificationService = app(NotificationService::class);
            $notificationService->sendWelcome($user);
            $notificationService->notifyNewUserRegistered($user);
        }

        // Issue Sanctum token
        $user->tokens()->where('name', 'web-session')->delete();
        $token = $user->createToken('web-session', ['*'])->plainTextToken;

        $user->update([
            'last_login_at' => now(),
            'last_login_ip' => request()->ip(),
        ]);

        // Redirect to SPA with token
        $userData = [
            'id'     => $user->id,
            'name'   => $user->name,
            'email'  => $user->email,
            'avatar' => $user->avatarUrl(),
            'roles'  => $user->roles->pluck('slug')->values()->all(),
        ];

        $params = http_build_query([
            'token' => $token,
            'user'  => base64_encode(json_encode($userData)),
        ]);

        return redirect($this->spaUrl("/auth/google/callback?{$params}"));
    }

    private function spaUrl(string $path): string
    {
        $baseUrl = rtrim(config('app.frontend_url', config('app.url')), '/');

        return $baseUrl . $path;
    }
}
