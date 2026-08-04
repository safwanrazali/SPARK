<?php

use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\MuatNaikController;
use App\Models\MuatNaik;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'showLoginForm'])->name('login');
    Route::post('/login', [LoginController::class, 'login'])->name('login.attempt');
});

Route::middleware('auth')->group(function () {

    Route::post('/logout', [LoginController::class, 'logout'])->name('logout');

    Route::get('/', function () {

        $jumlahMuatNaik = MuatNaik::count();

        return view('dashboard.index', compact('jumlahMuatNaik'));

    });

    // Viewing upload UI/history stays open to all authenticated roles
    // (Analyst can view inventory-related history per spec; only the
    // actual write actions are gated below).
    Route::get('/muat-naik',
        [MuatNaikController::class, 'index'])
        ->name('muat-naik.index');

    Route::get('/sejarah-muat-naik',
        [MuatNaikController::class, 'history'])
        ->name('muat-naik.history');

    Route::middleware('can:manage-upload')->group(function () {

        Route::post('/muat-naik',
            [MuatNaikController::class, 'store'])
            ->name('muat-naik.store');

        Route::post('/muat-naik/preview',
            [MuatNaikController::class, 'preview'])
            ->name('muat-naik.preview');

        Route::delete('/muat-naik/{muatNaik}',
            [MuatNaikController::class, 'destroy'])
            ->name('muat-naik.destroy');

    });

    Route::middleware('can:access-administration')
        ->prefix('administration')
        ->name('administration.')
        ->group(function () {
            Route::resource('users', UserController::class)
                ->except(['show']);
        });

});
