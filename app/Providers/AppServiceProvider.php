<?php

namespace App\Providers;

use App\Models\AnalisisInventori;
use App\Models\User;
use App\Policies\AnalisisInventoriPolicy;
use App\Services\EntityAccessService;
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

        // Fasa 2 — kawalan peringkat workflow 7 langkah.
        // NEEDS CONFIRMATION: matriks kebenaran (spesifikasi bahagian 26) tidak
        // menyatakan peranan mana yang boleh menukar peringkat. Pemetaan awal
        // mengikut konvensyen sedia ada untuk kawalan pemantauan
        // (`manage-status`): Pentadbir + Pegawai Penyelaras Analisis.
        // Ubah pemetaan di sini sahaja apabila business rule disahkan.
        Gate::define('manage-workflow', fn (User $user) => $user->isAdministrator() || $user->isCoordinator());

        // Fasa 3 — penugasan entiti kepada Pegawai Analisis.
        // Matriks kebenaran (spesifikasi bahagian 26) menyatakan "Assign entity"
        // dibenarkan untuk Pentadbir dan Pegawai Penyelaras Analisis sahaja.
        Gate::define('manage-assignment', fn (User $user) => $user->isAdministrator() || $user->isCoordinator());

        // Fasa 4 — kawalan akses entiti (spesifikasi bahagian 9).
        // Pegawai Analisis hanya boleh mengakses entiti yang ditugaskan
        // kepadanya; Pentadbir dan Penyelaras mengakses semua entiti.
        Gate::define(
            'access-entity',
            fn (User $user, ?string $agencyCode) => $this->app->make(EntityAccessService::class)
                ->canAccess($user, $agencyCode),
        );

        // Melihat senarai penuh entiti tanpa penapisan.
        Gate::define(
            'view-all-entities',
            fn (User $user) => ! $this->app->make(EntityAccessService::class)->isRestricted($user),
        );

        Gate::define('access-inventory', fn (User $user) => true);

        Gate::define('access-risk-assessment', fn (User $user) => true);

        Gate::define('access-reports', fn (User $user) => true);

        Gate::policy(AnalisisInventori::class, AnalisisInventoriPolicy::class);
    }
}
