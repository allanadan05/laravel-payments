# Laravel Payments

[![License: MIT](https://img.shields.io/badge/license-MIT-green.svg)](LICENSE)
[![Packagist](https://img.shields.io/packagist/v/allanadan05/laravel-payments.svg)](https://packagist.org/packages/allanadan05/laravel-payments)

A small, gateway-agnostic payment integration module for Laravel. It ships
with a working Braintree driver, but calling code never talks to Braintree
directly — everything goes through the `PaymentGateway` contract, so adding
Stripe, Authorize.Net, etc. later is a new driver class + a config entry,
not a rewrite of your controllers.

Framework-agnostic in design, Laravel-native in integration — any company
can drop this into an existing Laravel app without touching this repo's
code: implement one interface for your own gateway, or use the bundled
Braintree driver as-is.

## Requirements

- PHP ^8.1
- Laravel (`illuminate/support`) ^9.0, ^10.0, or ^11.0
- A Braintree account, if using the bundled driver (see [Braintree account
  setup](#braintree-account-setup-manual-step) below)

## Install

Once published to [Packagist](https://packagist.org):

```bash
composer require allanadan05/laravel-payments
php artisan vendor:publish --tag=payments-config
```

### Before it's on Packagist

Point Composer at the GitHub repo directly by adding a `repositories` entry
to your app's `composer.json`, then requiring it as normal:

```json
{
    "repositories": [
        {
            "type": "vcs",
            "url": "https://github.com/allanadan05/laravel-payments"
        }
    ]
}
```

```bash
composer require allanadan05/laravel-payments:^1.0
php artisan vendor:publish --tag=payments-config
```

Laravel's package auto-discovery registers the service provider and
`Payment` facade automatically — no manual registration needed.

Add credentials to `.env`:

```
PAYMENT_GATEWAY=braintree
BRAINTREE_ENVIRONMENT=sandbox
BRAINTREE_MERCHANT_ID=your_merchant_id
BRAINTREE_PUBLIC_KEY=your_public_key
BRAINTREE_PRIVATE_KEY=your_private_key
PAYMENT_CURRENCY=USD
```

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

## Structure

```
src/
├── PaymentServiceProvider.php        Registers the manager singleton, merges config, publishes it
├── PaymentManager.php                Resolves/caches gateways by name; extend() registers new drivers
├── Facades/
│   └── Payment.php                   Payment::gateway(...) — thin static wrapper over PaymentManager
├── Contracts/
│   └── PaymentGateway.php            Interface every driver implements; depend on this, not a concrete class
├── Gateways/
│   └── BraintreeGateway.php          Bundled driver; implements PaymentGateway using the Braintree SDK
├── DataTransferObjects/
│   ├── ChargeRequest.php             Gateway-agnostic input for a charge (amount as string, nonce/token, ...)
│   ├── ChargeResult.php              Outcome of a charge: success flag, transactionId, status, message
│   ├── RefundResult.php              Outcome of a refund/void
│   └── CustomerResult.php            Outcome of vaulting a customer + payment method
└── Exceptions/
    └── PaymentException.php          Thrown for unconfigured/unsupported gateways and gateway-side errors
```

`config/payments.php` maps gateway names to driver + credentials; `tests/`
mirrors this layout (`tests/Unit/PaymentManagerTest.php`,
`tests/Unit/DataTransferObjectsTest.php`).

## Adding another gateway later

1. Implement `Payments\Contracts\PaymentGateway` (see
   `src/Gateways/BraintreeGateway.php` for the shape).
2. Add a `config/payments.php` entry under `gateways` with a `driver` key.
3. Register the driver, typically in your `AppServiceProvider::boot()`:

```php
app(\Payments\PaymentManager::class)
    ->extend('stripe', fn (array $config) => new \App\Payments\StripeGateway($config));
```

No changes needed to `PaymentManager`, the facade, or any calling code.

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

## Publishing to Packagist

The steps below only need to happen once, by the repo owner:

1. Push a tagged release: `git tag v1.0.0 && git push origin v1.0.0`
   (Packagist reads versions from git tags, not `composer.json`.)
2. Sign in at [packagist.org](https://packagist.org) with your GitHub
   account and click **Submit**, then paste this repo's URL
   (`https://github.com/allanadan05/laravel-payments`).
3. On the package's Packagist page, open **Settings** and enable the
   GitHub Service Hook (or add the Packagist webhook under the GitHub
   repo's **Settings → Webhooks**) so new tags auto-publish without a
   manual "update" click.

After that, anyone can `composer require allanadan05/laravel-payments`
with no extra configuration.

## Contributing

Issues and PRs are welcome.

1. Fork the repo and create a branch off `main`.
2. Run the test suite (`make test` or `vendor/bin/phpunit`) before and
   after your change — see [Testing](#testing) below.
3. Keep new gateway drivers behind the `PaymentGateway` contract; don't
   add gateway-specific methods to `PaymentManager` or the facade.
4. Open a PR describing the change and why it's needed.

## License

MIT — see [LICENSE](LICENSE). Free to use, modify, and redistribute,
including commercially, by any company or individual.

## Braintree account setup (manual step)

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
