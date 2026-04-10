<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'hidden_prompt',
        'negative_prompt',
        'thumbnail',
        'category',
        'is_active',
        'is_premium',
        'sort_order',
        'settings',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'is_active'  => 'boolean',
            'is_premium' => 'boolean',
            'sort_order' => 'integer',
            'settings'   => 'array',
            'metadata'   => 'array',
        ];
    }

    public function aiRequests(): HasMany
    {
        return $this->hasMany(AiRequest::class);
    }
}
