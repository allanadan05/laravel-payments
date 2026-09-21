<?php

namespace Payments\Gateways;

use Braintree\CreditCard;
use Braintree\CustomerSearch;
use Braintree\Gateway as BraintreeSdkGateway;
use Braintree\PayPalAccount;
use Braintree\TransactionSearch;
use Payments\Contracts\PaymentGateway;
use Payments\Contracts\SupportsCustomerListing;
use Payments\Contracts\SupportsReporting;
use Payments\Contracts\SupportsStoredPaymentMethods;
use Payments\Contracts\SupportsSubscriptions;
use Payments\Contracts\SupportsWebhooks;
use Payments\DataTransferObjects\ChargeRequest;
use Payments\DataTransferObjects\ChargeResult;
use Payments\DataTransferObjects\CustomerResult;
use Payments\DataTransferObjects\PaymentMethodResult;
use Payments\DataTransferObjects\RefundResult;
use Payments\DataTransferObjects\SubscriptionRequest;
use Payments\DataTransferObjects\SubscriptionResult;
use Payments\DataTransferObjects\WebhookEvent;
use Payments\Exceptions\PaymentException;
use Throwable;

class BraintreeGateway implements
    PaymentGateway,
    SupportsSubscriptions,
    SupportsWebhooks,
    SupportsStoredPaymentMethods,
    SupportsReporting,
    SupportsCustomerListing
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

    public function subscribe(SubscriptionRequest $request): SubscriptionResult
    {
        try {
            $params = [
                'planId' => $request->planId,
                'paymentMethodToken' => $request->paymentMethodToken,
            ];

            if ($request->price !== null) {
                $params['price'] = $request->price;
            }

            if ($request->id !== null) {
                $params['id'] = $request->id;
            }

            $result = $this->gateway->subscription()->create($params);

            if ($result->success) {
                return $this->toSubscriptionResult($result->subscription);
            }

            return SubscriptionResult::failure(
                $result->message ?? 'Braintree subscription creation failed.',
                ['errors' => $this->flattenErrors($result)],
            );
        } catch (Throwable $e) {
            throw PaymentException::fromGatewayError('braintree', $e->getMessage(), $e);
        }
    }

    public function findSubscription(string $subscriptionId): SubscriptionResult
    {
        try {
            return $this->toSubscriptionResult($this->gateway->subscription()->find($subscriptionId));
        } catch (Throwable $e) {
            throw PaymentException::fromGatewayError('braintree', $e->getMessage(), $e);
        }
    }

    public function updateSubscription(string $subscriptionId, array $attributes): SubscriptionResult
    {
        try {
            $result = $this->gateway->subscription()->update($subscriptionId, $attributes);

            if ($result->success) {
                return $this->toSubscriptionResult($result->subscription);
            }

            return SubscriptionResult::failure($result->message ?? 'Braintree subscription update failed.');
        } catch (Throwable $e) {
            throw PaymentException::fromGatewayError('braintree', $e->getMessage(), $e);
        }
    }

    public function cancelSubscription(string $subscriptionId): SubscriptionResult
    {
        try {
            $result = $this->gateway->subscription()->cancel($subscriptionId);

            if ($result->success) {
                return $this->toSubscriptionResult($result->subscription);
            }

            return SubscriptionResult::failure($result->message ?? 'Braintree subscription cancellation failed.');
        } catch (Throwable $e) {
            throw PaymentException::fromGatewayError('braintree', $e->getMessage(), $e);
        }
    }

    public function verifyWebhook(string $challenge): string
    {
        try {
            return $this->gateway->webhookNotification()->verify($challenge);
        } catch (Throwable $e) {
            throw PaymentException::fromGatewayError('braintree', $e->getMessage(), $e);
        }
    }

    public function parseWebhook(string $signature, string $payload): WebhookEvent
    {
        try {
            $notification = $this->gateway->webhookNotification()->parse($signature, $payload);

            return new WebhookEvent(
                kind: $notification->kind,
                timestamp: isset($notification->timestamp) ? (string) $notification->timestamp : null,
                subject: (array) $notification->subject,
                raw: (array) $notification,
            );
        } catch (Throwable $e) {
            throw PaymentException::fromGatewayError('braintree', $e->getMessage(), $e);
        }
    }

    public function addPaymentMethod(string $customerId, string $paymentMethodNonce, array $options = []): PaymentMethodResult
    {
        try {
            $params = array_merge($options, [
                'customerId' => $customerId,
                'paymentMethodNonce' => $paymentMethodNonce,
            ]);

            $result = $this->gateway->paymentMethod()->create($params);

            if ($result->success) {
                return $this->toPaymentMethodResult($result->paymentMethod);
            }

            return PaymentMethodResult::failure($result->message ?? 'Braintree payment method creation failed.');
        } catch (Throwable $e) {
            throw PaymentException::fromGatewayError('braintree', $e->getMessage(), $e);
        }
    }

    public function listPaymentMethods(string $customerId): array
    {
        try {
            $customer = $this->gateway->customer()->find($customerId);

            return array_map(
                fn ($paymentMethod) => $this->toPaymentMethodResult($paymentMethod),
                $customer->paymentMethods,
            );
        } catch (Throwable $e) {
            throw PaymentException::fromGatewayError('braintree', $e->getMessage(), $e);
        }
    }

    public function defaultPaymentMethod(string $customerId): ?PaymentMethodResult
    {
        try {
            $paymentMethod = $this->gateway->customer()->find($customerId)->defaultPaymentMethod();

            return $paymentMethod ? $this->toPaymentMethodResult($paymentMethod) : null;
        } catch (Throwable $e) {
            throw PaymentException::fromGatewayError('braintree', $e->getMessage(), $e);
        }
    }

    public function deletePaymentMethod(string $token): bool
    {
        try {
            $this->gateway->paymentMethod()->delete($token);

            return true;
        } catch (Throwable $e) {
            throw PaymentException::fromGatewayError('braintree', $e->getMessage(), $e);
        }
    }

    public function findTransaction(string $transactionId): ChargeResult
    {
        try {
            return $this->toChargeResult($this->gateway->transaction()->find($transactionId));
        } catch (Throwable $e) {
            throw PaymentException::fromGatewayError('braintree', $e->getMessage(), $e);
        }
    }

    /**
     * $criteria supports exact-match lookups on: id, customer_id, order_id,
     * status. Unknown keys throw, so typos fail loudly instead of silently
     * matching everything.
     *
     * @return ChargeResult[]
     */
    public function searchTransactions(array $criteria): array
    {
        try {
            $collection = $this->gateway->transaction()->search(
                $this->buildSearchNodes($criteria, [
                    'id' => fn ($v) => TransactionSearch::id()->is($v),
                    'customer_id' => fn ($v) => TransactionSearch::customerId()->is($v),
                    'order_id' => fn ($v) => TransactionSearch::orderId()->is($v),
                    'status' => fn ($v) => TransactionSearch::status()->in((array) $v),
                ]),
            );

            return array_map(fn ($transaction) => $this->toChargeResult($transaction), iterator_to_array($collection));
        } catch (Throwable $e) {
            throw PaymentException::fromGatewayError('braintree', $e->getMessage(), $e);
        }
    }

    public function findCustomer(string $customerId): CustomerResult
    {
        try {
            $customer = $this->gateway->customer()->find($customerId);

            return CustomerResult::success($customer->id, (array) $customer);
        } catch (Throwable $e) {
            throw PaymentException::fromGatewayError('braintree', $e->getMessage(), $e);
        }
    }

    /**
     * With no $criteria, lists every customer. Otherwise supports
     * exact-match lookups on: id, email, first_name, last_name, company.
     * Unknown keys throw, so typos fail loudly instead of silently
     * matching everything.
     *
     * @return CustomerResult[]
     */
    public function listCustomers(array $criteria = []): array
    {
        try {
            $collection = $criteria === []
                ? $this->gateway->customer()->all()
                : $this->gateway->customer()->search($this->buildSearchNodes($criteria, [
                    'id' => fn ($v) => CustomerSearch::id()->is($v),
                    'email' => fn ($v) => CustomerSearch::email()->is($v),
                    'first_name' => fn ($v) => CustomerSearch::firstName()->is($v),
                    'last_name' => fn ($v) => CustomerSearch::lastName()->is($v),
                    'company' => fn ($v) => CustomerSearch::company()->is($v),
                ]));

            return array_map(
                fn ($customer) => CustomerResult::success($customer->id, (array) $customer),
                iterator_to_array($collection),
            );
        } catch (Throwable $e) {
            throw PaymentException::fromGatewayError('braintree', $e->getMessage(), $e);
        }
    }

    private function toSubscriptionResult($subscription): SubscriptionResult
    {
        return SubscriptionResult::success($subscription->id, $subscription->status, $subscription->planId, (array) $subscription);
    }

    private function toChargeResult($transaction): ChargeResult
    {
        return ChargeResult::success(
            transactionId: $transaction->id,
            status: $transaction->status,
            amount: (string) $transaction->amount,
            currency: $transaction->currencyIsoCode ?? '',
            raw: (array) $transaction,
        );
    }

    private function toPaymentMethodResult($paymentMethod): PaymentMethodResult
    {
        $type = match (true) {
            $paymentMethod instanceof CreditCard => 'credit_card',
            $paymentMethod instanceof PayPalAccount => 'paypal_account',
            default => 'unknown',
        };

        return PaymentMethodResult::success(
            $paymentMethod->token,
            $type,
            (bool) ($paymentMethod->default ?? false),
            (array) $paymentMethod,
        );
    }

    /**
     * Translate a flat criteria array into Braintree search nodes using the
     * given field builders, rejecting any key without a matching builder.
     */
    private function buildSearchNodes(array $criteria, array $fieldBuilders): array
    {
        $nodes = [];

        foreach ($criteria as $key => $value) {
            if (!isset($fieldBuilders[$key])) {
                throw new PaymentException("Unsupported search criteria key [{$key}] for braintree.");
            }

            $nodes[] = $fieldBuilders[$key]($value);
        }

        return $nodes;
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
