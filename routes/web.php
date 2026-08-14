<?php

use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\AnalisisInventoriController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EntitiAssignmentController;
use App\Http\Controllers\EntitiController;
use App\Http\Controllers\LaporanController;
use App\Http\Controllers\MuatNaikController;
use App\Http\Controllers\StatusLaporanController;
use App\Http\Controllers\WorkflowController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'showLoginForm'])->name('login');
    Route::post('/login', [LoginController::class, 'login'])->name('login.attempt');
});

Route::middleware('auth')->group(function () {

    Route::post('/logout', [LoginController::class, 'logout'])->name('logout');

    // Dashboard Pemantauan — kiraan automatik daripada rekod sebenar.
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

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
    | Penugasan Entiti — Penyelaras tugaskan entiti kepada Analisis (Fasa 3)
    |----------------------------------------------------------------------
    */
    Route::middleware('can:manage-assignment')
        ->prefix('penugasan')
        ->name('penugasan.')
        ->group(function () {
            Route::get('/', [EntitiAssignmentController::class, 'index'])->name('index');

            Route::middleware('entity.access')->group(function () {
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
        });

});
