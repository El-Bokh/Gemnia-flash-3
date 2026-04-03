<?php

namespace App\Services\Admin;

use App\Models\Coupon;
use App\Models\Payment;
use App\Models\UsageLog;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class CouponManagementService
{
    // ──────────────────────────────────────────────
    //  LIST (paginated with filters)
    // ──────────────────────────────────────────────

    public function list(array $filters = []): LengthAwarePaginator
    {
        $query = Coupon::query()
            ->with(['applicablePlan:id,name,slug']);

        // ── Trashed ──
        if (! empty($filters['trashed'])) {
            $filters['trashed'] === 'only'
                ? $query->onlyTrashed()
                : $query->withTrashed();
        }

        // ── Search (code, name, description) ──
        if (! empty($filters['search'])) {
            $term = $filters['search'];
            $query->where(function (Builder $q) use ($term) {
                $q->where('code', 'LIKE', "%{$term}%")
                  ->orWhere('name', 'LIKE', "%{$term}%")
                  ->orWhere('description', 'LIKE', "%{$term}%");
            });
        }

        // ── Exact filters ──
        if (! empty($filters['discount_type'])) {
            $query->where('discount_type', $filters['discount_type']);
        }
        if (isset($filters['is_active'])) {
            $query->where('is_active', $filters['is_active']);
        }
        if (! empty($filters['applicable_plan_id'])) {
            $query->where('applicable_plan_id', $filters['applicable_plan_id']);
        }

        // ── Expired filter ──
        if (isset($filters['expired'])) {
            if ($filters['expired']) {
                $query->whereNotNull('expires_at')->where('expires_at', '<', now());
            } else {
                $query->where(function (Builder $q) {
                    $q->whereNull('expires_at')->orWhere('expires_at', '>=', now());
                });
            }
        }

        // ── Has uses remaining ──
        if (isset($filters['has_uses_remaining'])) {
            if ($filters['has_uses_remaining']) {
                $query->where(function (Builder $q) {
                    $q->whereNull('max_uses')
                      ->orWhereColumn('times_used', '<', 'max_uses');
                });
            } else {
                $query->whereNotNull('max_uses')
                      ->whereColumn('times_used', '>=', 'max_uses');
            }
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

    public function getDetail(int $id): Coupon
    {
        return Coupon::withTrashed()
            ->with(['applicablePlan:id,name,slug'])
            ->withCount('payments')
            ->withSum('payments', 'amount')
            ->withSum('payments', 'discount_amount')
            ->findOrFail($id);
    }

    // ──────────────────────────────────────────────
    //  CREATE
    // ──────────────────────────────────────────────

    public function create(array $data, int $adminId): Coupon
    {
        $data['code'] = strtoupper(trim($data['code']));
        $data['times_used'] = 0;

        $coupon = Coupon::create($data);

        $this->logAction($coupon, $adminId, 'coupon_created');

        return $coupon->fresh(['applicablePlan:id,name,slug']);
    }

    // ──────────────────────────────────────────────
    //  UPDATE
    // ──────────────────────────────────────────────

    public function update(Coupon $coupon, array $data, int $adminId): Coupon
    {
        if (isset($data['code'])) {
            $data['code'] = strtoupper(trim($data['code']));
        }

        $coupon->update($data);

        $this->logAction($coupon, $adminId, 'coupon_updated', [
            'changed_fields' => array_keys($data),
        ]);

        return $coupon->fresh(['applicablePlan:id,name,slug']);
    }

    // ──────────────────────────────────────────────
    //  TOGGLE ACTIVE
    // ──────────────────────────────────────────────

    public function toggleActive(Coupon $coupon, int $adminId): Coupon
    {
        $coupon->update(['is_active' => ! $coupon->is_active]);

        $this->logAction($coupon, $adminId, 'coupon_toggled', [
            'is_active' => $coupon->is_active,
        ]);

        return $coupon;
    }

    // ──────────────────────────────────────────────
    //  VALIDATE COUPON
    // ──────────────────────────────────────────────

    public function validate(array $data): array
    {
        $coupon = Coupon::where('code', strtoupper(trim($data['code'])))->first();

        if (! $coupon) {
            return ['valid' => false, 'reason' => 'Coupon not found.'];
        }

        if (! $coupon->is_active) {
            return ['valid' => false, 'reason' => 'Coupon is not active.'];
        }

        if ($coupon->expires_at && $coupon->expires_at->isPast()) {
            return ['valid' => false, 'reason' => 'Coupon has expired.'];
        }

        if ($coupon->starts_at && $coupon->starts_at->isFuture()) {
            return ['valid' => false, 'reason' => 'Coupon is not yet active.'];
        }

        if ($coupon->max_uses && $coupon->times_used >= $coupon->max_uses) {
            return ['valid' => false, 'reason' => 'Coupon usage limit reached.'];
        }

        // ── Per-user limit ──
        if (! empty($data['user_id']) && $coupon->max_uses_per_user) {
            $userUses = Payment::where('coupon_id', $coupon->id)
                ->where('user_id', $data['user_id'])
                ->whereNotIn('status', ['failed', 'cancelled'])
                ->count();

            if ($userUses >= $coupon->max_uses_per_user) {
                return ['valid' => false, 'reason' => 'User has reached the usage limit for this coupon.'];
            }
        }

        // ── Plan restriction ──
        if (! empty($data['plan_id']) && $coupon->applicable_plan_id) {
            if ((int) $data['plan_id'] !== $coupon->applicable_plan_id) {
                return ['valid' => false, 'reason' => 'Coupon is not applicable for the selected plan.'];
            }
        }

        // ── Min order amount ──
        if (! empty($data['amount']) && $coupon->min_order_amount) {
            if ((float) $data['amount'] < (float) $coupon->min_order_amount) {
                return ['valid' => false, 'reason' => "Minimum order amount is {$coupon->min_order_amount} {$coupon->currency}."];
            }
        }

        // ── Calculate discount ──
        $discount = null;
        if (! empty($data['amount'])) {
            $discount = $this->calculateDiscount($coupon, (float) $data['amount']);
        }

        return [
            'valid'   => true,
            'coupon'  => [
                'id'             => $coupon->id,
                'code'           => $coupon->code,
                'name'           => $coupon->name,
                'discount_type'  => $coupon->discount_type,
                'discount_value' => (float) $coupon->discount_value,
                'currency'       => $coupon->currency,
            ],
            'discount' => $discount,
        ];
    }

    // ──────────────────────────────────────────────
    //  DELETE / FORCE-DELETE / RESTORE
    // ──────────────────────────────────────────────

    public function delete(Coupon $coupon, int $adminId): bool
    {
        $this->logAction($coupon, $adminId, 'coupon_deleted');
        return $coupon->delete();
    }

    public function forceDelete(int $id, int $adminId): bool
    {
        $coupon = Coupon::withTrashed()->findOrFail($id);
        $this->logAction($coupon, $adminId, 'coupon_force_deleted');
        return $coupon->forceDelete();
    }

    public function restore(int $id, int $adminId): Coupon
    {
        $coupon = Coupon::onlyTrashed()->findOrFail($id);
        $coupon->restore();
        $this->logAction($coupon, $adminId, 'coupon_restored');

        return $coupon->fresh(['applicablePlan:id,name,slug']);
    }

    // ──────────────────────────────────────────────
    //  USAGE STATS
    // ──────────────────────────────────────────────

    public function getUsageStats(Coupon $coupon): array
    {
        // ── Per-user breakdown ──
        $userBreakdown = Payment::where('coupon_id', $coupon->id)
            ->whereNotIn('payments.status', ['failed', 'cancelled'])
            ->join('users', 'payments.user_id', '=', 'users.id')
            ->select([
                'users.id',
                'users.name',
                'users.email',
                DB::raw('COUNT(payments.id) as uses'),
                DB::raw('SUM(payments.discount_amount) as total_discount'),
                DB::raw('SUM(payments.amount) as total_amount'),
            ])
            ->groupBy('users.id', 'users.name', 'users.email')
            ->orderByDesc('uses')
            ->limit(20)
            ->get();

        // ── Summary ──
        $summary = Payment::where('coupon_id', $coupon->id)
            ->whereNotIn('payments.status', ['failed', 'cancelled'])
            ->select([
                DB::raw('COUNT(*) as total_uses'),
                DB::raw('SUM(discount_amount) as total_discount_given'),
                DB::raw('SUM(amount) as total_revenue'),
                DB::raw('COUNT(DISTINCT user_id) as unique_users'),
                DB::raw('AVG(discount_amount) as avg_discount'),
            ])
            ->first();

        // ── Daily usage trend (last 30 days) ──
        $dailyTrend = Payment::where('coupon_id', $coupon->id)
            ->whereNotIn('payments.status', ['failed', 'cancelled'])
            ->where('created_at', '>=', now()->subDays(30))
            ->select([
                DB::raw('DATE(created_at) as date'),
                DB::raw('COUNT(*) as uses'),
                DB::raw('SUM(discount_amount) as discount_total'),
            ])
            ->groupBy(DB::raw('DATE(created_at)'))
            ->orderBy('date')
            ->get();

        return [
            'summary'        => $summary,
            'user_breakdown' => $userBreakdown,
            'daily_trend'    => $dailyTrend,
        ];
    }

    // ──────────────────────────────────────────────
    //  AGGREGATIONS
    // ──────────────────────────────────────────────

    public function getAggregations(): array
    {
        $stats = Coupon::select([
            DB::raw('COUNT(*) as total_coupons'),
            DB::raw("SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) as active_count"),
            DB::raw("SUM(CASE WHEN is_active = 0 THEN 1 ELSE 0 END) as inactive_count"),
            DB::raw('SUM(times_used) as total_uses'),
        ])->first();

        $expiredCount = Coupon::whereNotNull('expires_at')
            ->where('expires_at', '<', now())
            ->count();

        $byType = Coupon::select([
            'discount_type',
            DB::raw('COUNT(*) as count'),
            DB::raw('SUM(times_used) as total_uses'),
        ])
        ->groupBy('discount_type')
        ->get();

        $topCoupons = Coupon::where('times_used', '>', 0)
            ->select(['id', 'code', 'name', 'discount_type', 'discount_value', 'times_used'])
            ->orderByDesc('times_used')
            ->limit(10)
            ->get();

        return [
            'summary'      => $stats,
            'expired_count'=> $expiredCount,
            'by_type'      => $byType,
            'top_coupons'  => $topCoupons,
        ];
    }

    // ──────────────────────────────────────────────
    //  PRIVATE HELPERS
    // ──────────────────────────────────────────────

    private function calculateDiscount(Coupon $coupon, float $amount): array
    {
        $discount = match ($coupon->discount_type) {
            'percentage'   => round($amount * ($coupon->discount_value / 100), 2),
            'fixed_amount' => min((float) $coupon->discount_value, $amount),
            'credits'      => (float) $coupon->discount_value,
            default        => 0,
        };

        return [
            'original_amount' => $amount,
            'discount_amount' => $discount,
            'final_amount'    => $coupon->discount_type === 'credits' ? $amount : max($amount - $discount, 0),
            'type'            => $coupon->discount_type,
        ];
    }

    private function logAction(Coupon $coupon, int $adminId, string $action, array $extra = []): void
    {
        UsageLog::create([
            'user_id'     => $adminId,
            'action'      => $action,
            'description' => "Admin #{$adminId} performed [{$action}] on coupon #{$coupon->id} ({$coupon->code})",
            'metadata'    => array_merge([
                'coupon_id'   => $coupon->id,
                'coupon_code' => $coupon->code,
                'admin_id'    => $adminId,
            ], $extra),
        ]);
    }
}
