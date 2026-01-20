<?php

namespace App\Services;

use App\Models\App;
use App\Support\GatewayLogger;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PaystackService
{
    protected App $app;

    public function __construct(App $app)
    {
        $this->app = $app;
    }

    protected function headers(): array
    {
        return [
            'Authorization' => 'Bearer ' . $this->app->paystack_secret_key,
            'Content-Type' => 'application/json',
        ];
    }

    public function initialize(array $payload): array
    {
        GatewayLogger::info('paystack.initialize.request', [
            'app_id' => $this->app->app_id,
            'reference' => $payload['reference'] ?? null,
            'amount' => $payload['amount'] ?? null,
            'currency' => $payload['currency'] ?? null,
        ]);

        try {
            $response = Http::withHeaders($this->headers())
                ->timeout(15)
                ->retry(2, 300)
                ->post('https://api.paystack.co/transaction/initialize', $payload)
                ->throw()
                ->json();

            GatewayLogger::info('paystack.initialize.response', [
                'app_id' => $this->app->app_id,
                'reference' => $payload['reference'] ?? null,
                'status' => $response['status'] ?? null,
                'message' => $response['message'] ?? null,
            ]);

            return $response;

        } catch (ConnectionException $e) {
            GatewayLogger::error('paystack.initialize.timeout', [
                'app_id' => $this->app->app_id,
                'reference' => $payload['reference'] ?? null,
                'error' => $e->getMessage(),
            ]);
            throw $e;

        } catch (\Throwable $e) {
            GatewayLogger::error('paystack.initialize.failed', [
                'app_id' => $this->app->app_id,
                'reference' => $payload['reference'] ?? null,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }


     public function verify(string $reference): array
    {
        GatewayLogger::info('paystack.verify.request', [
            'app_id' => $this->app->app_id,
            'reference' => $reference,
        ]);

        try {
            $result = Http::withHeaders($this->headers())
                ->timeout(15)
                ->retry(2, 300)
                ->get("https://api.paystack.co/transaction/verify/{$reference}")
                ->throw()
                ->json();

            GatewayLogger::info('paystack.verify.response', [
                'app_id' => $this->app->app_id,
                'reference' => $reference,
                'status' => $result['data']['status'] ?? null,
                'gateway_message' => $result['data']['gateway_response'] ?? null,
            ]);

            return $result;

        } catch (ConnectionException $e) {
            GatewayLogger::error('paystack.verify.timeout', [
                'app_id' => $this->app->app_id,
                'reference' => $reference,
                'error' => $e->getMessage(),
            ]);
            throw $e;

        } catch (\Throwable $e) {
            GatewayLogger::error('paystack.verify.failed', [
                'app_id' => $this->app->app_id,
                'reference' => $reference,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

}
