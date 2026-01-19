<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Transaction extends Model
{
    use HasFactory;

    protected $fillable = [
        'app_id',
        'reference',
        'amount',
        'currency',
        'status',
        'channel',
        'paid_at',
        'gateway_response',
        'raw_payload',
    ];

    protected $casts = [
        'gateway_response' => 'array',
        'raw_payload' => 'array',
        'paid_at' => 'datetime',
    ];
}