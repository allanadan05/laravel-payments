<?php

namespace Payments\DataTransferObjects;

/**
 * A normalized incoming webhook notification. "kind" and "subject" are
 * driver-specific strings/shapes (e.g. Braintree's "subscription_canceled"),
 * passed through as-is rather than mapped to a common vocabulary.
 */
final class WebhookEvent
{
    public function __construct(
        public readonly string $kind,
        public readonly ?string $timestamp,
        public readonly array $subject = [],
        public readonly array $raw = [],
    ) {
    }
}
