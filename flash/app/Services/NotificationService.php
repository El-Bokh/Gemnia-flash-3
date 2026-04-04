<?php

namespace App\Services;

use App\Models\Notification;
use App\Models\User;
use Illuminate\Support\Facades\Log;

class NotificationService
{
    // ──────────────────────────────────────────────
    //  Notification Types
    // ──────────────────────────────────────────────

    // Admin notification types
    public const TYPE_ADMIN_NEW_USER         = 'admin_new_user';
    public const TYPE_ADMIN_NEW_SUBSCRIPTION = 'admin_new_subscription';
    public const TYPE_ADMIN_PLAN_UPGRADE     = 'admin_plan_upgrade';
    public const TYPE_ADMIN_PAYMENT_RECEIVED = 'admin_payment_received';
    public const TYPE_ADMIN_API_LIMIT_NEAR   = 'admin_api_limit_near';
    public const TYPE_ADMIN_API_HIGH_USAGE   = 'admin_api_high_usage';
    public const TYPE_ADMIN_SUSPICIOUS       = 'admin_suspicious_activity';

    // User notification types
    public const TYPE_WELCOME                = 'welcome';
    public const TYPE_SUBSCRIPTION_ACTIVATED = 'subscription_activated';
    public const TYPE_SUBSCRIPTION_UPGRADED  = 'subscription_upgraded';
    public const TYPE_SUBSCRIPTION_EXPIRING  = 'subscription_expiring';
    public const TYPE_SUBSCRIPTION_EXPIRED   = 'subscription_expired';
    public const TYPE_PAYMENT_SUCCESS        = 'payment_success';

    // Categories for filtering
    public const CATEGORY_INFO    = 'info';
    public const CATEGORY_WARNING = 'warning';
    public const CATEGORY_ALERT   = 'alert';

    // ──────────────────────────────────────────────
    //  Core: create notification
    // ──────────────────────────────────────────────

    public function create(
        int     $userId,
        string  $type,
        string  $title,
        string  $body,
        array   $data = [],
        ?string $icon = null,
        ?string $actionUrl = null,
        string  $priority = 'normal',
        string  $channel = 'in_app',
    ): Notification {
        return Notification::create([
            'user_id'    => $userId,
            'type'       => $type,
            'title'      => $title,
            'body'       => $body,
            'icon'       => $icon,
            'action_url' => $actionUrl,
            'channel'    => $channel,
            'priority'   => $priority,
            'is_read'    => false,
            'sent_at'    => now(),
            'data'       => $data,
        ]);
    }

    // ──────────────────────────────────────────────
    //  Notify all admins
    // ──────────────────────────────────────────────

    public function notifyAdmins(
        string  $type,
        string  $title,
        string  $body,
        array   $data = [],
        ?string $icon = null,
        ?string $actionUrl = null,
        string  $priority = 'normal',
    ): void {
        $adminIds = User::whereHas('roles', function ($q) {
            $q->where('slug', 'admin')->orWhere('slug', 'super-admin');
        })->pluck('id');

        foreach ($adminIds as $adminId) {
            $this->create($adminId, $type, $title, $body, $data, $icon, $actionUrl, $priority);
        }
    }

    // ──────────────────────────────────────────────
    //  Admin Notifications
    // ──────────────────────────────────────────────

    public function notifyNewUserRegistered(User $user): void
    {
        $this->notifyAdmins(
            type: self::TYPE_ADMIN_NEW_USER,
            title: 'New User Registered',
            body: "{$user->name} ({$user->email}) has just registered on the platform.",
            data: [
                'user_id'    => $user->id,
                'user_name'  => $user->name,
                'user_email' => $user->email,
                'category'   => self::CATEGORY_INFO,
            ],
            icon: 'user-plus',
            actionUrl: "/admin/users/{$user->id}",
        );
    }

    public function notifyNewSubscription(User $user, string $planName, string $cycle): void
    {
        $this->notifyAdmins(
            type: self::TYPE_ADMIN_NEW_SUBSCRIPTION,
            title: 'New Subscription',
            body: "{$user->name} subscribed to the {$planName} plan ({$cycle}).",
            data: [
                'user_id'   => $user->id,
                'user_name' => $user->name,
                'plan_name' => $planName,
                'cycle'     => $cycle,
                'category'  => self::CATEGORY_INFO,
            ],
            icon: 'credit-card',
            actionUrl: "/admin/users/{$user->id}",
        );
    }

    public function notifyPlanUpgrade(User $user, string $oldPlan, string $newPlan): void
    {
        $this->notifyAdmins(
            type: self::TYPE_ADMIN_PLAN_UPGRADE,
            title: 'Plan Upgraded',
            body: "{$user->name} upgraded from {$oldPlan} to {$newPlan}.",
            data: [
                'user_id'   => $user->id,
                'user_name' => $user->name,
                'old_plan'  => $oldPlan,
                'new_plan'  => $newPlan,
                'category'  => self::CATEGORY_INFO,
            ],
            icon: 'arrow-up',
            actionUrl: "/admin/users/{$user->id}",
        );
    }

    public function notifyPaymentReceived(User $user, float $amount, string $currency, string $gateway): void
    {
        $formatted = number_format($amount, 2) . ' ' . strtoupper($currency);

        $this->notifyAdmins(
            type: self::TYPE_ADMIN_PAYMENT_RECEIVED,
            title: 'Payment Received',
            body: "{$user->name} made a payment of {$formatted} via {$gateway}.",
            data: [
                'user_id'   => $user->id,
                'user_name' => $user->name,
                'amount'    => $amount,
                'currency'  => $currency,
                'gateway'   => $gateway,
                'category'  => self::CATEGORY_INFO,
            ],
            icon: 'wallet',
            actionUrl: "/admin/payments",
        );
    }

    public function notifyApiLimitApproaching(User $user, string $featureName, int $used, int $limit): void
    {
        $percentage = $limit > 0 ? round(($used / $limit) * 100) : 0;

        $this->notifyAdmins(
            type: self::TYPE_ADMIN_API_LIMIT_NEAR,
            title: 'API Limit Approaching',
            body: "{$user->name} has used {$percentage}% of their {$featureName} limit ({$used}/{$limit}).",
            data: [
                'user_id'      => $user->id,
                'user_name'    => $user->name,
                'feature_name' => $featureName,
                'used'         => $used,
                'limit'        => $limit,
                'percentage'   => $percentage,
                'category'     => self::CATEGORY_WARNING,
            ],
            icon: 'exclamation-triangle',
            priority: 'high',
        );
    }

    public function notifyHighApiUsage(User $user, int $requestsInHour): void
    {
        $this->notifyAdmins(
            type: self::TYPE_ADMIN_API_HIGH_USAGE,
            title: 'High API Usage Detected',
            body: "{$user->name} made {$requestsInHour} API requests in the last hour.",
            data: [
                'user_id'          => $user->id,
                'user_name'        => $user->name,
                'requests_in_hour' => $requestsInHour,
                'category'         => self::CATEGORY_ALERT,
            ],
            icon: 'bolt',
            priority: 'high',
        );
    }

    public function notifySuspiciousActivity(User $user, string $reason): void
    {
        $this->notifyAdmins(
            type: self::TYPE_ADMIN_SUSPICIOUS,
            title: 'Suspicious Activity',
            body: "Suspicious activity detected for {$user->name}: {$reason}.",
            data: [
                'user_id'   => $user->id,
                'user_name' => $user->name,
                'reason'    => $reason,
                'category'  => self::CATEGORY_ALERT,
            ],
            icon: 'shield',
            actionUrl: "/admin/users/{$user->id}",
            priority: 'high',
        );
    }

    // ──────────────────────────────────────────────
    //  User Notifications
    // ──────────────────────────────────────────────

    public function sendWelcome(User $user): void
    {
        $this->create(
            userId: $user->id,
            type: self::TYPE_WELCOME,
            title: 'Welcome to Klek AI! 🎉',
            body: "Hi {$user->name}! Your account has been created successfully. Start exploring and creating amazing AI-generated images.",
            data: ['category' => self::CATEGORY_INFO],
            icon: 'sparkles',
            actionUrl: '/',
        );
    }

    public function sendSubscriptionActivated(User $user, string $planName, string $cycle): void
    {
        $this->create(
            userId: $user->id,
            type: self::TYPE_SUBSCRIPTION_ACTIVATED,
            title: 'Subscription Activated',
            body: "Your {$planName} ({$cycle}) subscription is now active. Enjoy all the features!",
            data: [
                'plan_name' => $planName,
                'cycle'     => $cycle,
                'category'  => self::CATEGORY_INFO,
            ],
            icon: 'check-circle',
        );
    }

    public function sendSubscriptionUpgraded(User $user, string $oldPlan, string $newPlan): void
    {
        $this->create(
            userId: $user->id,
            type: self::TYPE_SUBSCRIPTION_UPGRADED,
            title: 'Plan Upgraded Successfully',
            body: "Your plan has been upgraded from {$oldPlan} to {$newPlan}. New features are now available!",
            data: [
                'old_plan' => $oldPlan,
                'new_plan' => $newPlan,
                'category' => self::CATEGORY_INFO,
            ],
            icon: 'arrow-up',
        );
    }

    public function sendSubscriptionExpiring(User $user, string $planName, int $daysLeft): void
    {
        $this->create(
            userId: $user->id,
            type: self::TYPE_SUBSCRIPTION_EXPIRING,
            title: 'Subscription Expiring Soon',
            body: "Your {$planName} subscription will expire in {$daysLeft} days. Renew now to keep your access.",
            data: [
                'plan_name' => $planName,
                'days_left' => $daysLeft,
                'category'  => self::CATEGORY_WARNING,
            ],
            icon: 'clock',
            priority: 'high',
        );
    }

    public function sendSubscriptionExpired(User $user, string $planName): void
    {
        $this->create(
            userId: $user->id,
            type: self::TYPE_SUBSCRIPTION_EXPIRED,
            title: 'Subscription Expired',
            body: "Your {$planName} subscription has expired. Subscribe again to continue using premium features.",
            data: [
                'plan_name' => $planName,
                'category'  => self::CATEGORY_ALERT,
            ],
            icon: 'exclamation-circle',
            priority: 'high',
        );
    }

    public function sendPaymentSuccess(User $user, float $amount, string $currency): void
    {
        $formatted = number_format($amount, 2) . ' ' . strtoupper($currency);

        $this->create(
            userId: $user->id,
            type: self::TYPE_PAYMENT_SUCCESS,
            title: 'Payment Successful',
            body: "Your payment of {$formatted} has been processed successfully.",
            data: [
                'amount'   => $amount,
                'currency' => $currency,
                'category' => self::CATEGORY_INFO,
            ],
            icon: 'check-circle',
        );
    }
}
