<?php

namespace Payments\Exceptions;

use RuntimeException;
use Throwable;

class PaymentException extends RuntimeException
{
    public static function gatewayNotConfigured(string $gateway): self
    {
        return new self("Payment gateway [{$gateway}] is not configured. Check config/payments.php and your .env.");
    }

    public static function unsupportedDriver(string $driver): self
    {
        return new self("No payment gateway driver registered for [{$driver}].");
    }

    /**
     * Throw from calling code after an `instanceof` capability check fails,
     * e.g. `if (!$gateway instanceof SupportsSubscriptions) { throw
     * PaymentException::capabilityNotSupported($gateway->name(),
     * SupportsSubscriptions::class); }`.
     */
    public static function capabilityNotSupported(string $gateway, string $capability): self
    {
        return new self("Payment gateway [{$gateway}] does not implement [{$capability}].");
    }

    public static function fromGatewayError(string $gateway, string $message, ?Throwable $previous = null): self
    {
        return new self("[{$gateway}] payment error: {$message}", 0, $previous);
    }
}
