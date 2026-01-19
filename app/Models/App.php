<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class App extends Model
{
    use HasFactory;
     protected $fillable = [
        'app_id',
        'name',
        'paystack_public_key',
        'paystack_secret_key',
        'environment',
        'callback_url',
        'webhook_secret',
    ];
}
