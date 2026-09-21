<?php

namespace Payments\Tests\Unit;

use Payments\DataTransferObjects\ChargeRequest;
use Payments\DataTransferObjects\ChargeResult;
use Payments\DataTransferObjects\RefundResult;
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
}
