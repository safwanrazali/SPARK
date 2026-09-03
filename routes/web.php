<?php

use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\AnalisisInventoriController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\TukarKataLaluanController;
use App\Http\Controllers\AuditTrailController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EntitiController;
use App\Http\Controllers\KemajuanAnalisisController;
use App\Http\Controllers\LaporanController;
use App\Http\Controllers\LaporanKomentarController;
use App\Models\LaporanKomentar;
use App\Http\Controllers\MuatNaikController;
use App\Http\Controllers\ProfilController;
use App\Http\Controllers\StatusLaporanController;
use App\Http\Controllers\WorkflowController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'showLoginForm'])->name('login');
    Route::post('/login', [LoginController::class, 'login'])->name('login.attempt');
});

Route::middleware(['auth', 'password.changed'])->group(function () {

    Route::post('/logout', [LoginController::class, 'logout'])->name('logout');

    /*
    |----------------------------------------------------------------------
    | Wajib tukar kata laluan sementara pada log masuk pertama
    |----------------------------------------------------------------------
    | Route ini dikecualikan daripada EnsurePasswordChanged; tanpa itu
    | pengguna akan dialihkan ke sini tanpa henti.
    */
    Route::get('/tukar-kata-laluan', [TukarKataLaluanController::class, 'edit'])
        ->name('kata-laluan.tukar');
    Route::put('/tukar-kata-laluan', [TukarKataLaluanController::class, 'update'])
        ->name('kata-laluan.simpan');

    // Dashboard Pemantauan — kiraan automatik daripada rekod sebenar.
    // Pegawai Analisis tiada papan pemuka keseluruhan; capaian terus ditolak
    // pada lapisan route, bukan sekadar disembunyikan daripada navigasi.
    Route::get('/', [DashboardController::class, 'index'])
        ->middleware('can:view-dashboard')
        ->name('dashboard');

    /*
    |----------------------------------------------------------------------
    | Profil sendiri — terbuka kepada semua pengguna yang telah log masuk
    |----------------------------------------------------------------------
    | Tiada gate kebenaran: setiap pengguna menyunting akaunnya sendiri
    | sahaja, dan peranan kekal dikawal oleh modul Pentadbiran.
    */
    Route::get('/profil', [ProfilController::class, 'edit'])->name('profil.edit');
    Route::put('/profil', [ProfilController::class, 'update'])->name('profil.update');

    /*
    |----------------------------------------------------------------------
    | Inventori — Muat Naik (modul sedia ada, dikekalkan)
    |----------------------------------------------------------------------
    */
    // Sejarah muat naik ditapis mengikut entiti yang boleh diakses pengguna.
    Route::get('/sejarah-muat-naik', [MuatNaikController::class, 'history'])
        ->name('muat-naik.history');

    Route::middleware('can:manage-upload')->group(function () {
        // Borang muat naik diselaraskan dengan kebenaran tindakan yang
        // dihoskannya (store/preview/destroy) — Fasa 4.
        Route::get('/muat-naik', [MuatNaikController::class, 'index'])
            ->name('muat-naik.index');
        Route::post('/muat-naik', [MuatNaikController::class, 'store'])
            ->name('muat-naik.store');
        Route::post('/muat-naik/preview', [MuatNaikController::class, 'preview'])
            ->name('muat-naik.preview');
        Route::delete('/muat-naik/{muatNaik}', [MuatNaikController::class, 'destroy'])
            ->name('muat-naik.destroy');
    });

    /*
    |----------------------------------------------------------------------
    | Analisis Inventori Kriptografi — input berstruktur (Fasa 1)
    |----------------------------------------------------------------------
    */
    Route::get('/analisis', [AnalisisInventoriController::class, 'index'])
        ->name('analisis.index');

    Route::middleware('can:manage-analysis')->group(function () {
        Route::get('/analisis/borang', [AnalisisInventoriController::class, 'borang'])
            ->name('analisis.borang');
        // Fasa 6 — simpan draf tanpa pengesahan penuh (save / resume).
        Route::post('/analisis/draf', [AnalisisInventoriController::class, 'draf'])
            ->name('analisis.draf');
        Route::post('/analisis', [AnalisisInventoriController::class, 'simpan'])
            ->name('analisis.simpan');
    });

    /*
    |----------------------------------------------------------------------
    | Status Tiga Laporan — paparan sahaja
    |
    | Tiada route kemas kini di sini dengan sengaja: status dikira daripada
    | Kemajuan Analisis Entiti, jadi tiada peranan boleh menetapkannya terus.
    |----------------------------------------------------------------------
    */
    Route::get('/status-laporan', [StatusLaporanController::class, 'index'])
        ->middleware('can:access-status-reports')
        ->name('status.index');

    /*
    |----------------------------------------------------------------------
    | Jejak Audit — paparan sahaja, rekod tidak boleh diubah (Fasa 8)
    |----------------------------------------------------------------------
    */
    Route::get('/jejak-audit', [AuditTrailController::class, 'index'])
        ->middleware('can:view-audit-trail')
        ->name('audit.index');

    /*
    |----------------------------------------------------------------------
    | Pusat Maklumat Entiti — himpunan maklumat setiap entiti (Fasa 5)
    |----------------------------------------------------------------------
    */
    Route::get('/entiti/{agencyCode}', [EntitiController::class, 'show'])
        ->middleware('entity.access')
        ->name('entiti.show');

    /*
    |----------------------------------------------------------------------
    | Aliran Kerja 5 Peringkat — kedudukan semasa setiap entiti
    |----------------------------------------------------------------------
    | Struktur peringkat (termasuk sub-peringkat) ditakrifkan dalam
    | App\Support\AliranKerja.
    */
    Route::get('/workflow', [WorkflowController::class, 'index'])
        ->name('workflow.index');

    Route::get('/workflow/{agencyCode}', [WorkflowController::class, 'show'])
        ->middleware('entity.access')
        ->name('workflow.show');

    /*
    |----------------------------------------------------------------------
    | Kemajuan Analisis Entiti — tindakan setiap peringkat
    |----------------------------------------------------------------------
    | `{stage}` ialah KUNCI peringkat ('1.1', '2', '3.1'), bukan nombor —
    | corak di bawah membenarkan nombor utama dengan sub-peringkat pilihan,
    | dan AliranKerja menolak apa-apa yang tidak wujud dalam takrifan.
    |
    | Kebenaran peranan disemak dalam controller kerana ia berbeza bagi
    | setiap peringkat; `entity.access` di sini memastikan Pegawai Analisis
    | tidak boleh menyentuh entiti yang bukan miliknya.
    |
    | TIADA route bagi peringkat 4 dan 5 (Penjanaan Laporan; Semakan,
    | Kelulusan & Penyerahan Laporan). Prosesnya belum ditentukan, jadi
    | tiada tindakan direka untuknya dalam fasa ini.
    */
    Route::middleware('entity.access')
        ->prefix('workflow/{agencyCode}')
        ->name('kemajuan.')
        ->group(function () {
            Route::post('/peringkat/{stage}/simpan', [KemajuanAnalisisController::class, 'simpan'])
                ->where('stage', '[0-9]+(\.[0-9]+)?')
                ->name('simpan');

            Route::post('/peringkat/{stage}/selesai', [KemajuanAnalisisController::class, 'selesai'])
                ->where('stage', '[0-9]+(\.[0-9]+)?')
                ->name('selesai');

            // No. Rujukan Borang — milik PPR, bukan pemilik peringkat.
            Route::post('/peringkat/{stage}/rujukan', [KemajuanAnalisisController::class, 'rujukan'])
                ->where('stage', '[0-9]+(\.[0-9]+)?')
                ->name('rujukan');

            /*
            | Penugasan Pegawai Analisis — kerja peringkat 1.2 Pendaftaran
            | Data, milik PPA.
            |
            | Ia berada di sini dan bukan pada modulnya sendiri kerana itulah
            | tempatnya dalam aliran kerja: PPA mendaftarkan data DAN menetapkan
            | pegawai yang akan menjalankan peringkat seterusnya.
            */
            Route::post('/penugasan', [KemajuanAnalisisController::class, 'tugaskan'])
                ->middleware('can:manage-assignment')
                ->name('tugaskan');
        });

    /*
    |----------------------------------------------------------------------
    | Penetapan Entiti — DIBUANG
    |----------------------------------------------------------------------
    | Skrin, laluan dan controllernya telah dibuang sepenuhnya.
    |
    | DATANYA KEKAL: jadual `entiti_assignment`, modelnya dan
    | App\Services\EntityAssignmentService tidak disentuh, jadi penugasan
    | yang telah direkodkan TERUS menentukan entiti mana yang boleh dicapai
    | oleh setiap Pegawai Analisis (lihat User::getAccessibleEntities()).
    |
    | Kesannya: tiada penugasan BAHARU boleh dibuat dan tiada entiti baharu
    | boleh dimasukkan ke dalam aliran kerja sehingga penggantinya ditetapkan.
    */

    /*
    |----------------------------------------------------------------------
    | Penjanaan Laporan — templat + business rules + input berstruktur
    |----------------------------------------------------------------------
    */
    Route::get('/laporan', [LaporanController::class, 'index'])
        ->name('laporan.index');

    // Akses laporan dikawal oleh AnalisisInventoriPolicy — Pegawai Analisis
    // hanya boleh membuka laporan bagi entiti yang ditugaskan kepadanya.
    Route::get('/laporan/inventori/{analisis}', [LaporanController::class, 'inventori'])
        ->middleware('can:view,analisis')
        ->name('laporan.inventori');

    Route::get('/laporan/inventori/{analisis}/unduh', [LaporanController::class, 'unduh'])
        ->middleware('can:generateReport,analisis')
        ->name('laporan.unduh');

    /*
    | Komentar KB/PPA pada laporan — maklum balas + pengakuan, bukan kitaran
    | kelulusan. Kebenaran DIKUATKUASAKAN DUA LAPIS pada setiap laluan:
    |
    |   - akses entiti: `can:view,analisis` bagi laluan yang menerima rekod
    |     analisis, dan EntityAccessService di dalam LaporanKomentarPolicy
    |     bagi laluan yang menerima komentar sedia ada;
    |   - peranan/pemilikan: LaporanKomentarPolicy.
    |
    | Tiada laluan bergantung pada butang yang disembunyikan di paparan.
    */
    Route::post('/laporan/inventori/{analisis}/komentar', [LaporanKomentarController::class, 'store'])
        ->middleware(['can:view,analisis', 'can:create,'.LaporanKomentar::class])
        ->name('laporan.komentar.store');

    // Menyunting dan memadam: pengarang komentar SAHAJA.
    Route::patch('/laporan/komentar/{komentar}', [LaporanKomentarController::class, 'update'])
        ->middleware('can:update,komentar')
        ->name('laporan.komentar.update');

    Route::delete('/laporan/komentar/{komentar}', [LaporanKomentarController::class, 'destroy'])
        ->middleware('can:delete,komentar')
        ->name('laporan.komentar.destroy');

    // Tindakan Diambil: Pegawai Analisis SAHAJA. Ia tidak menyentuh status
    // peringkat 3.1 dan tidak mencetuskan sebarang notifikasi.
    Route::post('/laporan/komentar/{komentar}/tindakan', [LaporanKomentarController::class, 'tandakanTindakan'])
        ->middleware('can:tandakanTindakan,komentar')
        ->name('laporan.komentar.tindakan');

    Route::delete('/laporan/komentar/{komentar}/tindakan', [LaporanKomentarController::class, 'batalkanTindakan'])
        ->middleware('can:batalkanTindakan,komentar')
        ->name('laporan.komentar.tindakan.batal');

    /*
    |----------------------------------------------------------------------
    | Pentadbiran (sedia ada, dikekalkan)
    |----------------------------------------------------------------------
    */
    Route::middleware('can:access-administration')
        ->prefix('administration')
        ->name('administration.')
        ->group(function () {
            Route::resource('users', UserController::class)
                ->except(['show']);

            // Tetapkan semula kata laluan pengguna atas permintaan mereka.
            Route::post('users/{user}/tetap-semula-kata-laluan', [UserController::class, 'tetapSemulaKataLaluan'])
                ->name('users.tetap-semula-kata-laluan');
        });
});
