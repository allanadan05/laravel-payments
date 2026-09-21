<?php

namespace Payments;

use Illuminate\Support\ServiceProvider;

class PaymentServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/payments.php', 'payments');

        $this->app->singleton(PaymentManager::class, function ($app) {
            return new PaymentManager($app);
        });

        $this->app->alias(PaymentManager::class, 'payments');
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../config/payments.php' => config_path('payments.php'),
            ], 'payments-config');
        }
    }

    public function provides(): array
    {
        return [PaymentManager::class, 'payments'];
    }
}
