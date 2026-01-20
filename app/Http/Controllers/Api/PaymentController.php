<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\App;
use App\Models\Transaction;
use App\Services\PaystackService;
use App\Support\GatewayLogger;
use Illuminate\Http\Request;

class PaymentController extends Controller
{
    public function initialize(Request $request)
    {
        $data = $request->validate([
            'app_id' => 'required|uuid',
            'email' => 'required|email',
            'amount' => 'required|integer|min:1',
            'currency' => 'required|string',
            'reference' => 'required|string|unique:transactions,reference',
            'metadata' => 'nullable|array',
        ]);

        GatewayLogger::info('gateway.initialize.incoming', [
            'app_id' => $data['app_id'],
            'reference' => $data['reference'],
            'email' => $data['email'],
            'amount' => $data['amount'],
            'currency' => $data['currency'],
        ]);

        $app = App::where('app_id', $data['app_id'])->firstOrFail();

        $transaction = Transaction::create([
            'app_id' => $app->app_id,
            'reference' => $data['reference'],
            'amount' => $data['amount'],
            'currency' => $data['currency'],
            'status' => 'pending',
        ]);

        GatewayLogger::info('gateway.transaction.created', [
            'app_id' => $app->app_id,
            'reference' => $transaction->reference,
            'status' => $transaction->status,
        ]);

        $paystack = new PaystackService($app);

        try {
            $response = $paystack->initialize([
                'email' => $data['email'],
                'amount' => $data['amount'],
                'currency' => $data['currency'],
                'reference' => $data['reference'],
                'callback_url' => url('/api/payments/callback'),
                'metadata' => $data['metadata'] ?? [],
            ]);

            $authUrl = $response['data']['authorization_url'] ?? null;

            GatewayLogger::info('gateway.initialize.success', [
                'app_id' => $app->app_id,
                'reference' => $data['reference'],
                'authorization_url_present' => (bool) $authUrl,
            ]);

            return response()->json([
                'authorization_url' => $authUrl,
                'reference' => $data['reference'],
            ], 200);

        } catch (\Throwable $e) {
            // Leave txn as pending; webhook may still finalize later
            GatewayLogger::error('gateway.initialize.failed', [
                'app_id' => $app->app_id,
                'reference' => $data['reference'],
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => 'Unable to initialize payment at the moment. Please try again.',
                'reference' => $data['reference'],
            ], 503);
        }
    }

    public function callback(Request $request)
    {
        $reference = $request->query('reference');

        GatewayLogger::info('gateway.callback.hit', [
            'reference' => $reference,
            'query' => $request->query(),
        ]);

        $transaction = Transaction::where('reference', $reference)->firstOrFail();
        $app = App::where('app_id', $transaction->app_id)->firstOrFail();

        // Safety guard: avoid redirect loops if tenant callback_url is misconfigured
        if (str_starts_with($app->callback_url, url('/api/payments/callback'))) {
            GatewayLogger::warning('gateway.callback.redirect_loop_prevented', [
                'app_id' => $app->app_id,
                'reference' => $reference,
                'callback_url' => $app->callback_url,
            ]);

            return response()->json([
                'message' => 'Tenant callback_url is misconfigured (points to gateway callback).',
                'reference' => $reference,
            ], 500);
        }

        $paystack = new PaystackService($app);

        try {
            $result = $paystack->verify($reference);
            $paystackStatus = $result['data']['status'] ?? null;

            if ($paystackStatus === 'success') {
                $transaction->update([
                    'status' => 'successful',
                    'paid_at' => now(),
                    'gateway_response' => $result,
                ]);
            } else {
                $transaction->update([
                    'status' => 'failed',
                    'gateway_response' => $result,
                ]);
            }

            GatewayLogger::info('gateway.callback.verified', [
                'app_id' => $app->app_id,
                'reference' => $reference,
                'paystack_status' => $paystackStatus,
                'final_status' => $transaction->status,
            ]);

        } catch (\Throwable $e) {
            GatewayLogger::error('gateway.callback.verify_failed', [
                'app_id' => $app->app_id,
                'reference' => $reference,
                'error' => $e->getMessage(),
            ]);

            // Don't break the flow completely—send user back with "pending"
            $transaction->update(['status' => 'pending']);
        }

        $redirectTo = $app->callback_url . '?status=' . $transaction->status . '&reference=' . $reference;

        GatewayLogger::info('gateway.callback.redirecting', [
            'app_id' => $app->app_id,
            'reference' => $reference,
            'to' => $app->callback_url,
            'final_url' => $redirectTo,
        ]);

        return redirect()->away($redirectTo);
    }
}
