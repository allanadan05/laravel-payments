<?php

namespace Payments\DataTransferObjects;

final class ChargeResult
{
    public function __construct(
        public readonly bool $successful,
        public readonly ?string $transactionId,
        public readonly ?string $status,
        public readonly ?string $amount,
        public readonly ?string $currency,
        public readonly ?string $message = null,
        public readonly array $raw = [],
    ) {
    }

    public static function success(
        string $transactionId,
        string $status,
        string $amount,
        string $currency,
        array $raw = []
    ): self {
        return new self(true, $transactionId, $status, $amount, $currency, null, $raw);
    }

    public static function failure(string $message, array $raw = []): self
    {
        return new self(false, null, null, null, null, $message, $raw);
    }
}
