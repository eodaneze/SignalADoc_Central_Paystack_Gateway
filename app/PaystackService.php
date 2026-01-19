<?php

namespace App\Services;

use App\Models\App;
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
        Log::channel('paystack')->info('Initializing Paystack transaction', [
            'app_id' => $this->app->app_id,
            'reference' => $payload['reference'] ?? null,
        ]);

        $response = Http::withHeaders($this->headers())
            ->post('https://api.paystack.co/transaction/initialize', $payload)
            ->throw()
            ->json();

        return $response;
    }

    public function verify(string $reference): array
    {
        Log::channel('paystack')->info('Verifying Paystack transaction', [
            'app_id' => $this->app->app_id,
            'reference' => $reference,
        ]);

        return Http::withHeaders($this->headers())
            ->get("https://api.paystack.co/transaction/verify/{$reference}")
            ->throw()
            ->json();
    }
}
