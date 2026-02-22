<?php

namespace App\Providers;

use App\Domain\Enums\UserRole;
use Illuminate\Support\Facades\Gate;
use Laravel\Horizon\HorizonApplicationServiceProvider;

class HorizonServiceProvider extends HorizonApplicationServiceProvider
{
    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        parent::boot();

        // Horizon::routeSmsNotificationsTo('15556667777');
        // Horizon::routeMailNotificationsTo('example@example.com');
        // Horizon::routeSlackNotificationsTo('slack-webhook-url', '#channel');
    }

    /**
     * Register the Horizon gate.
     *
     * This gate determines who can access Horizon in non-local environments.
     */
    protected function gate(): void
    {
        Gate::define('viewHorizon', function ($user = null) {
            $allowedIps = array_filter(array_map(
                'trim',
                explode(',', (string) env('HORIZON_ALLOWED_IPS', ''))
            ));

            if (!empty($allowedIps) && in_array(request()->ip(), $allowedIps, true)) {
                return true;
            }

            if (!$user || !isset($user->role)) {
                return false;
            }

            return $user->role instanceof UserRole
                ? $user->role->isStaff()
                : in_array((string) $user->role, [
                    UserRole::SUPER_ADMIN->value,
                    UserRole::ADMIN->value,
                    UserRole::MANAGER->value,
                    UserRole::SUPPORT->value,
                ], true);
        });
    }
}
