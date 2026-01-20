<?php

namespace App\Support;

use Illuminate\Support\Facades\Log;

class GatewayLogger
{
    public static function info(string $action, array $context = []): void
    {
        Log::channel('paystack')->info($action, self::sanitize($context));
    }

    public static function warning(string $action, array $context = []): void
    {
        Log::channel('paystack')->warning($action, self::sanitize($context));
    }

    public static function error(string $action, array $context = []): void
    {
        Log::channel('paystack')->error($action, self::sanitize($context));
    }

    // Prevent accidental logging of secrets.

    private static function sanitize(array $context): array
    {
        $blockedKeys = ['authorization', 'Authorization', 'paystack_secret_key', 'secret', 'sk_', 'pk_'];

        array_walk_recursive($context, function (&$value, $key) use ($blockedKeys) {
            foreach ($blockedKeys as $blocked) {
                if (stripos((string)$key, (string)$blocked) !== false) {
                    $value = '[REDACTED]';
                }
            }
        });

        return $context;
    }
}
