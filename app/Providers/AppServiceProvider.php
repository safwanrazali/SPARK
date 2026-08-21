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
        /*
        |------------------------------------------------------------------
        | Matriks kebenaran — sumber tunggal kebenaran
        |------------------------------------------------------------------
        |
        | Setiap gate di bawah memetakan SATU baris matriks. Tiada kebenaran
        | tersirat: peranan yang tidak disenaraikan pada satu gate ditolak,
        | dan gate inilah yang digunakan serentak oleh middleware route,
        | controller dan paparan — menyembunyikan butang bukan kebenaran.
        |
        | Modul / Fungsi                    | PS | TPII | KB | PPR | PKD | PPA | PA
        | ----------------------------------|----|------|----|-----|-----|-----|----
        | Papan Pemuka                      | ✓  |  ✓   | ✓  |  ✓  |  ✓  |  ✓  | ✗
        | Penetapan Entiti — Set Semula     | ✗  |  ✗   | ✓  |  ✗  |  ✗  |  ✗  | ✗
        | Penetapan Entiti — Tanda/Kemaskini| ✗  |  ✗   | ✗  |  ✓  |  ✗  |  ✗  | ✗
        | Penetapan Entiti — Tugaskan PA    | ✗  |  ✗   | ✗  |  ✗  |  ✗  |  ✓  | ✗
        | Kemajuan Analisis — Lihat         | ✓  |  ✓   | ✓  |  ✓  |  ✓  |  ✓  | ✓
        | Kemajuan Analisis — Kemas Kini    | ✗  |  ✗   | ✗  |  ✗  |  ✗  |  ✗  | ✓
        | Kemajuan Analisis — Semak         | ✗  |  ✗   | ✓  |  ✗  |  ✗  |  ✓  | ✗
        | Kemajuan Analisis — Sahkan        | ✗  |  ✗   | ✓  |  ✗  |  ✗  |  ✗  | ✗
        | Kemajuan Analisis — Hantar NACSA  | ✗  |  ✗   | ✓  |  ✗  |  ✗  |  ✗  | ✗
        | Analisis Inventori — Lihat        | ✓  |  ✓   | ✓  |  ✓  |  ✓  |  ✓  | ✓
        | Analisis Inventori — Input/Sunting| ✗  |  ✗   | ✗  |  ✗  |  ✗  |  ✗  | ✓
        | Analisis Inventori — Jana Laporan | ✗  |  ✗   | ✗  |  ✗  |  ✗  |  ✗  | ✓
        | Status 3 Laporan                  | ✗  |  ✓   | ✓  |  ✓  |  ✓  |  ✓  | ✓
        | Log Audit                         | ✓  |  ✓   | ✓  |  ✓  |  ✓  |  ✓  | ✓
        | Pengguna                          | ✓  |  ✗   | ✗  |  ✗  |  ✗  |  ✗  | ✗
        | Profil Saya                       | ✓  |  ✓   | ✓  |  ✓  |  ✓  |  ✓  | ✓
        |
        | Pentadbir Sistem mempunyai "Lihat" sahaja pada Kemajuan Analisis
        | Entiti dan Analisis Inventori Kriptografi. Tiada gate memberikannya
        | kuasa menggerakkan peringkat, menyemak, mengesahkan atau menyerah —
        | termasuk kawalan penyeliaan manual, yang telah dibuang sepenuhnya
        | kerana tiada peranan berhak menggunakannya.
        |
        | Baris "Lihat" tertakluk kepada kawalan akses entiti: Pegawai
        | Analisis hanya melihat entiti yang ditugaskan kepadanya (lihat
        | EntityAccessService). Kebenaran peranan dan kebenaran data ialah
        | dua lapisan berasingan yang mesti kedua-duanya lulus.
        |
        | Profil Saya tiada gate kerana ia tidak menerima id pengguna —
        | ProfilController sentiasa bekerja pada $request->user() sahaja,
        | jadi profil orang lain tidak boleh dicapai melaluinya.
        */

        $ps = [User::ROLE_ADMINISTRATOR];
        $tpii = [User::ROLE_TIMBALAN_PENGARAH_II];
        $kb = [User::ROLE_KETUA_BAHAGIAN];
        $ppr = [User::ROLE_PENYELARAS_REKOD];
        $pkd = [User::ROLE_PEGAWAI_KAWALAN_DOKUMEN];
        $ppa = [User::ROLE_COORDINATOR];
        $pa = [User::ROLE_ANALYST];

        $semua = User::roles();

        /*
        |------------------------------------------------------------------
        | Papan Pemuka
        |------------------------------------------------------------------
        | Semua peranan kecuali Pegawai Analisis, yang bekerja daripada
        | senarai entiti yang ditugaskan kepadanya.
        */
        Gate::define('view-dashboard', fn (User $user) => $user->hasAnyRole(
            [...$ps, ...$tpii, ...$kb, ...$ppr, ...$pkd, ...$ppa]
        ));

        /*
        |------------------------------------------------------------------
        | Penetapan Entiti — tiga tindakan, tiga peranan berlainan
        |------------------------------------------------------------------
        | Skrin dikongsi, tetapi setiap panel dan setiap route mempunyai
        | gate sendiri supaya satu peranan tidak boleh melakukan kerja
        | peranan yang lain.
        */
        Gate::define('register-entity-data', fn (User $user) => $user->hasAnyRole($ppr));

        Gate::define('reset-entity-registration', fn (User $user) => $user->hasAnyRole($kb));

        Gate::define('manage-assignment', fn (User $user) => $user->hasAnyRole($ppa));

        /*
        |------------------------------------------------------------------
        | Kemajuan Analisis Entiti
        |------------------------------------------------------------------
        */

        // Memajukan peringkat — Pegawai Analisis sahaja, dan hanya bagi
        // entiti yang ditugaskan kepadanya (dikuatkuasakan berasingan oleh
        // middleware `entity.access`).
        Gate::define('advance-analysis-stage', fn (User $user) => $user->hasAnyRole($pa));

        // Semakan laporan: PPA menyemak sebelum Ketua Bahagian, dan Ketua
        // Bahagian turut boleh mengembalikan laporan yang berada padanya.
        Gate::define('review-report', fn (User $user) => $user->hasAnyRole([...$kb, ...$ppa]));

        // Pengesahan laporan — Ketua Bahagian sahaja.
        Gate::define('approve-report', fn (User $user) => $user->hasAnyRole($kb));

        // Penyerahan laporan yang telah disahkan kepada NACSA.
        //
        // NEEDS CONFIRMATION: pengurusan belum memuktamadkan sama ada
        // tanggungjawab ini milik Ketua Bahagian atau Timbalan Pengarah II.
        // Matriks semasa memberikannya kepada Ketua Bahagian; tambah
        // `...$tpii` di sini apabila keputusan disahkan.
        Gate::define('submit-to-nacsa', fn (User $user) => $user->hasAnyRole($kb));

        /*
        |------------------------------------------------------------------
        | Analisis Inventori Kriptografi
        |------------------------------------------------------------------
        | Mengisi borang, menyimpan draf dan menjana laporan ialah kerja
        | Pegawai Analisis. Peranan lain melihat sahaja.
        */
        Gate::define('manage-analysis', fn (User $user) => $user->hasAnyRole($pa));

        /*
        |------------------------------------------------------------------
        | Status 3 Laporan
        |------------------------------------------------------------------
        | Semua peranan operasi — Pentadbir Sistem sengaja dikecualikan.
        | Disenaraikan secara eksplisit dan bukan sebagai "semua kecuali",
        | supaya peranan baharu tidak mewarisi akses tanpa keputusan sedar.
        */
        Gate::define('access-status-reports', fn (User $user) => $user->hasAnyRole(
            [...$tpii, ...$kb, ...$ppr, ...$pkd, ...$ppa, ...$pa]
        ));

        // Mengitar status ketiga-tiga laporan kekal milik PPA.
        Gate::define('manage-status', fn (User $user) => $user->hasAnyRole($ppa));

        /*
        |------------------------------------------------------------------
        | Log Audit — semua peranan
        |------------------------------------------------------------------
        | Rekod jejak audit bersifat tambah-sahaja (lihat ActivityLog), jadi
        | tiada peranan boleh mengubah atau memadamnya. Kandungan yang
        | dilihat tetap ditapis mengikut entiti yang boleh diakses.
        */
        Gate::define('view-audit-trail', fn (User $user) => $user->hasAnyRole($semua));

        /*
        |------------------------------------------------------------------
        | Pentadbiran pengguna — Pentadbir Sistem sahaja
        |------------------------------------------------------------------
        */
        Gate::define('access-administration', fn (User $user) => $user->hasAnyRole($ps));

        /*
        |------------------------------------------------------------------
        | Modul muat naik (sedia ada, di luar matriks dan di luar navigasi)
        |------------------------------------------------------------------
        */
        Gate::define('manage-upload', fn (User $user) => $user->hasAnyRole([...$ps, ...$ppa]));

        /*
        |------------------------------------------------------------------
        | Kawalan akses entiti — lapisan kedua di atas kebenaran peranan
        |------------------------------------------------------------------
        | Pegawai Analisis hanya boleh menyentuh entiti yang ditugaskan
        | kepadanya; peranan lain melihat semua entiti (lihat
        | User::hasFullEntityVisibility()). Penapisan dilakukan pada query,
        | bukan pada paparan, supaya URL langsung turut ditolak.
        */
        Gate::define(
            'access-entity',
            fn (User $user, ?string $agencyCode) => $this->app->make(EntityAccessService::class)
                ->canAccess($user, $agencyCode),
        );

        Gate::define(
            'view-all-entities',
            fn (User $user) => ! $this->app->make(EntityAccessService::class)->isRestricted($user),
        );

        Gate::policy(AnalisisInventori::class, AnalisisInventoriPolicy::class);
    }
}
