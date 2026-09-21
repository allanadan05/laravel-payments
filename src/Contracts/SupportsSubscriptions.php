<?php

namespace Payments\Contracts;

use Payments\DataTransferObjects\SubscriptionRequest;
use Payments\DataTransferObjects\SubscriptionResult;

/**
 * Optional capability for gateways that support recurring billing.
 *
 * Not every provider has a subscriptions API, so this lives outside the
 * core PaymentGateway contract — implement it only if the underlying
 * gateway supports it, and check with `$gateway instanceof
 * SupportsSubscriptions` before calling these methods.
 */
interface SupportsSubscriptions
{
    public function subscribe(SubscriptionRequest $request): SubscriptionResult;

    public function findSubscription(string $subscriptionId): SubscriptionResult;

    /**
     * @param array $attributes Driver-specific update payload (e.g. price, planId).
     */
    public function updateSubscription(string $subscriptionId, array $attributes): SubscriptionResult;

    public function cancelSubscription(string $subscriptionId): SubscriptionResult;
}
