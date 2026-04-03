<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PaymentWebhook extends Model
{
    use HasFactory;

    protected $fillable = [
        'gateway',
        'event_type',
        'event_id',
        'gateway_payment_id',
        'gateway_subscription_id',
        'status',
        'payload',
        'headers',
        'error_message',
        'attempts',
        'processed_at',
        'ip_address',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'headers' => 'array',
            'attempts' => 'integer',
            'processed_at' => 'datetime',
        ];
    }
}
