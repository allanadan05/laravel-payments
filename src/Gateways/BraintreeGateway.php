<?php

namespace Payments\Gateways;

use Braintree\Gateway as BraintreeSdkGateway;
use Payments\Contracts\PaymentGateway;
use Payments\DataTransferObjects\ChargeRequest;
use Payments\DataTransferObjects\ChargeResult;
use Payments\DataTransferObjects\CustomerResult;
use Payments\DataTransferObjects\RefundResult;
use Payments\Exceptions\PaymentException;
use Throwable;

class BraintreeGateway implements PaymentGateway
{
    private BraintreeSdkGateway $gateway;

    public function __construct(array $config)
    {
        foreach (['merchant_id', 'public_key', 'private_key'] as $key) {
            if (empty($config[$key])) {
                throw PaymentException::gatewayNotConfigured('braintree');
            }
        }

        $this->gateway = new BraintreeSdkGateway([
            'environment' => $config['environment'] ?? 'sandbox',
            'merchantId' => $config['merchant_id'],
            'publicKey' => $config['public_key'],
            'privateKey' => $config['private_key'],
        ]);
    }

    public function name(): string
    {
        return 'braintree';
    }

    public function generateClientToken(?string $customerId = null): ?string
    {
        try {
            $params = $customerId ? ['customerId' => $customerId] : [];

            return $this->gateway->clientToken()->generate($params);
        } catch (Throwable $e) {
            throw PaymentException::fromGatewayError('braintree', $e->getMessage(), $e);
        }
    }

    public function charge(ChargeRequest $request): ChargeResult
    {
        try {
            $params = [
                'amount' => $request->amount,
                'paymentMethodNonce' => $request->paymentMethodNonceOrToken,
                'options' => [
                    'submitForSettlement' => $request->submitForSettlement,
                ],
            ];

            if ($request->customerId) {
                $params['customerId'] = $request->customerId;
            }

            if ($request->orderId) {
                $params['orderId'] = $request->orderId;
            }

            $result = $this->gateway->transaction()->sale($params);

            if ($result->success) {
                $tx = $result->transaction;

                return ChargeResult::success(
                    transactionId: $tx->id,
                    status: $tx->status,
                    amount: (string) $tx->amount,
                    currency: $tx->currencyIsoCode ?? '',
                    raw: (array) $tx,
                );
            }

            return ChargeResult::failure(
                message: $result->message ?? 'Braintree transaction declined.',
                raw: ['errors' => $this->flattenErrors($result)],
            );
        } catch (Throwable $e) {
            throw PaymentException::fromGatewayError('braintree', $e->getMessage(), $e);
        }
    }

    public function refund(string $transactionId, ?string $amount = null): RefundResult
    {
        try {
            $result = $amount !== null
                ? $this->gateway->transaction()->refund($transactionId, $amount)
                : $this->gateway->transaction()->refund($transactionId);

            if ($result->success) {
                return RefundResult::success(
                    refundTransactionId: $result->transaction->id,
                    status: $result->transaction->status,
                    raw: (array) $result->transaction,
                );
            }

            return RefundResult::failure($result->message ?? 'Braintree refund failed.');
        } catch (Throwable $e) {
            throw PaymentException::fromGatewayError('braintree', $e->getMessage(), $e);
        }
    }

    public function void(string $transactionId): RefundResult
    {
        try {
            $result = $this->gateway->transaction()->void($transactionId);

            if ($result->success) {
                return RefundResult::success(
                    refundTransactionId: $result->transaction->id,
                    status: $result->transaction->status,
                    raw: (array) $result->transaction,
                );
            }

            return RefundResult::failure($result->message ?? 'Braintree void failed.');
        } catch (Throwable $e) {
            throw PaymentException::fromGatewayError('braintree', $e->getMessage(), $e);
        }
    }

    public function createCustomer(array $attributes, ?string $paymentMethodNonce = null): CustomerResult
    {
        try {
            $params = $attributes;

            if ($paymentMethodNonce) {
                $params['paymentMethodNonce'] = $paymentMethodNonce;
            }

            $result = $this->gateway->customer()->create($params);

            if ($result->success) {
                return CustomerResult::success($result->customer->id, (array) $result->customer);
            }

            return CustomerResult::failure($result->message ?? 'Braintree customer creation failed.');
        } catch (Throwable $e) {
            throw PaymentException::fromGatewayError('braintree', $e->getMessage(), $e);
        }
    }

    /**
     * Flatten Braintree's nested ValidationErrorCollection into a simple
     * array of "field: message" strings for logging/debugging.
     */
    private function flattenErrors($result): array
    {
        if (!isset($result->errors)) {
            return [];
        }

        $flat = [];

        foreach ($result->errors->deepAll() as $error) {
            $flat[] = sprintf('%s: %s', $error->attribute, $error->message);
        }

        return $flat;
    }
}
