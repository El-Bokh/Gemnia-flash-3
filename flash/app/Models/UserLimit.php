<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserLimit extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'limit_type',
        'max_requests',
        'used_requests',
        'period',
        'period_started_at',
        'period_ends_at',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'max_requests' => 'integer',
            'used_requests' => 'integer',
            'is_active' => 'boolean',
            'period_started_at' => 'datetime',
            'period_ends_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
