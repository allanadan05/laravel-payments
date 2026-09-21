<?php

namespace Payments\DataTransferObjects;

/**
 * Gateway-agnostic representation of a "charge this payment method" request.
 *
 * amount is always a string to avoid float rounding issues; pass "19.99",
 * not 19.99.
 */
final class ChargeRequest
{
    public function __construct(
        public readonly string $amount,
        public readonly string $paymentMethodNonceOrToken,
        public readonly ?string $currency = null,
        public readonly ?string $customerId = null,
        public readonly ?string $orderId = null,
        public readonly ?string $description = null,
        public readonly bool $submitForSettlement = true,
        public readonly array $metadata = [],
    ) {
    }

    public static function make(string $amount, string $paymentMethodNonceOrToken, array $options = []): self
    {
        return new self(
            amount: $amount,
            paymentMethodNonceOrToken: $paymentMethodNonceOrToken,
            currency: $options['currency'] ?? null,
            customerId: $options['customer_id'] ?? null,
            orderId: $options['order_id'] ?? null,
            description: $options['description'] ?? null,
            submitForSettlement: $options['submit_for_settlement'] ?? true,
            metadata: $options['metadata'] ?? [],
        );
    }
}
