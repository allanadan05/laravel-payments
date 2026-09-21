<?php

namespace Payments\DataTransferObjects;

final class CustomerResult
{
    public function __construct(
        public readonly bool $successful,
        public readonly ?string $customerId,
        public readonly ?string $message = null,
        public readonly array $raw = [],
    ) {
    }

    public static function success(string $customerId, array $raw = []): self
    {
        return new self(true, $customerId, null, $raw);
    }

    public static function failure(string $message, array $raw = []): self
    {
        return new self(false, null, $message, $raw);
    }
}
