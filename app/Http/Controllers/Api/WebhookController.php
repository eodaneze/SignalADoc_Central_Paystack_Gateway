<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\App;
use App\Models\Transaction;
use App\Models\WebhookEvent;
use App\Support\GatewayLogger;
use Illuminate\Http\Request;

class WebhookController extends Controller
{
    public function handle(Request $request)
    {
        GatewayLogger::info('gateway.webhook.received', [
            'ip' => $request->ip(),
            'signature_present' => $request->hasHeader('x-paystack-signature'),
            'user_agent' => $request->userAgent(),
        ]);

        $signature = $request->header('x-paystack-signature');
        if (!$signature) {
            GatewayLogger::warning('gateway.webhook.missing_signature', [
                'ip' => $request->ip(),
            ]);

            return response()->json(['error' => 'Missing signature'], 400);
        }

        $payload = $request->getContent();

        // Idempotency: signature is unique for a given payload delivery
        if (WebhookEvent::where('signature', $signature)->exists()) {
            GatewayLogger::info('gateway.webhook.duplicate_ignored', [
                'signature' => substr($signature, 0, 12) . '...',
                'ip' => $request->ip(),
            ]);

            return response()->json(['message' => 'Duplicate'], 200);
        }

        $data = json_decode($payload, true);
        if (!is_array($data)) {
            GatewayLogger::warning('gateway.webhook.invalid_json', [
                'signature' => substr($signature, 0, 12) . '...',
            ]);

            return response()->json(['error' => 'Invalid JSON'], 400);
        }

        $event = $data['event'] ?? null;
        $reference = $data['data']['reference'] ?? null;

        GatewayLogger::info('gateway.webhook.parsed', [
            'event' => $event,
            'reference' => $reference,
        ]);

        if (!$reference) {
            GatewayLogger::warning('gateway.webhook.missing_reference', [
                'event' => $event,
            ]);

            // Acknowledge so Paystack doesn't keep retrying, but we can't reconcile
            return response()->json(['status' => 'ok'], 200);
        }

        $transaction = Transaction::where('reference', $reference)->first();
        if (!$transaction) {
            GatewayLogger::warning('gateway.webhook.transaction_not_found', [
                'reference' => $reference,
                'event' => $event,
            ]);

            // Acknowledge to avoid repeated retries; transaction may not be created yet
            return response()->json(['status' => 'ok'], 200);
        }

        $app = App::where('app_id', $transaction->app_id)->firstOrFail();

        // Signature verification (tenant-specific secret)
        $computed = hash_hmac('sha512', $payload, $app->paystack_secret_key);

        if (!hash_equals($computed, $signature)) {
            GatewayLogger::warning('gateway.webhook.signature_invalid', [
                'app_id' => $app->app_id,
                'reference' => $reference,
                'event' => $event,
                'signature' => substr($signature, 0, 12) . '...',
            ]);

            return response()->json(['error' => 'Invalid signature'], 403);
        }

        GatewayLogger::info('gateway.webhook.signature_valid', [
            'app_id' => $app->app_id,
            'reference' => $reference,
            'event' => $event,
        ]);

        // Persist webhook delivery (idempotency record + audit)
        WebhookEvent::create([
            'event' => $event,
            'reference' => $reference,
            'signature' => $signature,
            'payload' => $data,
        ]);

        // Apply updates based on event type
        if ($event === 'charge.success') {
            // Avoid flipping successful back/forth
            if ($transaction->status !== 'successful') {
                $transaction->update([
                    'status' => 'successful',
                    'paid_at' => now(),
                    'channel' => $data['data']['channel'] ?? $transaction->channel,
                    'raw_payload' => $data,
                    'gateway_response' => $data['data']['gateway_response'] ?? $transaction->gateway_response,
                ]);

                GatewayLogger::info('gateway.webhook.transaction_updated', [
                    'app_id' => $app->app_id,
                    'reference' => $reference,
                    'event' => $event,
                    'status' => 'successful',
                ]);
            } else {
                GatewayLogger::info('gateway.webhook.transaction_already_successful', [
                    'app_id' => $app->app_id,
                    'reference' => $reference,
                    'event' => $event,
                ]);
            }
        } elseif ($event === 'charge.failed') {
            if ($transaction->status !== 'failed') {
                $transaction->update([
                    'status' => 'failed',
                    'raw_payload' => $data,
                    'gateway_response' => $data['data']['gateway_response'] ?? $transaction->gateway_response,
                ]);

                GatewayLogger::info('gateway.webhook.transaction_updated', [
                    'app_id' => $app->app_id,
                    'reference' => $reference,
                    'event' => $event,
                    'status' => 'failed',
                ]);
            }
        } else {
            GatewayLogger::info('gateway.webhook.event_ignored', [
                'app_id' => $app->app_id,
                'reference' => $reference,
                'event' => $event,
            ]);
        }

        GatewayLogger::info('gateway.webhook.acknowledged', [
            'app_id' => $app->app_id,
            'reference' => $reference,
            'event' => $event,
        ]);

        return response()->json(['status' => 'ok'], 200);
    }
}
