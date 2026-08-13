<?php

use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\AnalisisInventoriController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\DashboardController;
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
    Route::get('/muat-naik', [MuatNaikController::class, 'index'])
        ->name('muat-naik.index');

    Route::get('/sejarah-muat-naik', [MuatNaikController::class, 'history'])
        ->name('muat-naik.history');

    Route::middleware('can:manage-upload')->group(function () {
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
    | Workflow 7 Peringkat — kedudukan semasa setiap entiti (Fasa 2)
    |----------------------------------------------------------------------
    */
    Route::get('/workflow', [WorkflowController::class, 'index'])
        ->name('workflow.index');

    Route::get('/workflow/{agencyCode}', [WorkflowController::class, 'show'])
        ->name('workflow.show');

    Route::middleware('can:manage-workflow')->group(function () {
        Route::post('/workflow/{agencyCode}/mula', [WorkflowController::class, 'mula'])
            ->name('workflow.mula');
        Route::post('/workflow/{agencyCode}/peringkat', [WorkflowController::class, 'peringkat'])
            ->name('workflow.peringkat');
        Route::post('/workflow/{agencyCode}/status', [WorkflowController::class, 'status'])
            ->name('workflow.status');
    });

    /*
    |----------------------------------------------------------------------
    | Penjanaan Laporan — templat + business rules + input berstruktur
    |----------------------------------------------------------------------
    */
    Route::get('/laporan', [LaporanController::class, 'index'])
        ->name('laporan.index');

    Route::get('/laporan/inventori/{analisis}', [LaporanController::class, 'inventori'])
        ->name('laporan.inventori');

    Route::get('/laporan/inventori/{analisis}/unduh', [LaporanController::class, 'unduh'])
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
