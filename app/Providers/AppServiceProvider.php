<?php

namespace App\Providers;

use App\Models\AnalisisInventori;
use App\Models\LaporanCatatan;
use App\Models\User;
use App\Policies\AnalisisInventoriPolicy;
use App\Policies\LaporanCatatanPolicy;
use App\Support\AliranKerja;
use App\Services\EntityAccessService;
use Illuminate\Pagination\Paginator;
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
        | Penomboran halaman
        |------------------------------------------------------------------
        |
        | Paparan lalai Laravel ditulis untuk Tailwind. Aplikasi ini memuatkan
        | Bootstrap 5 sahaja, jadi kelas utiliti Tailwind (termasuk saiz ikon
        | `w-5 h-5`) tidak wujud dan anak panah SVG dipaparkan sebesar
        | halaman. Paparan Bootstrap 5 digayakan semula dalam
        | `resources/scss/pagination.scss`.
        */
        Paginator::useBootstrapFive();

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
        | Penetapan Entiti — Tanda/Kemaskini| ✗  |  ✗   | ✓  |  ✗  |  ✗  |  ✓  | ✗
        | Penetapan Entiti — Tugaskan PA    | ✗  |  ✗   | ✗  |  ✗  |  ✗  |  ✓  | ✗
        | Kemajuan Analisis — Lihat         | ✓  |  ✓   | ✓  |  ✓  |  ✓  |  ✓  | ✓
        | Peringkat 1.1 Penerimaan Data     | ✗  |  ✗   | ✓  |  ✗  |  ✗  |  ✓  | ✗
        | Peringkat 1.2 Pendaftaran Data    | ✗  |  ✗   | ✗  |  ✗  |  ✗  |  ✓  | ✗
        | Peringkat 1.3 / 2 / 3.1           | ✗  |  ✗   | ✗  |  ✗  |  ✗  |  ✗  | ✓
        | No. Rujukan (1.1–1.3, 3.1)        | ✗  |  ✗   | ✗  |  ✗  |  ✓  |  ✗  | ✗
        | Analisis Inventori Kriptografi — Lihat        | ✓  |  ✓   | ✓  |  ✓  |  ✓  |  ✓  | ✓
        | Analisis Inventori Kriptografi — Input/Sunting| ✗  |  ✗   | ✗  |  ✗  |  ✗  |  ✗  | ✓
        | Analisis Inventori Kriptografi — Jana Laporan | ✗  |  ✗   | ✗  |  ✗  |  ✗  |  ✗  | ✓
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
        | Penetapan Entiti — modul DIBUANG
        |------------------------------------------------------------------
        | Skrin, laluan dan controllernya telah dibuang. Ketiga-tiga gate ini
        | DIKEKALKAN kerana ia merakam tanggungjawab yang telah dipersetujui,
        | dan kerana `manage-assignment` masih menentukan siapa melihat
        | maklumat penugasan pada skrin lain — tetapi TIADA laluan lagi yang
        | membenarkan sesiapa menanda peringkat 1.1, menetapkannya semula,
        | atau membuat penugasan baharu.
        |
        | Penugasan yang TELAH direkodkan kekal berkuat kuasa: ia yang
        | menentukan entiti mana boleh dicapai oleh setiap Pegawai Analisis
        | (lihat User::getAccessibleEntities()).
        |
        | Jangan sambungkan gate ini kepada laluan baharu tanpa spesifikasi
        | pengganti modul tersebut.
        */
        Gate::define('register-entity-data', fn (User $user) => $user->hasAnyRole([...$kb, ...$ppa]));

        Gate::define('reset-entity-registration', fn (User $user) => $user->hasAnyRole($kb));

        Gate::define('manage-assignment', fn (User $user) => $user->hasAnyRole($ppa));

        /*
        |------------------------------------------------------------------
        | Kemajuan Analisis Entiti
        |------------------------------------------------------------------
        */

        /*
        | Setiap peringkat aliran kerja mempunyai peranannya sendiri. Gate di
        | bawah memetakan lajur "Peranan Bertanggungjawab" takrifan aliran
        | kerja; AliranKerja::gate() menamakan gate mana melindungi peringkat
        | mana, supaya tiada pemetaan kedua yang boleh terpesong daripadanya.
        |
        |   1.1 Penerimaan Data                  KB / PPA
        |   1.2 Pendaftaran Data                 PPA
        |   1.3 Semakan Awal Data                PA
        |   2   Penyediaan & Pengesahan Data     PA
        |   3.1 Analisis Inventori Kriptografi   PA
        |   3.2 / 4 / 5                          fasa akan datang — tiada gate
        */

        // Peringkat 1.1 — dikongsi dengan skrin Penetapan Entiti di atas.
        Gate::define('manage-stage-penerimaan', fn (User $user) => $user->hasAnyRole([...$kb, ...$ppa]));

        // Peringkat 1.2 — Pegawai Penyelaras Analisis.
        Gate::define('manage-stage-pendaftaran', fn (User $user) => $user->hasAnyRole($ppa));

        // Peringkat 1.3, 2 dan 3.1 — Pegawai Analisis sahaja, dan hanya bagi
        // entiti yang ditugaskan kepadanya (dikuatkuasakan berasingan oleh
        // middleware `entity.access`).
        Gate::define('advance-analysis-stage', fn (User $user) => $user->hasAnyRole($pa));

        /*
        | SETIAP No. Rujukan — Pegawai Kawalan Dokumen SAHAJA.
        |
        | Ini keseluruhan tanggungjawab PKD, dan satu-satunya kuasa menulis
        | yang dimilikinya. Ia meliputi kesemua empat nombor rujukan:
        |
        |   No. Rujukan Borang Penerimaan Data      (peringkat 1.1)
        |   No. Rujukan Borang Pendaftaran Data     (peringkat 1.2)
        |   No. Rujukan Borang Semakan Awal Data    (peringkat 1.3)
        |   No. Rujukan Laporan                     (peringkat 3.1)
        |
        | Sengaja berasingan daripada gate peringkat di atas: keempat-empat
        | peringkat itu dilaksanakan oleh KB, PPA dan PA. Menyatukannya akan
        | memberi PKD kuasa menggerakkan peringkat, atau memberi pemilik
        | peringkat kuasa menetapkan nombor rujukan — kedua-duanya bukan
        | tanggungjawab mereka.
        |
        | Pegawai Penyelaras Rekod (PPR) tiada tugas khusus dalam fasa ini:
        | ia tidak memegang gate menulis langsung.
        */
        Gate::define(AliranKerja::GATE_RUJUKAN, fn (User $user) => $user->hasAnyRole($pkd));

        /*
        |------------------------------------------------------------------
        | Peringkat 5 — Semakan, Kelulusan & Penyerahan Laporan
        |------------------------------------------------------------------
        | FASA AKAN DATANG. Ketiga-tiga gate di bawah DIKEKALKAN supaya
        | tanggungjawab yang telah pun dipersetujui tidak hilang, tetapi
        | TIADA route atau butang menggunakannya dalam fasa ini: proses
        | semakan, kelulusan dan penyerahan peringkat 5 belum ditentukan.
        |
        | Jangan sambungkannya kepada tindakan baharu tanpa spesifikasi
        | peringkat 5 — itu bermakna mereka-reka proses yang belum diberikan.
        */
        Gate::define('review-report', fn (User $user) => $user->hasAnyRole([...$kb, ...$ppa]));

        Gate::define('approve-report', fn (User $user) => $user->hasAnyRole($kb));

        // NEEDS CONFIRMATION: pengurusan belum memuktamadkan sama ada
        // tanggungjawab ini milik Ketua Bahagian atau Timbalan Pengarah II.
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

        // Tiada gate 'manage-status': Status Tiga Laporan bersifat paparan
        // sahaja. Status dikira daripada Kemajuan Analisis Entiti, jadi tiada
        // peranan — termasuk PPA — boleh menetapkannya terus.

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
        Gate::policy(LaporanCatatan::class, LaporanCatatanPolicy::class);
    }
}
