<?php

namespace Payments\Tests\Unit;

use Payments\DataTransferObjects\ChargeRequest;
use Payments\DataTransferObjects\ChargeResult;
use Payments\DataTransferObjects\PaymentMethodResult;
use Payments\DataTransferObjects\RefundResult;
use Payments\DataTransferObjects\SubscriptionRequest;
use Payments\DataTransferObjects\SubscriptionResult;
use Payments\DataTransferObjects\WebhookEvent;
use PHPUnit\Framework\TestCase;

class DataTransferObjectsTest extends TestCase
{
    public function test_charge_request_make_applies_defaults(): void
    {
        $request = ChargeRequest::make('19.99', 'fake-nonce');

        $this->assertSame('19.99', $request->amount);
        $this->assertSame('fake-nonce', $request->paymentMethodNonceOrToken);
        $this->assertTrue($request->submitForSettlement);
        $this->assertNull($request->customerId);
    }

    public function test_charge_request_make_honors_overrides(): void
    {
        $request = ChargeRequest::make('5.00', 'fake-nonce', [
            'customer_id' => 'cust_1',
            'order_id' => 'order_1',
            'submit_for_settlement' => false,
        ]);

        $this->assertSame('cust_1', $request->customerId);
        $this->assertSame('order_1', $request->orderId);
        $this->assertFalse($request->submitForSettlement);
    }

    public function test_charge_result_success_and_failure_shapes(): void
    {
        $success = ChargeResult::success('tx_1', 'settled', '10.00', 'USD');
        $this->assertTrue($success->successful);
        $this->assertSame('tx_1', $success->transactionId);

        $failure = ChargeResult::failure('Declined');
        $this->assertFalse($failure->successful);
        $this->assertSame('Declined', $failure->message);
        $this->assertNull($failure->transactionId);
    }

    public function test_refund_result_success_and_failure_shapes(): void
    {
        $success = RefundResult::success('refund_1', 'settled');
        $this->assertTrue($success->successful);

        $failure = RefundResult::failure('Cannot refund');
        $this->assertFalse($failure->successful);
    }

    public function test_subscription_request_make_applies_defaults(): void
    {
        $request = SubscriptionRequest::make('plan_1', 'pm_token_1');

        $this->assertSame('plan_1', $request->planId);
        $this->assertSame('pm_token_1', $request->paymentMethodToken);
        $this->assertNull($request->price);
        $this->assertNull($request->id);
    }

    public function test_subscription_request_make_honors_overrides(): void
    {
        $request = SubscriptionRequest::make('plan_1', 'pm_token_1', [
            'price' => '9.99',
            'id' => 'sub_custom_id',
        ]);

        $this->assertSame('9.99', $request->price);
        $this->assertSame('sub_custom_id', $request->id);
    }

    public function test_subscription_result_success_and_failure_shapes(): void
    {
        $success = SubscriptionResult::success('sub_1', 'active', 'plan_1');
        $this->assertTrue($success->successful);
        $this->assertSame('sub_1', $success->subscriptionId);
        $this->assertSame('plan_1', $success->planId);

        $failure = SubscriptionResult::failure('Invalid plan');
        $this->assertFalse($failure->successful);
        $this->assertSame('Invalid plan', $failure->message);
        $this->assertNull($failure->subscriptionId);
    }

    public function test_payment_method_result_success_and_failure_shapes(): void
    {
        $success = PaymentMethodResult::success('token_1', 'credit_card', true);
        $this->assertTrue($success->successful);
        $this->assertSame('token_1', $success->token);
        $this->assertTrue($success->isDefault);

        $failure = PaymentMethodResult::failure('Invalid nonce');
        $this->assertFalse($failure->successful);
        $this->assertFalse($failure->isDefault);
        $this->assertNull($failure->token);
    }

    public function test_webhook_event_exposes_kind_and_subject(): void
    {
        $event = new WebhookEvent(
            kind: 'subscription_canceled',
            timestamp: '2026-01-01T00:00:00Z',
            subject: ['id' => 'sub_1'],
        );

        $this->assertSame('subscription_canceled', $event->kind);
        $this->assertSame(['id' => 'sub_1'], $event->subject);
    }
}
