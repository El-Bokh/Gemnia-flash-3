<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class AiRequest extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'uuid',
        'user_id',
        'subscription_id',
        'visual_style_id',
        'product_id',
        'type',
        'status',
        'user_prompt',
        'processed_prompt',
        'negative_prompt',
        'hidden_prompt',
        'model_used',
        'engine_provider',
        'width',
        'height',
        'steps',
        'cfg_scale',
        'sampler',
        'seed',
        'num_images',
        'credits_consumed',
        'input_image_path',
        'output_image_path',
        'mask_image_path',
        'denoising_strength',
        'error_message',
        'error_code',
        'retry_count',
        'processing_time_ms',
        'ip_address',
        'user_agent',
        'request_payload',
        'response_payload',
        'metadata',
        'started_at',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'width' => 'integer',
            'height' => 'integer',
            'steps' => 'integer',
            'cfg_scale' => 'decimal:2',
            'seed' => 'integer',
            'num_images' => 'integer',
            'credits_consumed' => 'integer',
            'denoising_strength' => 'decimal:2',
            'retry_count' => 'integer',
            'processing_time_ms' => 'integer',
            'request_payload' => 'array',
            'response_payload' => 'array',
            'metadata' => 'array',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (AiRequest $model) {
            if (empty($model->uuid)) {
                $model->uuid = Str::uuid()->toString();
            }
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    public function visualStyle(): BelongsTo
    {
        return $this->belongsTo(VisualStyle::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function generatedImages(): HasMany
    {
        return $this->hasMany(GeneratedImage::class);
    }

    public function usageLogs(): HasMany
    {
        return $this->hasMany(UsageLog::class);
    }
}
