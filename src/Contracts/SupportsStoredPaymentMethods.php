<?php

namespace Payments\Contracts;

use Payments\DataTransferObjects\PaymentMethodResult;

/**
 * Optional capability for gateways that let a customer's vaulted payment
 * methods be listed/added/removed independently of taking a charge.
 *
 * Implement it only if the underlying gateway supports it, and check with
 * `$gateway instanceof SupportsStoredPaymentMethods` before calling these
 * methods.
 */
interface SupportsStoredPaymentMethods
{
    public function addPaymentMethod(string $customerId, string $paymentMethodNonce, array $options = []): PaymentMethodResult;

    /**
     * @return PaymentMethodResult[]
     */
    public function listPaymentMethods(string $customerId): array;

    public function defaultPaymentMethod(string $customerId): ?PaymentMethodResult;

    public function deletePaymentMethod(string $token): bool;
}
