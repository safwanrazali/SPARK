<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\TukarKataLaluanRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

/**
 * Skrin wajib tukar kata laluan pada log masuk pertama.
 *
 * Pengguna yang ditanda `must_change_password` dikunci di sini oleh
 * EnsurePasswordChanged sehingga kata laluan sementara diganti.
 */
class TukarKataLaluanController extends Controller
{
    public function edit(Request $request)
    {
        // Pengguna yang tidak dipaksa menukar tiada urusan di skrin ini.
        if (! $request->user()->must_change_password) {
            return redirect()->route('dashboard');
        }

        return view('auth.tukar-kata-laluan');
    }

    public function update(TukarKataLaluanRequest $request)
    {
        $pengguna = $request->user();

        $pengguna->forceFill([
            'password' => Hash::make($request->validated('password')),
            'must_change_password' => false,
        ])->save();

        // Sesi diperbaharui supaya kelayakan sementara tidak boleh digunakan
        // semula melalui sesi yang dirampas sebelum penukaran.
        $request->session()->regenerate();

        return redirect()
            ->route('dashboard')
            ->with('success', 'Kata laluan anda telah dikemaskini. Selamat datang.');
    }
}
