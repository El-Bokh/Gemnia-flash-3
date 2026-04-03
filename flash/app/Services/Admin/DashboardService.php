<?php

namespace App\Services\Admin;

use App\Models\AiRequest;
use App\Models\GeneratedImage;
use App\Models\Notification;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class DashboardService
{
    // ──────────────────────────────────────────────
    //  KPIs
    // ──────────────────────────────────────────────

    public function getKpis(): array
    {
        $today = Carbon::today();
        $weekStart = Carbon::now()->startOfWeek();

        return [
            'users'                    => $this->getUserKpis(),
            'subscriptions_per_plan'   => $this->getSubscriptionsPerPlan(),
            'images_generated_today'   => $this->countImagesGenerated($today),
            'images_generated_week'    => $this->countImagesGenerated($weekStart),
            'revenue_today'            => $this->getRevenue($today),
            'revenue_week'             => $this->getRevenue($weekStart),
            'ai_requests_pending'      => $this->countAiRequestsByStatus('pending'),
            'ai_requests_completed'    => $this->countAiRequestsByStatus('completed'),
            'ai_requests_failed'       => $this->countAiRequestsByStatus('failed'),
        ];
    }

    private function getUserKpis(): array
    {
        return [
            'total'       => User::count(),
            'active'      => User::where('status', 'active')->count(),
            'suspended'   => User::where('status', 'suspended')->count(),
            'pending'     => User::where('status', 'pending')->count(),
            'new_today'   => User::whereDate('created_at', Carbon::today())->count(),
            'new_week'    => User::where('created_at', '>=', Carbon::now()->startOfWeek())->count(),
        ];
    }

    private function getSubscriptionsPerPlan(): array
    {
        return Plan::query()
            ->select('plans.id', 'plans.name', 'plans.slug')
            ->withCount(['subscriptions as active_count' => function ($q) {
                $q->where('status', 'active');
            }])
            ->withCount(['subscriptions as trial_count' => function ($q) {
                $q->where('status', 'trialing');
            }])
            ->withCount('subscriptions as total_count')
            ->orderBy('sort_order')
            ->get()
            ->toArray();
    }

    private function countImagesGenerated(Carbon $since): int
    {
        return GeneratedImage::where('created_at', '>=', $since)->count();
    }

    private function getRevenue(Carbon $since): array
    {
        $row = Payment::where('status', 'completed')
            ->where('paid_at', '>=', $since)
            ->selectRaw('COALESCE(SUM(net_amount), 0) as total, COUNT(*) as count')
            ->first();

        return [
            'total'    => (float) $row->total,
            'count'    => (int) $row->count,
            'currency' => 'USD',
        ];
    }

    private function countAiRequestsByStatus(string $status): int
    {
        return AiRequest::where('status', $status)->count();
    }

    // ──────────────────────────────────────────────
    //  Charts
    // ──────────────────────────────────────────────

    public function getCharts(): array
    {
        return [
            'subscriptions_by_plan'   => $this->subscriptionsPieChart(),
            'images_last_7_days'      => $this->imagesLineChart(),
            'ai_requests_by_status'   => $this->aiRequestsBarChart(),
        ];
    }

    private function subscriptionsPieChart(): array
    {
        return Plan::query()
            ->select('plans.id', 'plans.name')
            ->withCount(['subscriptions as count' => function ($q) {
                $q->where('status', 'active');
            }])
            ->orderBy('sort_order')
            ->get()
            ->map(fn ($p) => [
                'label' => $p->name,
                'value' => $p->count,
            ])
            ->toArray();
    }

    private function imagesLineChart(): array
    {
        $days = collect(range(6, 0))->map(fn ($i) => Carbon::today()->subDays($i));

        $counts = GeneratedImage::query()
            ->where('created_at', '>=', Carbon::today()->subDays(6)->startOfDay())
            ->select(DB::raw('DATE(created_at) as date'), DB::raw('COUNT(*) as count'))
            ->groupBy('date')
            ->pluck('count', 'date');

        return $days->map(fn (Carbon $day) => [
            'date'  => $day->toDateString(),
            'label' => $day->format('D'),
            'count' => (int) ($counts[$day->toDateString()] ?? 0),
        ])->toArray();
    }

    private function aiRequestsBarChart(): array
    {
        return AiRequest::query()
            ->select('status', DB::raw('COUNT(*) as count'))
            ->whereIn('status', ['pending', 'processing', 'completed', 'failed'])
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();
    }

    // ──────────────────────────────────────────────
    //  Recent Activity
    // ──────────────────────────────────────────────

    public function getRecentAiRequests(int $limit = 10): array
    {
        return AiRequest::with(['user:id,name,email,avatar', 'visualStyle:id,name'])
            ->select([
                'id', 'uuid', 'user_id', 'visual_style_id',
                'type', 'status', 'user_prompt', 'model_used',
                'credits_consumed', 'processing_time_ms',
                'created_at',
            ])
            ->latest()
            ->limit($limit)
            ->get()
            ->map(fn (AiRequest $r) => [
                'id'               => $r->id,
                'uuid'             => $r->uuid,
                'user'             => $r->user ? [
                    'id'     => $r->user->id,
                    'name'   => $r->user->name,
                    'email'  => $r->user->email,
                    'avatar' => $r->user->avatar,
                ] : null,
                'prompt'           => $r->user_prompt,
                'style'            => $r->visualStyle?->name,
                'type'             => $r->type,
                'status'           => $r->status,
                'model'            => $r->model_used,
                'credits'          => $r->credits_consumed,
                'processing_ms'    => $r->processing_time_ms,
                'date'             => $r->created_at->toIso8601String(),
            ])
            ->toArray();
    }

    public function getRecentPayments(int $limit = 10): array
    {
        return Payment::with([
            'user:id,name,email,avatar',
            'subscription.plan:id,name,slug',
        ])
            ->select([
                'id', 'uuid', 'user_id', 'subscription_id',
                'status', 'amount', 'net_amount', 'currency',
                'payment_method', 'paid_at', 'created_at',
            ])
            ->latest()
            ->limit($limit)
            ->get()
            ->map(fn (Payment $p) => [
                'id'             => $p->id,
                'uuid'           => $p->uuid,
                'user'           => $p->user ? [
                    'id'     => $p->user->id,
                    'name'   => $p->user->name,
                    'email'  => $p->user->email,
                    'avatar' => $p->user->avatar,
                ] : null,
                'plan'           => $p->subscription?->plan?->name,
                'amount'         => (float) $p->amount,
                'net_amount'     => (float) $p->net_amount,
                'currency'       => $p->currency,
                'payment_method' => $p->payment_method,
                'status'         => $p->status,
                'paid_at'        => $p->paid_at?->toIso8601String(),
                'date'           => $p->created_at->toIso8601String(),
            ])
            ->toArray();
    }

    // ──────────────────────────────────────────────
    //  Admin Notifications / Alerts
    // ──────────────────────────────────────────────

    public function getAdminAlerts(): array
    {
        return [
            'failed_requests'   => $this->getFailedRequestAlerts(),
            'pending_payments'  => $this->getPendingPaymentAlerts(),
            'system'            => $this->getSystemAlerts(),
        ];
    }

    private function getFailedRequestAlerts(): array
    {
        $failedToday = AiRequest::where('status', 'failed')
            ->whereDate('created_at', Carbon::today())
            ->count();

        $failedRecent = AiRequest::where('status', 'failed')
            ->with('user:id,name,email')
            ->latest()
            ->limit(5)
            ->get()
            ->map(fn (AiRequest $r) => [
                'id'      => $r->id,
                'user'    => $r->user?->name,
                'error'   => $r->error_message,
                'date'    => $r->created_at->toIso8601String(),
            ])
            ->toArray();

        return [
            'count_today' => $failedToday,
            'recent'      => $failedRecent,
        ];
    }

    private function getPendingPaymentAlerts(): array
    {
        $pending = Payment::whereIn('status', ['pending', 'failed'])
            ->with('user:id,name,email')
            ->latest()
            ->limit(5)
            ->get()
            ->map(fn (Payment $p) => [
                'id'       => $p->id,
                'user'     => $p->user?->name,
                'amount'   => (float) $p->amount,
                'currency' => $p->currency,
                'status'   => $p->status,
                'date'     => $p->created_at->toIso8601String(),
            ])
            ->toArray();

        return [
            'count' => Payment::whereIn('status', ['pending', 'failed'])->count(),
            'recent' => $pending,
        ];
    }

    private function getSystemAlerts(): array
    {
        $lowCreditUsers = Subscription::where('status', 'active')
            ->where('credits_remaining', '<=', 5)
            ->where('credits_remaining', '>', 0)
            ->count();

        $expiringSoon = Subscription::where('status', 'active')
            ->whereBetween('ends_at', [Carbon::now(), Carbon::now()->addDays(3)])
            ->count();

        return [
            'low_credit_users'       => $lowCreditUsers,
            'subscriptions_expiring' => $expiringSoon,
        ];
    }

    // ──────────────────────────────────────────────
    //  Full overview (single endpoint)
    // ──────────────────────────────────────────────

    public function getFullOverview(): array
    {
        return [
            'kpis'              => $this->getKpis(),
            'charts'            => $this->getCharts(),
            'recent_ai_requests'=> $this->getRecentAiRequests(),
            'recent_payments'   => $this->getRecentPayments(),
            'alerts'            => $this->getAdminAlerts(),
        ];
    }
}
