<?php

namespace Payments\Contracts;

use Payments\DataTransferObjects\ChargeRequest;
use Payments\DataTransferObjects\ChargeResult;
use Payments\DataTransferObjects\CustomerResult;
use Payments\DataTransferObjects\RefundResult;

/**
 * Every payment provider driver (Braintree, Stripe, Authorize.Net, ...)
 * implements this contract. Calling code (controllers, jobs, services)
 * should only ever depend on this interface, never on a concrete gateway
 * class — that's what makes swapping or adding providers a config change
 * instead of a rewrite.
 */
interface PaymentGateway
{
    /**
     * A short, unique identifier for this gateway, e.g. "braintree".
     */
    public function name(): string;

    /**
     * Generate a client-side token (used by Braintree's Drop-in / hosted
     * fields UI, or the equivalent for another provider). Returns null for
     * gateways that don't use this pattern.
     */
    public function generateClientToken(?string $customerId = null): ?string;

    /**
     * Charge a payment method (nonce or stored token) for the given amount.
     */
    public function charge(ChargeRequest $request): ChargeResult;

    /**
     * Refund a previously settled/submitted transaction. Pass null amount
     * to refund in full.
     */
    public function refund(string $transactionId, ?string $amount = null): RefundResult;

    /**
     * Void a transaction that has not yet settled.
     */
    public function void(string $transactionId): RefundResult;

    /**
     * Create a customer record on the gateway, optionally vaulting a
     * payment method against it via the nonce.
     */
    public function createCustomer(array $attributes, ?string $paymentMethodNonce = null): CustomerResult;
}
