<?php

namespace App\Providers;

use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

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
        $this->configureDefaults();
        $this->definePlatformGates();
    }

    /**
     * Platform abilities — the Admin's domain.
     *
     * Deliberately separate from the content policies. An **Admin** manages
     * the PLATFORM; an **RCM** manages content and access to regional hats.
     * Neither inherits the other's authority, so an RCM cannot reconfigure
     * the platform and an Admin cannot approve a booking.
     */
    protected function definePlatformGates(): void
    {
        // Only the first Admin ever created may appoint or remove others.
        Gate::define('manage-admins', fn (User $user): bool => $user->isSuperAdmin());

        // Appointing the content authority is the platform's call.
        Gate::define('appoint-rcm', fn (User $user): bool => $user->isAdmin());

        Gate::define('configure-platform', fn (User $user): bool => $user->isAdmin());

        Gate::define('view-platform-audit', fn (User $user): bool => $user->isAdmin());

        // Impersonation is a platform capability, never a content one — and
        // the Super Admin is not impersonable by anybody.
        Gate::define('impersonate', fn (User $user, User $target): bool => $user->isAdmin()
            && ! $target->isSuperAdmin()
            && $user->id !== $target->id);
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }
}
