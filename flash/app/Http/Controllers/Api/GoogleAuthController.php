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
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;
use Throwable;

class GoogleAuthController extends Controller
{
    /**
     * Redirect to Google OAuth consent screen.
     */
    public function redirect(): RedirectResponse|\Symfony\Component\HttpFoundation\RedirectResponse
    {
        try {
            return $this->socialiteDriver()->redirect();
        } catch (Throwable $e) {
            $this->logGoogleAuthFailure('Google OAuth redirect failed', $e);

            return redirect($this->spaUrl('/oauth/callback?error=google_auth_failed'));
        }
    }

    /**
     * Handle Google OAuth callback.
     * Find or create user, issue Sanctum token, redirect to SPA.
     */
    public function callback(): RedirectResponse
    {
        try {
            $googleUser = $this->socialiteDriver()->user();
            $email = $this->normalizedGoogleEmail($googleUser);
        } catch (Throwable $e) {
            $this->logGoogleAuthFailure('Google OAuth callback failed', $e);

            return redirect($this->spaUrl('/oauth/callback?error=google_auth_failed'));
        }

        $user = User::where('google_id', $googleUser->getId())->first();

        if (! $user) {
            $user = User::where('email', $email)->first();
        }

        if ($user) {
            // Existing user — link Google ID if not already linked
            $updates = [];

            if (! $user->google_id) {
                $updates['google_id'] = $googleUser->getId();
                $updates['provider'] = 'google';
            }

            if (! $user->avatar && $googleUser->getAvatar()) {
                $updates['avatar'] = $googleUser->getAvatar();
            }

            if (! $user->email_verified_at && $this->googleEmailIsVerified($googleUser)) {
                $updates['email_verified_at'] = now();
            }

            if ($updates !== []) {
                $user->forceFill($updates)->save();
            }

            if ($user->status !== 'active') {
                return redirect($this->spaUrl('/oauth/callback?error=account_inactive'));
            }
        } else {
            // New user — create account
            $user = DB::transaction(function () use ($googleUser, $email) {
                $user = new User([
                    'name'      => $this->googleDisplayName($googleUser, $email),
                    'email'     => $email,
                    'password'  => Hash::make(Str::random(40)),
                    'google_id' => $googleUser->getId(),
                    'provider'  => 'google',
                    'avatar'    => $googleUser->getAvatar(),
                    'status'    => 'active',
                    'locale'    => app()->getLocale(),
                    'timezone'  => config('app.timezone', 'UTC'),
                ]);

                if ($this->googleEmailIsVerified($googleUser)) {
                    $user->email_verified_at = now();
                }

                $user->save();

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

        return redirect($this->spaUrl("/oauth/callback?{$params}"));
    }

    private function spaUrl(string $path): string
    {
        $baseUrl = rtrim(config('app.frontend_url', config('app.url')), '/');

        return $baseUrl . $path;
    }

    private function socialiteDriver(): \Laravel\Socialite\Contracts\Provider
    {
        if (! class_exists(Socialite::class)) {
            throw new \RuntimeException(
                'Laravel Socialite facade is unavailable. Run composer install and refresh Laravel caches on the deployed backend.'
            );
        }

        return Socialite::driver('google')->stateless();
    }

    private function logGoogleAuthFailure(string $message, Throwable $e): void
    {
        Log::error($message, [
            'error' => $e->getMessage(),
            'socialite_facade_available' => class_exists(Socialite::class),
            'google_client_id_configured' => filled(config('services.google.client_id')),
            'google_client_secret_configured' => filled(config('services.google.client_secret')),
            'google_redirect_configured' => filled(config('services.google.redirect')),
        ]);
    }

    private function normalizedGoogleEmail(\Laravel\Socialite\Contracts\User $googleUser): string
    {
        $email = Str::lower(trim((string) $googleUser->getEmail()));

        if (blank($email)) {
            throw new \UnexpectedValueException('Google account did not return an email address.');
        }

        return $email;
    }

    private function googleDisplayName(\Laravel\Socialite\Contracts\User $googleUser, string $email): string
    {
        $name = trim((string) ($googleUser->getName() ?: $googleUser->getNickname() ?: ''));

        if ($name !== '') {
            return $name;
        }

        return Str::headline(Str::before($email, '@'));
    }

    private function googleEmailIsVerified(\Laravel\Socialite\Contracts\User $googleUser): bool
    {
        return (bool) ($googleUser->getRaw()['email_verified'] ?? false);
    }
}
