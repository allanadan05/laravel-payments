<?php

namespace Payments\DataTransferObjects;

final class PaymentMethodResult
{
    public function __construct(
        public readonly bool $successful,
        public readonly ?string $token,
        public readonly ?string $type = null,
        public readonly bool $isDefault = false,
        public readonly ?string $message = null,
        public readonly array $raw = [],
    ) {
    }

    public static function success(
        string $token,
        ?string $type = null,
        bool $isDefault = false,
        array $raw = []
    ): self {
        return new self(true, $token, $type, $isDefault, null, $raw);
    }

    public static function failure(string $message, array $raw = []): self
    {
        return new self(false, null, null, false, $message, $raw);
    }
}
