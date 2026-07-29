<?php

use App\Http\Controllers\MuatNaikController;
use App\Models\MuatNaik;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {

    $jumlahMuatNaik =
        MuatNaik::count();

    return view(
        'dashboard.index',
        compact('jumlahMuatNaik')
    );

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

Route::get('/sejarah-muat-naik',
    [MuatNaikController::class, 'history'])
    ->name('muat-naik.history');
