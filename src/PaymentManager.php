<?php

namespace Payments;

use Payments\Contracts\PaymentGateway;
use Payments\Exceptions\PaymentException;
use Payments\Gateways\BraintreeGateway;
use Illuminate\Contracts\Foundation\Application;

/**
 * Resolves and caches PaymentGateway drivers by name, following the same
 * "manager" pattern Laravel itself uses for cache/queue/mail drivers.
 *
 * To add a new provider:
 *   1. Implement Payments\Contracts\PaymentGateway.
 *   2. Add a config/payments.php entry with a "driver" key.
 *   3. Register it with $manager->extend('your_driver', fn ($config) => new YourGateway($config));
 *      (e.g. in a service provider's boot method) — no changes needed here.
 */
class PaymentManager
{
    private array $gateways = [];

    private array $customCreators = [];

    public function __construct(private Application $app)
    {
    }

    public function gateway(?string $name = null): PaymentGateway
    {
        $name = $name ?? $this->getDefaultGateway();

        return $this->gateways[$name] ??= $this->resolve($name);
    }

    /**
     * Register a custom driver factory, keyed by the "driver" value used
     * in config/payments.php (not the gateway name itself, so multiple
     * named configs can share one driver implementation).
     */
    public function extend(string $driver, \Closure $factory): static
    {
        $this->customCreators[$driver] = $factory;

        return $this;
    }

    public function getDefaultGateway(): string
    {
        return $this->app['config']['payments.default'];
    }

    private function resolve(string $name): PaymentGateway
    {
        $config = $this->app['config']["payments.gateways.{$name}"];

        if (!$config) {
            throw PaymentException::gatewayNotConfigured($name);
        }

        $driver = $config['driver'] ?? $name;

        if (isset($this->customCreators[$driver])) {
            return $this->customCreators[$driver]($config);
        }

        return match ($driver) {
            'braintree' => new BraintreeGateway($config),
            default => throw PaymentException::unsupportedDriver($driver),
        };
    }
}
