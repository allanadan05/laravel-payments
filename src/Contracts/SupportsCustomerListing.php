<?php

namespace Payments\Contracts;

use Payments\DataTransferObjects\CustomerResult;

/**
 * Optional capability for gateways that support fetching a single customer
 * or listing/searching the vaulted customer records.
 *
 * Implement it only if the underlying gateway supports it, and check with
 * `$gateway instanceof SupportsCustomerListing` before calling these
 * methods. The shape of $criteria in listCustomers() is driver-specific —
 * see the implementing class for supported keys.
 */
interface SupportsCustomerListing
{
    public function findCustomer(string $customerId): CustomerResult;

    /**
     * @return CustomerResult[]
     */
    public function listCustomers(array $criteria = []): array;
}
