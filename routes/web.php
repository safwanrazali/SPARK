<?php

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

    Route::get('/muat-naik',
        [MuatNaikController::class, 'index'])
        ->name('muat-naik.index');

    Route::post('/muat-naik',
        [MuatNaikController::class, 'store'])
        ->name('muat-naik.store');

    Route::post('/muat-naik/preview',
        [MuatNaikController::class, 'preview'])
        ->name('muat-naik.preview');

    Route::delete('/muat-naik/{muatNaik}',
        [MuatNaikController::class, 'destroy'])
        ->name('muat-naik.destroy');

    Route::get('/sejarah-muat-naik',
        [MuatNaikController::class, 'history'])
        ->name('muat-naik.history');

});
