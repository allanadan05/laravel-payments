<?php

namespace Payments\Contracts;

use Payments\DataTransferObjects\WebhookEvent;

/**
 * Optional capability for gateways that push async event notifications.
 *
 * Implement it only if the underlying gateway has a webhook mechanism, and
 * check with `$gateway instanceof SupportsWebhooks` before calling these
 * methods.
 */
interface SupportsWebhooks
{
    /**
     * Respond to the gateway's endpoint-verification handshake (e.g. the
     * GET request Braintree sends when a webhook URL is first configured).
     */
    public function verifyWebhook(string $challenge): string;

    /**
     * Verify and decode an incoming webhook request body into a normalized
     * event. Throws PaymentException if the signature is invalid.
     */
    public function parseWebhook(string $signature, string $payload): WebhookEvent;
}
