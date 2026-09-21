<?php

namespace Payments\Tests;

use Payments\PaymentServiceProvider;
use Orchestra\Testbench\TestCase as OrchestraTestCase;

abstract class TestCase extends OrchestraTestCase
{
    protected function getPackageProviders($app): array
    {
        return [PaymentServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('payments.default', 'braintree');
        $app['config']->set('payments.gateways.braintree', [
            'driver' => 'braintree',
            'environment' => 'sandbox',
            'merchant_id' => 'test_merchant_id',
            'public_key' => 'test_public_key',
            'private_key' => 'test_private_key',
        ]);
    }
}
