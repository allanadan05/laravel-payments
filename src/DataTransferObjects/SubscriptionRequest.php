<?php

namespace Payments\DataTransferObjects;

/**
 * Gateway-agnostic representation of a "start a subscription" request.
 *
 * paymentMethodToken is a *stored* payment method token (see
 * SupportsStoredPaymentMethods::addPaymentMethod()), not a one-time nonce —
 * recurring billing requires a vaulted payment method.
 */
final class SubscriptionRequest
{
    public function __construct(
        public readonly string $planId,
        public readonly string $paymentMethodToken,
        public readonly ?string $price = null,
        public readonly ?string $id = null,
        public readonly array $options = [],
    ) {
    }

    public static function make(string $planId, string $paymentMethodToken, array $options = []): self
    {
        return new self(
            planId: $planId,
            paymentMethodToken: $paymentMethodToken,
            price: $options['price'] ?? null,
            id: $options['id'] ?? null,
            options: $options,
        );
    }
}
