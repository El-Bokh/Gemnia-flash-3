<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlanFeature extends Model
{
    use HasFactory;

    protected $fillable = [
        'plan_id',
        'feature_id',
        'is_enabled',
        'usage_limit',
        'limit_period',
        'credits_per_use',
        'constraints',
    ];

    protected function casts(): array
    {
        return [
            'is_enabled' => 'boolean',
            'usage_limit' => 'integer',
            'credits_per_use' => 'integer',
            'constraints' => 'array',
        ];
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    public function feature(): BelongsTo
    {
        return $this->belongsTo(Feature::class);
    }
}
