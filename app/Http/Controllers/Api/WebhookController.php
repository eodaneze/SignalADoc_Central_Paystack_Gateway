<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\App;
use App\Models\Transaction;
use App\Models\WebhookEvent;
use Illuminate\Http\Request;

class WebhookController extends Controller
{
    public function handle(Request $request)
{
    $signature = $request->header('x-paystack-signature');
    if (!$signature) {
        return response()->json(['error' => 'Missing signature'], 400);
    }

    $payload = $request->getContent();

    if (WebhookEvent::where('signature', $signature)->exists()) {
        return response()->json(['message' => 'Duplicate'], 200);
    }

    $data = json_decode($payload, true);
    $reference = $data['data']['reference'] ?? null;

    $transaction = Transaction::where('reference', $reference)->first();
    if (!$transaction) return response()->json([], 200);

    $app = App::where('app_id', $transaction->app_id)->firstOrFail();

    $computed = hash_hmac('sha512', $payload, $app->paystack_secret_key);

    abort_unless(hash_equals($computed, $signature), 403);

    WebhookEvent::create([
        'event' => $data['event'],
        'reference' => $reference,
        'signature' => $signature,
        'payload' => $data,
    ]);

    if ($data['event'] === 'charge.success') {
        $transaction->update([
            'status' => 'successful',
            'paid_at' => now(),
        ]);
    }

    return response()->json(['status' => 'ok']);
}

}
