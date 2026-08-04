<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Gate::define('access-administration', fn (User $user) => $user->isAdministrator());

        Gate::define('manage-upload', fn (User $user) => $user->isAdministrator() || $user->isCoordinator());

        Gate::define('access-inventory', fn (User $user) => true); // all 3 roles per your spec

        Gate::define('access-risk-assessment', fn (User $user) => true); // all 3 roles

        Gate::define('access-reports', fn (User $user) => true); // all 3 roles
    }
}
