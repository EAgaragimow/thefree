<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Auth\Events\Login;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->defineMarketplaceGates();

        // Signing in again reactivates a self-deactivated account.
        Event::listen(Login::class, function (Login $event): void {
            if ($event->user->deactivated_at !== null) {
                $event->user->forceFill(['deactivated_at' => null])->save();
            }
        });
    }

    /**
     * Subscription-level access rules (SPEC §2). Guests are denied automatically because
     * every ability requires an authenticated user.
     */
    private function defineMarketplaceGates(): void
    {
        Gate::define('view-full-directory', fn (User $user): bool => $user->isAdmin()
            || $user->isFreelancer()
            || $user->hasPaidAccess());

        Gate::define('view-full-freelancer-profile', fn (User $user): bool => $user->isAdmin()
            || $user->isFreelancer()
            || $user->hasPaidAccess());

        Gate::define('read-full-blog-article', fn (User $user): bool => $user->isAdmin()
            || $user->hasPaidAccess());

        Gate::define('favorite-freelancers', fn (User $user): bool => $user->hasPaidAccess());
    }
}
