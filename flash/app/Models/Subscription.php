<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Subscription extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id',
        'plan_id',
        'billing_cycle',
        'status',
        'price',
        'currency',
        'trial_starts_at',
        'trial_ends_at',
        'starts_at',
        'ends_at',
        'cancelled_at',
        'cancellation_reason',
        'payment_gateway',
        'gateway_subscription_id',
        'gumroad_product_id',
        'gumroad_sale_id',
        'gumroad_variant',
        'gumroad_license_key',
        'credits_remaining',
        'credits_total',
        'auto_renew',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'credits_remaining' => 'integer',
            'credits_total' => 'integer',
            'auto_renew' => 'boolean',
            'trial_starts_at' => 'datetime',
            'trial_ends_at' => 'datetime',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    public function logs(): HasMany
    {
        return $this->hasMany(SubscriptionLog::class);
    }

    public function aiRequests(): HasMany
    {
        return $this->hasMany(AiRequest::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function usageLogs(): HasMany
    {
        return $this->hasMany(UsageLog::class);
    }

    public function creditLedgers(): HasMany
    {
        return $this->hasMany(CreditLedger::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    public function syncTrialStatus(): self
    {
        if ($this->status !== 'trialing') {
            return $this;
        }

        $this->loadMissing('plan');

        $now = now();
        $trialDays = (int) ($this->plan->trial_days ?? 0);
        $effectiveTrialStart = $this->trial_starts_at ?? $this->starts_at;

        $updates = [];
        if ($trialDays <= 0) {
            $updates['status'] = 'active';
            $updates['trial_ends_at'] = $this->trial_ends_at instanceof CarbonInterface && $this->trial_ends_at->lte($now)
                ? $this->trial_ends_at
                : $now;
        } elseif ($effectiveTrialStart instanceof CarbonInterface) {
            $effectiveTrialEnd = $effectiveTrialStart->copy()->addDays($trialDays);

            if ($this->trial_starts_at === null) {
                $updates['trial_starts_at'] = $effectiveTrialStart;
            }

            if (! ($this->trial_ends_at instanceof CarbonInterface) || ! $this->trial_ends_at->equalTo($effectiveTrialEnd)) {
                $updates['trial_ends_at'] = $effectiveTrialEnd;
            }

            if ($effectiveTrialEnd->lte($now)) {
                $updates['status'] = 'active';
            }
        } elseif ($this->trial_ends_at instanceof CarbonInterface && $this->trial_ends_at->lte($now)) {
            $updates['status'] = 'active';
        }

        if ($updates === []) {
            return $this;
        }

        $this->fill($updates);
        $this->save();

        return $this;
    }
}
