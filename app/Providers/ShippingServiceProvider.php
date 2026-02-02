<?php

declare(strict_types=1);

namespace App\Providers;

use App\Services\Shipping\AndreaniApiClient;
use App\Services\Shipping\AndreaniShippingProvider;
use App\Services\Shipping\Contracts\ShippingProviderInterface;
use App\Services\Shipping\ShippingService;
use Illuminate\Support\ServiceProvider;

/**
 * Service Provider for shipping services.
 * Follows Dependency Inversion Principle - binds abstractions to concrete implementations.
 */
class ShippingServiceProvider extends ServiceProvider
{
    /**
     * Register shipping services.
     */
    public function register(): void
    {
        // Register Andreani API Client as singleton
        $this->app->singleton(AndreaniApiClient::class, function ($app) {
            return new AndreaniApiClient(
                username: config('services.andreani.username'),
                password: config('services.andreani.password'),
            );
        });

        // Register Andreani Provider as singleton
        $this->app->singleton(AndreaniShippingProvider::class, function ($app) {
            return new AndreaniShippingProvider(
                apiClient: $app->make(AndreaniApiClient::class),
                contractNumber: config('services.andreani.contract_number'),
            );
        });

        // Bind interface to implementation (Dependency Inversion Principle)
        $this->app->bind(
            ShippingProviderInterface::class,
            AndreaniShippingProvider::class
        );

        // Register main ShippingService
        $this->app->singleton(ShippingService::class, function ($app) {
            return new ShippingService(
                provider: $app->make(ShippingProviderInterface::class),
            );
        });
    }

    /**
     * Bootstrap shipping services.
     */
    public function boot(): void
    {
        //
    }
}
