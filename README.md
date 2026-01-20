# Multi-Tenant Paystack Payment Gateway (Laravel)

A centralized Paystack payment gateway service designed to support multiple Laravel applications (tenants) with isolated credentials, callbacks, and reliable payment reconciliation.

---

## Features

- Multi-tenant Paystack integration
- Per-app Paystack keys (test & live)
- Centralized payment initialization
- Secure redirect callback handling
- Verified webhook processing
- Idempotent webhook reconciliation
- Structured gateway logging
- Rate limiting per tenant

---

## Tech Stack

- Laravel 9+
- PHP 8+
- MySQL
- Paystack REST API
- Laravel HTTP Client
- Ngrok (local webhook testing)

---

## Setup

### 1. Clone and install dependencies

```bash
git clone <repo-url>
cd SignalADoc_Central_Paystack_Gateway
composer install
cp .env.example .env
php artisan key:generate


### 2. Environment Variables
APP_NAME=PaystackGateway
APP_ENV=local
APP_URL=http://localhost:8000

DB_DATABASE=paystack_gateway
DB_USERNAME=root
DB_PASSWORD=

PAYSTACK_LOG_LEVEL=info

### 3. Run migrations
php artisan migrate

### 4. start server
php artisan serve

### Multi-Tenant Configuration
Each tenant application is stored in the apps table with:
app_id(UUID), paystack_publick_key, paystack_secret_key, callback_url, environment(test|live)

### Payment Flow
### 1. Initialize Payment
## POST /api/payments/initialize
{
  "app_id": "tenant-uuid",
  "email": "user@exampletest.com",
  "amount": 50000,
  "currency": "NGN",
  "reference": "ORDER_12345"
}

## Response
{
  "authorization_url": "https://checkout.paystack.com/...",
  "reference": "ORDER_12345"
}

## 2. Paystack Redirect Callback
## Paystack redirects to: 
GET /api/payments/callback?reference=ORDER_12345

## the gateway: verifies transaction via paystack api, updates transaction status and redirects users to the orignating app's callback_url with status (the orignating app callback_url is in the apps table)

## 3. Webhook Handling
## POST /api/webhooks/paystack
- Signature validated using tenant secret key
- Idempotency enforced via webhook_events
- Transaction status reconciled even if redirect was skipped


## Security Considerations
- Per-tenant Paystack secret keys
- HMAC SHA-512 webhook verification
- Idempotent webhook processing
- Rate limiting per app
- Secrets never logged
- Handling of Paystack downtime
- Webhook acknowledgement to prevent retry storms


## Logging
## All gateway interactions are logged to: 
- storage/logs/paystack-YYYY-MM-DD.log

## Logged events include:
- Initialization requests/responses
- Verification requests/responses
- Callback lifecycle
- Webhook lifecycle
- Errors and retries


## Local Webhook Testing

## Paystack cannot send webhooks to localhost.
use ngrok: ngrok http 8000
## Set webhook URL in Paystack dashboard: 
https://<ngrok-id>.ngrok-free.app/api/webhooks/paystack
