# Laravel Payments

[![Packagist](https://img.shields.io/packagist/v/allanadan05/laravel-payments.svg)](https://packagist.org/packages/allanadan05/laravel-payments)
[![License: MIT](https://img.shields.io/badge/license-MIT-green.svg)](LICENSE)

A small, gateway-agnostic payment integration module for Laravel. It ships
with a working Braintree driver, but calling code never talks to Braintree
directly — everything goes through the `PaymentGateway` contract, so adding
Stripe, Authorize.Net, etc. later is a new driver class + a config entry,
not a rewrite of your controllers.

## Features

- **Gateway-agnostic contract** — controllers/jobs depend on `PaymentGateway`,
  never a concrete driver.
- **Bundled Braintree driver** — charge, refund, void, vault customers,
  subscriptions, webhooks, stored payment methods, and reporting.
- **Optional capability interfaces** — subscriptions, webhooks, etc. stay
  out of the core contract so simpler drivers aren't forced to implement
  them; check with `instanceof` (see [Optional
  capabilities](#optional-capabilities)).
- **Facade or dependency injection** — use `Payment::gateway()` or inject
  `PaymentGateway` directly.
- **Typed data transfer objects** for requests/results instead of loose
  arrays.
- **Laravel package auto-discovery** — service provider and facade register
  themselves.

## Requirements

- PHP ^8.1
- Laravel (`illuminate/support`) ^9.0, ^10.0, or ^11.0
- A Braintree account, if using the bundled driver (see
  [Braintree account setup](#braintree-account-setup) below)

## Installation

```bash
composer require allanadan05/laravel-payments
php artisan vendor:publish --tag=payments-config
```

Laravel's package auto-discovery registers the service provider and
`Payment` facade automatically — no manual registration needed.

## Configuration

Add credentials to `.env`:

```
PAYMENT_GATEWAY=braintree
BRAINTREE_ENVIRONMENT=sandbox
BRAINTREE_MERCHANT_ID=your_merchant_id
BRAINTREE_PUBLIC_KEY=your_public_key
BRAINTREE_PRIVATE_KEY=your_private_key
PAYMENT_CURRENCY=USD
```

`PAYMENT_GATEWAY` selects the default entry from the `gateways` array in
the published `config/payments.php`.

### Braintree account setup

- **Sandbox (free, for development):** sign up at
  https://sandbox.braintreegateway.com/signup. You'll get an activation
  email; set a password there, then find your Merchant ID / Public Key /
  Private Key under **Settings → API → Keys** once logged in. Braintree
  sandboxes are available to merchants domiciled in the US, Canada,
  Australia, Europe, Singapore, Hong Kong SAR China, Malaysia, and New
  Zealand.
- **Production (to actually take payments):** requires a separate
  application reviewed by Braintree's/PayPal's sales and underwriting
  team. Be ready to provide the legal business name, "Doing Business As"
  name, EIN/tax ID, business address, and a business checking account for
  disbursements (savings/prepaid accounts aren't accepted). They may
  follow up for ID or bank statements before final approval.
- Keep sandbox and production keys in separate `.env` files/environments —
  never commit real keys.

## Usage

```php
use Payments\Facades\Payment;
use Payments\DataTransferObjects\ChargeRequest;

// Client token for Braintree's Drop-in UI / hosted fields
$clientToken = Payment::gateway()->generateClientToken();

// Charge a payment method nonce collected from the frontend
$result = Payment::gateway()->charge(ChargeRequest::make(
    amount: '49.00',
    paymentMethodNonceOrToken: $request->input('payment_method_nonce'),
    options: ['order_id' => $order->id, 'customer_id' => $customer->braintree_id]
));

if ($result->successful) {
    // $result->transactionId, $result->status, $result->amount
} else {
    // $result->message
}

// Refund / void
Payment::gateway()->refund($result->transactionId);
Payment::gateway()->void($result->transactionId);

// Vault a customer + payment method
$customer = Payment::gateway()->createCustomer(
    ['firstName' => 'Jane', 'lastName' => 'Doe', 'email' => 'jane@example.com'],
    paymentMethodNonce: $nonce
);
```

You can also resolve a specific named gateway (useful once a second
provider is configured): `Payment::gateway('braintree')`.

Dependency-inject the contract instead of the facade if you prefer:

```php
use Payments\Contracts\PaymentGateway;

public function __construct(private PaymentGateway $gateway) {}
```

(Bind `PaymentGateway::class` to `Payment::gateway()` in a service provider
if you want constructor injection app-wide.)

## Optional capabilities

Not every gateway supports subscriptions, webhooks, etc., so those live
outside the core `PaymentGateway` contract as separate, optional
interfaces. `BraintreeGateway` implements all of them; a driver for a
simpler provider can skip any it doesn't support. Check with `instanceof`
before calling:

```php
use Payments\Contracts\SupportsSubscriptions;

$gateway = Payment::gateway();

if ($gateway instanceof SupportsSubscriptions) {
    $gateway->subscribe(/* ... */);
}
```

| Interface | Methods | Purpose |
|---|---|---|
| `SupportsSubscriptions` | `subscribe()`, `findSubscription()`, `updateSubscription()`, `cancelSubscription()` | Recurring billing |
| `SupportsWebhooks` | `verifyWebhook()`, `parseWebhook()` | Handle async gateway events |
| `SupportsStoredPaymentMethods` | `addPaymentMethod()`, `listPaymentMethods()`, `defaultPaymentMethod()`, `deletePaymentMethod()` | Manage a customer's vaulted cards/accounts |
| `SupportsReporting` | `findTransaction()`, `searchTransactions()` | Look up / search past transactions |
| `SupportsCustomerListing` | `findCustomer()`, `listCustomers()` | Look up / list vaulted customers |

```php
use Payments\DataTransferObjects\SubscriptionRequest;

// Subscriptions — paymentMethodToken is a *stored* token, not a one-time nonce
$result = Payment::gateway()->subscribe(SubscriptionRequest::make(
    planId: 'monthly_plan',
    paymentMethodToken: $customer->braintree_payment_method_token,
));

// Webhooks — in the controller handling your gateway's webhook URL
$event = Payment::gateway()->parseWebhook($request->input('bt_signature'), $request->input('bt_payload'));
match ($event->kind) {
    'subscription_canceled' => /* ... */,
    default => null,
};

// Stored payment methods
$methods = Payment::gateway()->listPaymentMethods($customer->braintree_id);
Payment::gateway()->deletePaymentMethod($token);

// Reporting
$transactions = Payment::gateway()->searchTransactions(['customer_id' => $customer->braintree_id]);

// Customer listing
$customers = Payment::gateway()->listCustomers(['email' => 'jane@example.com']);
```

`searchTransactions()` and `listCustomers()` take a flat criteria array;
`BraintreeGateway` supports exact-match lookups on a fixed set of keys
(see the docblocks on `searchTransactions()`/`listCustomers()` in
`src/Gateways/BraintreeGateway.php`) and throws on unknown keys rather
than silently ignoring them.

## Adding another gateway

1. Implement `Payments\Contracts\PaymentGateway` (see
   `src/Gateways/BraintreeGateway.php` for the shape).
2. Add a `config/payments.php` entry under `gateways` with a `driver` key.
3. Register the driver, typically in your `AppServiceProvider::boot()`:

```php
app(\Payments\PaymentManager::class)
    ->extend('stripe', fn (array $config) => new \App\Payments\StripeGateway($config));
```

No changes needed to `PaymentManager`, the facade, or any calling code.

## Roadmap

Planned drivers, in priority order:

1. **Stripe** — closest 1:1 fit to the existing contract; PaymentIntents,
   Subscriptions, Webhooks, and SetupIntents map directly onto
   `PaymentGateway` and all five optional capability interfaces.
2. **PayMongo** — Stripe-shaped API (PaymentIntents-style flow) that also
   happens to be the practical way to accept **GCash** and **Maya** in
   the Philippines, since it aggregates both under one integration
   instead of needing a separate driver per wallet.
3. **Maya (PayMaya)** — direct integration for merchants who want Maya as
   a primary gateway rather than going through PayMongo. Supports
   `charge`/webhooks; no native equivalent of Stripe/Braintree's
   subscription or stored-payment-method vaulting yet, so it likely
   won't implement `SupportsSubscriptions` or
   `SupportsStoredPaymentMethods` at first.
4. **PayPal** — standalone REST API integration for merchants who don't
   want to route PayPal through Braintree. Billing agreements back
   `SupportsSubscriptions`; webhooks map cleanly.

## Testing

If you have PHP + Composer installed locally:

```bash
composer install
vendor/bin/phpunit
```

### Or via Docker (no local PHP/Composer needed)

```bash
make install   # builds the image, runs composer install
make test      # runs vendor/bin/phpunit inside the container
make shell     # drop into a bash shell in the container
```

(No `make`? Run the underlying commands directly: `docker compose build`,
then `docker compose run --rm app composer install`, then
`docker compose run --rm app vendor/bin/phpunit`.) Requires Docker Desktop
(or another Docker engine) running.

`tests/Unit/PaymentManagerTest.php` uses Orchestra Testbench to boot a
minimal Laravel app and exercises the manager/facade without hitting
Braintree's API. `DataTransferObjectsTest.php` covers the plain-PHP value
objects with no framework bootstrap needed.

## Contributing

Issues and PRs are welcome.

1. Fork the repo and create a branch off `main`.
2. Run the test suite (`make test` or `vendor/bin/phpunit`) before and
   after your change — see [Testing](#testing) above.
3. Keep new gateway drivers behind the `PaymentGateway` contract; don't
   add gateway-specific methods to `PaymentManager` or the facade.
4. Open a PR describing the change and why it's needed.

## License

MIT — see [LICENSE](LICENSE). Free to use, modify, and redistribute,
including commercially, by any company or individual.
