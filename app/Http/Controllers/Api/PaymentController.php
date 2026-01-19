<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\App;
use App\Models\Transaction;
use App\Services\PaystackService;
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

        $app = App::where('app_id', $data['app_id'])->firstOrFail();

        Transaction::create([
            'app_id' => $app->app_id,
            'reference' => $data['reference'],
            'amount' => $data['amount'],
            'currency' => $data['currency'],
        ]);

        $paystack = new PaystackService($app);

        $response = $paystack->initialize([
            'email' => $data['email'],
            'amount' => $data['amount'],
            'currency' => $data['currency'],
            'reference' => $data['reference'],
            'callback_url' => route('payment.callback'),
            'metadata' => $data['metadata'] ?? [],
        ]);

        return response()->json([
            'authorization_url' => $response['data']['authorization_url'],
            'reference' => $data['reference'],
        ]);
    }

    public function callback(Request $request)
    {
        $reference = $request->query('reference');

        $transaction = Transaction::where('reference', $reference)->firstOrFail();
        $app = App::where('app_id', $transaction->app_id)->firstOrFail();

        $paystack = new PaystackService($app);
        $result = $paystack->verify($reference);

        if ($result['data']['status'] === 'success') {
            $transaction->update([
                'status' => 'successful',
                'paid_at' => now(),
                'gateway_response' => $result,
            ]);
        } else {
            $transaction->update(['status' => 'failed']);
        }

        return redirect()->away(
            $app->callback_url . '?status=' . $transaction->status . '&reference=' . $reference
        );
    }

}
