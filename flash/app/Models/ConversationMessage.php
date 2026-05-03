<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ConversationMessage extends Model
{
    protected $fillable = [
        'conversation_id',
        'ai_request_id',
        'role',
        'content',
        'image_url',
        'video_url',
        'image_style',
        'product_images',
        'metadata',
        'status',
    ];

    protected $casts = [
        'product_images' => 'array',
        'metadata' => 'array',
    ];

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    public function aiRequest(): BelongsTo
    {
        return $this->belongsTo(AiRequest::class);
    }
}
