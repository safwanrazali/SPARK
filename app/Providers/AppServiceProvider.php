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
        | FASA 9 — pemetaan kebenaran mengikut peranan
        |------------------------------------------------------------------
        |
        | Sumber tunggal: matriks kebenaran asas (spesifikasi bahagian 26).
        | Sel bertanda "ikut permission" bermakna kebenaran sebenar BELUM
        | ditetapkan; ia dilayan sebagai TIDAK dibenarkan supaya tiada
        | kuasa direka sendiri. Tandakan NEEDS CONFIRMATION.
        |
        | Fungsi                | Admin | Penyelaras | Analisis | Ketua
        | ----------------------|-------|------------|----------|------
        | Dashboard keseluruhan |   ✓   |     ✓      |    ✗     |  ✓
        | Lihat semua entiti    |   ✓   |     ✓      |    ✗     |  ✓
        | Lihat assigned entity |   ✓   |     ✓      |    ✓     |  ✓
        | Assign entity         |   ✓   |     ✓      |    ✗     |  ✗
        | Input analysis        |   ✓   |  ikut izin |    ✓     |  ✗
        | Save / resume draft   |   ✓   |  ikut izin |    ✓     |  ✗
        | Generate report       |   ✓   |  ikut izin |    ✓     |  ✗
        | Review                |   ✓   |     ✓      | ikut izin|  ✓
        | Approve               |   ✓   |  ikut izin |    ✗     |  ✓
        | Audit trail           |   ✓   |     ✓      | ikut izin|  ✓
        |
        | Pegawai Kawalan Dokumen dan Pegawai Penyelaras Rekod (bahagian 25)
        | TIADA baris dalam matriks. Peranan tersebut didaftarkan supaya
        | boleh diberikan kepada pengguna, tetapi tidak diberi sebarang
        | kebenaran sehingga business rule disahkan — lalai ialah tolak.
        |
        | Timbalan Pengarah II (TPII) juga TIADA baris dalam matriks.
        | NEEDS CONFIRMATION: pengurusan belum memuktamadkan kebenarannya.
        | Buat sementara ia diberi akses BACA sahaja — papan pemuka dan
        | keterlihatan entiti (lihat User::hasFullEntityVisibility()) —
        | supaya angka papan pemuka bermakna. Semua gate menulis, menyemak,
        | meluluskan, jejak audit dan pentadbiran kekal ditolak.
        */

        $admin = [User::ROLE_ADMINISTRATOR];
        $penyelaras = [User::ROLE_COORDINATOR];
        $analisis = [User::ROLE_ANALYST];
        $ketua = [User::ROLE_KETUA_BAHAGIAN];
        $timbalanII = [User::ROLE_TIMBALAN_PENGARAH_II];
        $penyelarasRekod = [User::ROLE_PENYELARAS_REKOD];

        // Pentadbiran sistem — Pentadbir sahaja. Tiada peranan lain boleh
        // mewarisi akses ini secara tidak sengaja.
        Gate::define('access-administration', fn (User $user) => $user->hasAnyRole($admin));

        // Modul muat naik sedia ada (bukan sebahagian workflow baharu).
        Gate::define('manage-upload', fn (User $user) => $user->hasAnyRole([...$admin, ...$penyelaras]));

        // Input dapatan analisis, draf dan penjanaan laporan.
        // Penyelaras "ikut permission" → tidak diberikan. Ketua ✗.
        Gate::define('manage-analysis', fn (User $user) => $user->hasAnyRole([...$admin, ...$analisis]));

        // Status tiga laporan (modul pemantauan sedia ada).
        Gate::define('manage-status', fn (User $user) => $user->hasAnyRole([...$admin, ...$penyelaras]));

        // Fasa 2 — kawalan peringkat workflow 7 langkah.
        // NEEDS CONFIRMATION: matriks tidak menyatakan peranan mana yang
        // boleh menukar peringkat. Pemetaan mengikut konvensyen kawalan
        // pemantauan sedia ada (`manage-status`).
        Gate::define('manage-workflow', fn (User $user) => $user->hasAnyRole([...$admin, ...$penyelaras]));

        // "Assign entity" — Pentadbir ✓, Penyelaras ✓, Analisis ✗, Ketua ✗.
        Gate::define('manage-assignment', fn (User $user) => $user->hasAnyRole([...$admin, ...$penyelaras]));

        /*
        |------------------------------------------------------------------
        | Kemajuan Analisis Entiti — carta aliran kerja
        |------------------------------------------------------------------
        |
        | Aliran ini memberi setiap peringkat kepada peranan tertentu, jadi
        | gate `manage-workflow` yang menyeluruh itu tidak lagi mencukupi.
        | Ia dikekalkan sebagai kawalan penyeliaan (kemas kini peringkat
        | secara manual); tindakan aliran kerja harian menggunakan gate
        | khusus di bawah.
        */

        // PPR menandakan "Penerimaan & Pendaftaran Data" bagi setiap entiti.
        // Skrin ini membaca senarai induk sektor sahaja — ia tidak mendedahkan
        // rekod entiti, jadi PPR kekal tanpa akses kepada halaman entiti.
        Gate::define('register-entity-data', fn (User $user) => $user->hasAnyRole([...$admin, ...$penyelarasRekod]));

        // Entiti yang telah didaftarkan dikunci daripada PPR. Nota carta
        // aliran menyatakan hanya Ketua Bahagian boleh membuka semula.
        Gate::define('reset-entity-registration', fn (User $user) => $user->hasAnyRole([...$admin, ...$ketua]));

        // PA memacu peringkat 2 hingga 5 bagi entiti yang ditugaskan
        // kepadanya. Dimensi entiti dikuatkuasakan berasingan melalui
        // EntityAccessService, jadi gate ini hanya menyatakan peranan.
        Gate::define('advance-analysis-stage', fn (User $user) => $user->hasAnyRole([...$admin, ...$analisis]));

        // "Penyerahan & Penutupan" — penyerahan laporan yang telah disahkan
        // kepada NACSA.
        //
        // NEEDS CONFIRMATION: pengurusan belum memuktamadkan sama ada
        // tanggungjawab ini milik Ketua Bahagian atau Timbalan Pengarah II.
        // Buat sementara ia diberikan kepada Ketua Bahagian sahaja; tambah
        // `...$timbalanII` pada senarai ini apabila keputusan disahkan —
        // tiada perubahan lain diperlukan.
        Gate::define('submit-to-nacsa', fn (User $user) => $user->hasAnyRole([...$admin, ...$ketua]));

        // "Dashboard keseluruhan" — termasuk Ketua Bahagian, dan buat
        // sementara Timbalan Pengarah II (satu-satunya kebenaran eksplisit
        // yang diberikan kepada peranan itu setakat ini).
        Gate::define('view-dashboard', fn (User $user) => $user->hasAnyRole([...$admin, ...$penyelaras, ...$ketua, ...$timbalanII]));

        // "Audit trail" — Analisis "ikut permission" → tidak diberikan akses
        // kepada jejak audit berpusat; mereka tetap melihat sejarah entiti
        // yang ditugaskan melalui halaman entiti (Fasa 5).
        Gate::define('view-audit-trail', fn (User $user) => $user->hasAnyRole([...$admin, ...$penyelaras, ...$ketua]));

        // "Review" — Analisis "ikut permission" → tidak diberikan.
        // "Approve" — Penyelaras "ikut permission" → tidak diberikan.
        //
        // Pemetaan kebenaran sahaja. Aliran semakan dan kelulusan sebenar
        // ialah skop Fasa 10 dan belum dilaksanakan; tiada kuasa kelulusan
        // direka di luar matriks yang telah disahkan.
        Gate::define('review-report', fn (User $user) => $user->hasAnyRole([...$admin, ...$penyelaras, ...$ketua]));

        Gate::define('approve-report', fn (User $user) => $user->hasAnyRole([...$admin, ...$ketua]));

        // Fasa 4 — kawalan akses entiti (spesifikasi bahagian 9).
        // Pegawai Analisis hanya boleh mengakses entiti yang ditugaskan
        // kepadanya; Pentadbir, Penyelaras dan Ketua Bahagian mengakses
        // semua entiti (lihat User::hasFullEntityVisibility()).
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
