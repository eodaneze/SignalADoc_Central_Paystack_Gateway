<?php

use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\WebhookController;
use App\Services\PaystackService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/


Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

// Payment initialization (called by tenant apps)
Route::middleware(['throttle:payment-init'])->group(function () {
    Route::post('/payments/initialize', [PaymentController::class, 'initialize']);
});


// Paystack redirect callback
Route::get('/payments/callback', [PaymentController::class, 'callback'])
    ->name('payment.callback');

// Paystack webhook listener
Route::middleware(['throttle:webhooks'])->group(function () {
    Route::post('/webhooks/paystack', [WebhookController::class, 'handle']);
});
