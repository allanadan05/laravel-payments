<?php

namespace Payments\DataTransferObjects;

final class RefundResult
{
    public function __construct(
        public readonly bool $successful,
        public readonly ?string $refundTransactionId,
        public readonly ?string $status,
        public readonly ?string $message = null,
        public readonly array $raw = [],
    ) {
    }

    public static function success(string $refundTransactionId, string $status, array $raw = []): self
    {
        return new self(true, $refundTransactionId, $status, null, $raw);
    }

    public static function failure(string $message, array $raw = []): self
    {
        return new self(false, null, null, $message, $raw);
    }
}
