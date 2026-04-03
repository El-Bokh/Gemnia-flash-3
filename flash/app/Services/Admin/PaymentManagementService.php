<?php

namespace App\Services\Admin;

use App\Models\Payment;
use App\Models\UsageLog;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class PaymentManagementService
{
    // ──────────────────────────────────────────────
    //  LIST (paginated with filters)
    // ──────────────────────────────────────────────

    public function list(array $filters = []): LengthAwarePaginator
    {
        $query = Payment::query()
            ->with([
                'user:id,name,email,avatar',
                'subscription:id,plan_id,status,billing_cycle',
                'subscription.plan:id,name,slug',
                'coupon:id,code,name,discount_type,discount_value',
            ])
            ->withCount('invoices');

        // ── Trashed ──
        if (! empty($filters['trashed'])) {
            $filters['trashed'] === 'only'
                ? $query->onlyTrashed()
                : $query->withTrashed();
        }

        // ── Search (uuid, gateway_payment_id, billing_name, billing_email, user name/email) ──
        if (! empty($filters['search'])) {
            $term = $filters['search'];
            $query->where(function (Builder $q) use ($term) {
                $q->where('uuid', 'LIKE', "%{$term}%")
                  ->orWhere('gateway_payment_id', 'LIKE', "%{$term}%")
                  ->orWhere('gateway_customer_id', 'LIKE', "%{$term}%")
                  ->orWhere('billing_name', 'LIKE', "%{$term}%")
                  ->orWhere('billing_email', 'LIKE', "%{$term}%")
                  ->orWhere('description', 'LIKE', "%{$term}%")
                  ->orWhereHas('user', function (Builder $uq) use ($term) {
                      $uq->where('name', 'LIKE', "%{$term}%")
                         ->orWhere('email', 'LIKE', "%{$term}%");
                  });
            });
        }

        // ── Exact filters ──
        if (! empty($filters['user_id'])) {
            $query->where('user_id', $filters['user_id']);
        }
        if (! empty($filters['subscription_id'])) {
            $query->where('subscription_id', $filters['subscription_id']);
        }
        if (! empty($filters['coupon_id'])) {
            $query->where('coupon_id', $filters['coupon_id']);
        }
        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        if (! empty($filters['payment_gateway'])) {
            $query->where('payment_gateway', $filters['payment_gateway']);
        }
        if (! empty($filters['payment_method'])) {
            $query->where('payment_method', $filters['payment_method']);
        }
        if (! empty($filters['currency'])) {
            $query->where('currency', $filters['currency']);
        }

        // ── Amount range ──
        if (isset($filters['amount_min'])) {
            $query->where('amount', '>=', $filters['amount_min']);
        }
        if (isset($filters['amount_max'])) {
            $query->where('amount', '<=', $filters['amount_max']);
        }

        // ── Date ranges ──
        if (! empty($filters['date_from'])) {
            $query->whereDate('created_at', '>=', $filters['date_from']);
        }
        if (! empty($filters['date_to'])) {
            $query->whereDate('created_at', '<=', $filters['date_to']);
        }
        if (! empty($filters['paid_from'])) {
            $query->whereDate('paid_at', '>=', $filters['paid_from']);
        }
        if (! empty($filters['paid_to'])) {
            $query->whereDate('paid_at', '<=', $filters['paid_to']);
        }

        // ── Refund filter ──
        if (isset($filters['has_refund'])) {
            $filters['has_refund']
                ? $query->whereNotNull('refunded_at')
                : $query->whereNull('refunded_at');
        }

        // ── Sort ──
        $sortBy  = $filters['sort_by'] ?? 'created_at';
        $sortDir = $filters['sort_dir'] ?? 'desc';
        $query->orderBy($sortBy, $sortDir);

        return $query->paginate($filters['per_page'] ?? 15);
    }

    // ──────────────────────────────────────────────
    //  DETAIL
    // ──────────────────────────────────────────────

    public function getDetail(int $id): Payment
    {
        return Payment::withTrashed()
            ->with([
                'user:id,name,email,avatar,phone',
                'subscription:id,plan_id,status,billing_cycle,starts_at,ends_at',
                'subscription.plan:id,name,slug',
                'coupon:id,code,name,discount_type,discount_value',
                'invoices' => fn ($q) => $q->select('id', 'uuid', 'payment_id', 'invoice_number', 'status', 'total', 'currency', 'issued_at', 'paid_at'),
            ])
            ->findOrFail($id);
    }

    // ──────────────────────────────────────────────
    //  UPDATE
    // ──────────────────────────────────────────────

    public function update(Payment $payment, array $data, int $adminId): Payment
    {
        $oldStatus = $payment->status;

        // Auto-set paid_at when status changes to completed
        if (isset($data['status']) && $data['status'] === 'completed' && $payment->status !== 'completed') {
            $data['paid_at'] = now();
        }

        $payment->update($data);

        // Log status change
        if (isset($data['status']) && $data['status'] !== $oldStatus) {
            $this->logAction($payment, $adminId, 'payment_status_changed', [
                'old_status' => $oldStatus,
                'new_status' => $data['status'],
            ]);
        }

        return $payment->fresh([
            'user:id,name,email,avatar',
            'subscription:id,plan_id,status',
            'coupon:id,code,name',
        ]);
    }

    // ──────────────────────────────────────────────
    //  REFUND (full or partial)
    // ──────────────────────────────────────────────

    public function processRefund(Payment $payment, array $data, int $adminId): Payment
    {
        if (! in_array($payment->status, ['completed', 'partially_refunded'])) {
            abort(422, 'Only completed or partially refunded payments can be refunded.');
        }

        $currentRefunded = (float) ($payment->refunded_amount ?? 0);
        $maxRefundable   = (float) $payment->amount - $currentRefunded;

        // Full refund if no amount specified
        $refundAmount = isset($data['amount'])
            ? (float) $data['amount']
            : $maxRefundable;

        if ($refundAmount <= 0 || $refundAmount > $maxRefundable) {
            abort(422, "Refund amount must be between 0.01 and {$maxRefundable}.");
        }

        $newRefundedTotal = $currentRefunded + $refundAmount;
        $isFullRefund     = abs($newRefundedTotal - (float) $payment->amount) < 0.01;

        $payment->update([
            'refunded_amount' => $newRefundedTotal,
            'refunded_at'     => now(),
            'refund_reason'   => $data['reason'],
            'status'          => $isFullRefund ? 'refunded' : 'partially_refunded',
        ]);

        $this->logAction($payment, $adminId, 'payment_refunded', [
            'refund_amount'    => $refundAmount,
            'total_refunded'   => $newRefundedTotal,
            'is_full_refund'   => $isFullRefund,
            'reason'           => $data['reason'],
        ]);

        // Update related invoice status if fully refunded
        if ($isFullRefund) {
            $payment->invoices()->where('status', '!=', 'refunded')->update(['status' => 'refunded']);
        }

        return $payment->fresh([
            'user:id,name,email,avatar',
            'invoices:id,payment_id,invoice_number,status,total',
        ]);
    }

    // ──────────────────────────────────────────────
    //  DELETE / FORCE-DELETE / RESTORE
    // ──────────────────────────────────────────────

    public function delete(Payment $payment, int $adminId): bool
    {
        if ($payment->status === 'pending') {
            abort(422, 'Cannot delete a pending payment. Cancel it first.');
        }

        $this->logAction($payment, $adminId, 'payment_deleted');
        return $payment->delete();
    }

    public function forceDelete(int $id, int $adminId): bool
    {
        $payment = Payment::withTrashed()->findOrFail($id);
        $this->logAction($payment, $adminId, 'payment_force_deleted');
        return $payment->forceDelete();
    }

    public function restore(int $id, int $adminId): Payment
    {
        $payment = Payment::onlyTrashed()->findOrFail($id);
        $payment->restore();
        $this->logAction($payment, $adminId, 'payment_restored');

        return $payment->fresh([
            'user:id,name,email,avatar',
            'subscription:id,plan_id,status',
        ]);
    }

    // ──────────────────────────────────────────────
    //  AGGREGATIONS
    // ──────────────────────────────────────────────

    public function getAggregations(array $filters = []): array
    {
        $baseQuery = Payment::query();

        if (! empty($filters['date_from'])) {
            $baseQuery->whereDate('created_at', '>=', $filters['date_from']);
        }
        if (! empty($filters['date_to'])) {
            $baseQuery->whereDate('created_at', '<=', $filters['date_to']);
        }

        // ── Summary stats ──
        $stats = (clone $baseQuery)->select([
            DB::raw('COUNT(*) as total_payments'),
            DB::raw("SUM(CASE WHEN status = 'completed' THEN amount ELSE 0 END) as total_revenue"),
            DB::raw("SUM(CASE WHEN status = 'completed' THEN net_amount ELSE 0 END) as total_net_revenue"),
            DB::raw("SUM(CASE WHEN status = 'completed' THEN discount_amount ELSE 0 END) as total_discounts"),
            DB::raw("SUM(CASE WHEN status = 'completed' THEN tax_amount ELSE 0 END) as total_taxes"),
            DB::raw("SUM(CASE WHEN status IN ('refunded','partially_refunded') THEN refunded_amount ELSE 0 END) as total_refunded"),
            DB::raw("AVG(CASE WHEN status = 'completed' THEN amount ELSE NULL END) as avg_payment_amount"),
            DB::raw("COUNT(CASE WHEN status = 'completed' THEN 1 END) as completed_count"),
            DB::raw("COUNT(CASE WHEN status = 'failed' THEN 1 END) as failed_count"),
            DB::raw("COUNT(CASE WHEN status = 'pending' THEN 1 END) as pending_count"),
            DB::raw("COUNT(CASE WHEN status = 'refunded' THEN 1 END) as refunded_count"),
            DB::raw("COUNT(CASE WHEN status = 'disputed' THEN 1 END) as disputed_count"),
        ])->first();

        // ── Revenue by gateway ──
        $byGateway = (clone $baseQuery)
            ->where('status', 'completed')
            ->select([
                'payment_gateway',
                DB::raw('COUNT(*) as count'),
                DB::raw('SUM(amount) as total'),
                DB::raw('SUM(net_amount) as net_total'),
            ])
            ->groupBy('payment_gateway')
            ->orderByDesc('total')
            ->get();

        // ── Revenue by currency ──
        $byCurrency = (clone $baseQuery)
            ->where('status', 'completed')
            ->select([
                'currency',
                DB::raw('COUNT(*) as count'),
                DB::raw('SUM(amount) as total'),
            ])
            ->groupBy('currency')
            ->orderByDesc('total')
            ->get();

        // ── Daily revenue trend (last 30 days) ──
        $days = (int) ($filters['trend_days'] ?? 30);
        $dailyTrend = Payment::query()
            ->where('status', 'completed')
            ->where('paid_at', '>=', now()->subDays($days))
            ->select([
                DB::raw('DATE(paid_at) as date'),
                DB::raw('COUNT(*) as count'),
                DB::raw('SUM(amount) as total'),
                DB::raw('SUM(net_amount) as net_total'),
            ])
            ->groupBy(DB::raw('DATE(paid_at)'))
            ->orderBy('date')
            ->get();

        // ── Top paying users ──
        $topUsers = Payment::query()
            ->where('payments.status', 'completed')
            ->join('users', 'payments.user_id', '=', 'users.id')
            ->select([
                'users.id',
                'users.name',
                'users.email',
                DB::raw('COUNT(payments.id) as payments_count'),
                DB::raw('SUM(payments.amount) as total_spent'),
            ])
            ->groupBy('users.id', 'users.name', 'users.email')
            ->orderByDesc('total_spent')
            ->limit(10)
            ->get();

        // ── Status distribution ──
        $statusDistribution = (clone $baseQuery)
            ->select([
                'status',
                DB::raw('COUNT(*) as count'),
                DB::raw('SUM(amount) as total'),
            ])
            ->groupBy('status')
            ->orderByDesc('count')
            ->get();

        return [
            'summary'             => $stats,
            'by_gateway'          => $byGateway,
            'by_currency'         => $byCurrency,
            'daily_trend'         => $dailyTrend,
            'top_users'           => $topUsers,
            'status_distribution' => $statusDistribution,
        ];
    }

    // ──────────────────────────────────────────────
    //  PRIVATE: Audit log
    // ──────────────────────────────────────────────

    private function logAction(Payment $payment, int $adminId, string $action, array $extra = []): void
    {
        UsageLog::create([
            'user_id'      => $payment->user_id,
            'action'       => $action,
            'description'  => "Admin #{$adminId} performed [{$action}] on payment #{$payment->id}",
            'metadata'     => array_merge([
                'payment_id' => $payment->id,
                'payment_uuid' => $payment->uuid,
                'admin_id'   => $adminId,
            ], $extra),
        ]);
    }
}
