<?php

namespace Payments\Tests\Unit;

use Payments\Contracts\PaymentGateway;
use Payments\Contracts\SupportsCustomerListing;
use Payments\Contracts\SupportsReporting;
use Payments\Contracts\SupportsStoredPaymentMethods;
use Payments\Contracts\SupportsSubscriptions;
use Payments\Contracts\SupportsWebhooks;
use Payments\Exceptions\PaymentException;
use Payments\Facades\Payment;
use Payments\PaymentManager;
use Payments\Tests\TestCase;

class PaymentManagerTest extends TestCase
{
    public function test_it_resolves_the_default_gateway_from_config(): void
    {
        $gateway = Payment::gateway();

        $this->assertInstanceOf(PaymentGateway::class, $gateway);
        $this->assertSame('braintree', $gateway->name());
    }

    public function test_it_caches_resolved_gateways(): void
    {
        $manager = $this->app->make(PaymentManager::class);

        $this->assertSame($manager->gateway('braintree'), $manager->gateway('braintree'));
    }

    public function test_it_throws_when_gateway_is_not_configured(): void
    {
        $this->expectException(PaymentException::class);

        Payment::gateway('does_not_exist');
    }

    public function test_it_allows_registering_a_custom_driver(): void
    {
        $manager = $this->app->make(PaymentManager::class);

        $this->app['config']->set('payments.gateways.fake', ['driver' => 'fake']);

        $fake = new class implements PaymentGateway {
            public function name(): string
            {
                return 'fake';
            }

            public function generateClientToken(?string $customerId = null): ?string
            {
                return 'fake-token';
            }

            public function charge(\Payments\DataTransferObjects\ChargeRequest $request): \Payments\DataTransferObjects\ChargeResult
            {
                return \Payments\DataTransferObjects\ChargeResult::success('tx_1', 'settled', $request->amount, 'USD');
            }

            public function refund(string $transactionId, ?string $amount = null): \Payments\DataTransferObjects\RefundResult
            {
                return \Payments\DataTransferObjects\RefundResult::success('refund_1', 'settled');
            }

            public function void(string $transactionId): \Payments\DataTransferObjects\RefundResult
            {
                return \Payments\DataTransferObjects\RefundResult::success($transactionId, 'voided');
            }

            public function createCustomer(array $attributes, ?string $paymentMethodNonce = null): \Payments\DataTransferObjects\CustomerResult
            {
                return \Payments\DataTransferObjects\CustomerResult::success('cust_1');
            }
        };

        $manager->extend('fake', fn () => $fake);

        $this->assertSame($fake, $manager->gateway('fake'));
    }

    public function test_braintree_gateway_implements_all_optional_capabilities(): void
    {
        $gateway = Payment::gateway('braintree');

        $this->assertInstanceOf(SupportsSubscriptions::class, $gateway);
        $this->assertInstanceOf(SupportsWebhooks::class, $gateway);
        $this->assertInstanceOf(SupportsStoredPaymentMethods::class, $gateway);
        $this->assertInstanceOf(SupportsReporting::class, $gateway);
        $this->assertInstanceOf(SupportsCustomerListing::class, $gateway);
    }

    public function test_a_minimal_gateway_supports_no_optional_capabilities(): void
    {
        $manager = $this->app->make(PaymentManager::class);

        $this->app['config']->set('payments.gateways.fake', ['driver' => 'fake']);

        $fake = new class implements PaymentGateway {
            public function name(): string
            {
                return 'fake';
            }

            public function generateClientToken(?string $customerId = null): ?string
            {
                return null;
            }

            public function charge(\Payments\DataTransferObjects\ChargeRequest $request): \Payments\DataTransferObjects\ChargeResult
            {
                return \Payments\DataTransferObjects\ChargeResult::success('tx_1', 'settled', $request->amount, 'USD');
            }

            public function refund(string $transactionId, ?string $amount = null): \Payments\DataTransferObjects\RefundResult
            {
                return \Payments\DataTransferObjects\RefundResult::success('refund_1', 'settled');
            }

            public function void(string $transactionId): \Payments\DataTransferObjects\RefundResult
            {
                return \Payments\DataTransferObjects\RefundResult::success($transactionId, 'voided');
            }

            public function createCustomer(array $attributes, ?string $paymentMethodNonce = null): \Payments\DataTransferObjects\CustomerResult
            {
                return \Payments\DataTransferObjects\CustomerResult::success('cust_1');
            }
        };

        $manager->extend('fake', fn () => $fake);

        $gateway = $manager->gateway('fake');

        $this->assertNotInstanceOf(SupportsSubscriptions::class, $gateway);
        $this->assertNotInstanceOf(SupportsWebhooks::class, $gateway);
        $this->assertNotInstanceOf(SupportsStoredPaymentMethods::class, $gateway);
        $this->assertNotInstanceOf(SupportsReporting::class, $gateway);
        $this->assertNotInstanceOf(SupportsCustomerListing::class, $gateway);
    }
}
