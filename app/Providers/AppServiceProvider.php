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

        // Fasa 1 — kawalan akses mengikut slaid 14 (PPTX):
        // Pegawai Analisis  : input dapatan + jana laporan
        // Pegawai Penyelaras: tetapkan / kemas kini status 3 laporan
        // Pentadbir         : semua fungsi
        Gate::define('manage-analysis', fn (User $user) => $user->isAdministrator() || $user->isAnalyst());

        Gate::define('manage-status', fn (User $user) => $user->isAdministrator() || $user->isCoordinator());

        Gate::define('access-inventory', fn (User $user) => true);

        Gate::define('access-risk-assessment', fn (User $user) => true);

        Gate::define('access-reports', fn (User $user) => true);
    }
}
