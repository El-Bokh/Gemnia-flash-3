<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class GeneratedImage extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'uuid',
        'user_id',
        'ai_request_id',
        'file_path',
        'file_name',
        'disk',
        'mime_type',
        'file_size',
        'width',
        'height',
        'thumbnail_path',
        'is_public',
        'is_favorite',
        'is_nsfw',
        'download_count',
        'view_count',
        'metadata',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'file_size' => 'integer',
            'width' => 'integer',
            'height' => 'integer',
            'is_public' => 'boolean',
            'is_favorite' => 'boolean',
            'is_nsfw' => 'boolean',
            'download_count' => 'integer',
            'view_count' => 'integer',
            'metadata' => 'array',
            'expires_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (GeneratedImage $model) {
            if (empty($model->uuid)) {
                $model->uuid = Str::uuid()->toString();
            }
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function aiRequest(): BelongsTo
    {
        return $this->belongsTo(AiRequest::class);
    }
}
