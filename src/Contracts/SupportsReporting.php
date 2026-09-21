<?php

namespace Payments\Contracts;

use Payments\DataTransferObjects\ChargeResult;

/**
 * Optional capability for gateways that support looking up and searching
 * past transactions.
 *
 * Implement it only if the underlying gateway supports it, and check with
 * `$gateway instanceof SupportsReporting` before calling these methods.
 * The shape of $criteria in searchTransactions() is driver-specific — see
 * the implementing class for supported keys.
 */
interface SupportsReporting
{
    public function findTransaction(string $transactionId): ChargeResult;

    /**
     * @return ChargeResult[]
     */
    public function searchTransactions(array $criteria): array;
}
