<?php

namespace Payments\DataTransferObjects;

final class SubscriptionResult
{
    public function __construct(
        public readonly bool $successful,
        public readonly ?string $subscriptionId,
        public readonly ?string $status,
        public readonly ?string $planId = null,
        public readonly ?string $message = null,
        public readonly array $raw = [],
    ) {
    }

    public static function success(
        string $subscriptionId,
        string $status,
        ?string $planId = null,
        array $raw = []
    ): self {
        return new self(true, $subscriptionId, $status, $planId, null, $raw);
    }

    public static function failure(string $message, array $raw = []): self
    {
        return new self(false, null, null, null, $message, $raw);
    }
}
