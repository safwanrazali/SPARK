<?php

use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\AnalisisInventoriController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\TukarKataLaluanController;
use App\Http\Controllers\AuditTrailController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EntitiAssignmentController;
use App\Http\Controllers\EntitiController;
use App\Http\Controllers\KemajuanAnalisisController;
use App\Http\Controllers\LaporanController;
use App\Http\Controllers\MuatNaikController;
use App\Http\Controllers\PendaftaranEntitiController;
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
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

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
    | Status Tiga Laporan — kitaran dikawal Pegawai Penyelaras
    |----------------------------------------------------------------------
    */
    Route::get('/status-laporan', [StatusLaporanController::class, 'index'])
        ->name('status.index');

    Route::post('/status-laporan/kitar', [StatusLaporanController::class, 'kitar'])
        ->middleware('can:manage-status')
        ->name('status.kitar');

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
    | Workflow 7 Peringkat — kedudukan semasa setiap entiti (Fasa 2)
    |----------------------------------------------------------------------
    */
    Route::get('/workflow', [WorkflowController::class, 'index'])
        ->name('workflow.index');

    Route::get('/workflow/{agencyCode}', [WorkflowController::class, 'show'])
        ->middleware('entity.access')
        ->name('workflow.show');

    Route::middleware(['can:manage-workflow', 'entity.access'])->group(function () {
        Route::post('/workflow/{agencyCode}/mula', [WorkflowController::class, 'mula'])
            ->name('workflow.mula');
        Route::post('/workflow/{agencyCode}/peringkat', [WorkflowController::class, 'peringkat'])
            ->name('workflow.peringkat');
        Route::post('/workflow/{agencyCode}/status', [WorkflowController::class, 'status'])
            ->name('workflow.status');
    });

    /*
    |----------------------------------------------------------------------
    | Kemajuan Analisis Entiti — tindakan setiap peringkat
    |----------------------------------------------------------------------
    | Kebenaran peranan disemak dalam controller kerana ia berbeza bagi
    | setiap tindakan; `entity.access` di sini memastikan Pegawai Analisis
    | tidak boleh menyentuh entiti yang bukan miliknya.
    */
    Route::middleware('entity.access')
        ->prefix('workflow/{agencyCode}')
        ->name('kemajuan.')
        ->group(function () {
            Route::post('/peringkat/{stage}/selesai', [KemajuanAnalisisController::class, 'selesai'])
                ->whereNumber('stage')
                ->name('selesai');

            Route::post('/jana-laporan', [KemajuanAnalisisController::class, 'janaLaporan'])->name('jana-laporan');
            Route::post('/hantar', [KemajuanAnalisisController::class, 'hantar'])->name('hantar');
            Route::post('/semak', [KemajuanAnalisisController::class, 'semak'])->name('semak');
            Route::post('/kembalikan', [KemajuanAnalisisController::class, 'kembalikan'])->name('kembalikan');
            Route::post('/sahkan', [KemajuanAnalisisController::class, 'sahkan'])->name('sahkan');
            Route::post('/serah', [KemajuanAnalisisController::class, 'serah'])->name('serah');
        });

    /*
    |----------------------------------------------------------------------
    | Penetapan Entiti — satu skrin, dua panel mengikut peranan
    |----------------------------------------------------------------------
    | Panel pendaftaran (peringkat 1) milik Pegawai Penyelaras Rekod dan
    | Ketua Bahagian; panel penugasan milik Pegawai Penyelaras Analisis.
    | Kerana itu gate `manage-assignment` tidak lagi boleh melindungi
    | keseluruhan kumpulan — ia dipindahkan ke setiap route penugasan.
    |
    | Route `pendaftaran` mesti didaftarkan SEBELUM `{agencyCode}`, jika
    | tidak POST /penugasan/pendaftaran akan dipadankan sebagai penugasan
    | bagi entiti bernama "pendaftaran".
    */
    Route::prefix('penugasan')
        ->name('penugasan.')
        ->group(function () {
            Route::get('/', [EntitiAssignmentController::class, 'index'])->name('index');

            Route::post('/pendaftaran', [PendaftaranEntitiController::class, 'kemasKini'])
                ->middleware('can:register-entity-data')
                ->name('pendaftaran.kemas-kini');

            Route::post('/pendaftaran/{agencyCode}/set-semula', [PendaftaranEntitiController::class, 'setSemula'])
                ->middleware('can:reset-entity-registration')
                ->name('pendaftaran.set-semula');

            Route::middleware(['can:manage-assignment', 'entity.access'])->group(function () {
                Route::get('/{agencyCode}', [EntitiAssignmentController::class, 'show'])->name('show');
                Route::post('/{agencyCode}', [EntitiAssignmentController::class, 'simpan'])->name('simpan');
                Route::post('/{agencyCode}/tarik', [EntitiAssignmentController::class, 'tarik'])->name('tarik');
            });
        });

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
