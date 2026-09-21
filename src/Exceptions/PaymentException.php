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

    public static function fromGatewayError(string $gateway, string $message, ?Throwable $previous = null): self
    {
        return new self("[{$gateway}] payment error: {$message}", 0, $previous);
    }
}
