<?php

declare(strict_types=1);

namespace App\Providers;

use App\Contracts\Services\AuthServiceInterface;
use App\Models\Review;
use App\Policies\ReviewPolicy;
use App\Services\Auth\AuthenticationService;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Auth Service
        $this->app->bind(AuthServiceInterface::class, AuthenticationService::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Configure default password rules
        Password::defaults(function () {
            return Password::min(8)
                ->mixedCase()
                ->numbers()
                ->symbols()
                ->uncompromised();
        });

        // Register policies
        Gate::policy(Review::class, ReviewPolicy::class);

        // Force HTTPS in production
        if ($this->app->environment('production')) {
            URL::forceScheme('https');
        }
    }
}
