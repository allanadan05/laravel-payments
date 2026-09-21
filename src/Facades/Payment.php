<?php

namespace Payments\Facades;

use Payments\Contracts\PaymentGateway;
use Payments\PaymentManager;
use Illuminate\Support\Facades\Facade;

/**
 * @method static PaymentGateway gateway(?string $name = null)
 * @method static string getDefaultGateway()
 * @method static PaymentManager extend(string $driver, \Closure $factory)
 *
 * @see \Payments\PaymentManager
 */
class Payment extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return PaymentManager::class;
    }
}
