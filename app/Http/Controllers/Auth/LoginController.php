<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

class LoginController extends Controller
{
    /**
     * FASA 13 — hadkan percubaan log masuk.
     *
     * Kawalan keselamatan asas terhadap percubaan meneka kata laluan.
     * Kiraan dibuat mengikut gabungan nama pengguna + alamat IP supaya
     * seorang penyerang tidak boleh mengunci akaun pegawai lain hanya
     * dengan menyerang nama pengguna mereka dari IP yang berbeza.
     */
    private const PERCUBAAN_MAKSIMUM = 5;

    /** Tempoh sekatan selepas percubaan maksimum dicapai (saat). */
    private const TEMPOH_SEKATAN = 60;

    public function showLoginForm()
    {
        return view('auth.login');
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'username' => ['required', 'string'],
            'password' => ['required', 'string'],
        ]);

        $kunci = $this->kunciKadar($request);

        if (RateLimiter::tooManyAttempts($kunci, self::PERCUBAAN_MAKSIMUM)) {
            return back()
                ->withErrors(['username' => sprintf(
                    'Terlalu banyak percubaan log masuk. Sila cuba lagi dalam %d saat.',
                    RateLimiter::availableIn($kunci),
                )])
                ->onlyInput('username');
        }

        if (Auth::attempt($credentials)) {
            RateLimiter::clear($kunci);

            $request->session()->regenerate();

            // Papan pemuka kini ditolak (403) bagi peranan yang tidak
            // dibenarkan, jadi halaman mendarat mesti dipilih mengikut
            // kebenaran — bukan sentiasa '/'.
            return redirect()->intended($this->halamanMendarat());
        }

        RateLimiter::hit($kunci, self::TEMPOH_SEKATAN);

        return back()
            ->withErrors(['username' => 'Nama pengguna atau kata laluan tidak sah.'])
            ->onlyInput('username');
    }

    /**
     * Halaman pertama selepas log masuk.
     *
     * Pegawai Analisis tiada papan pemuka keseluruhan; senarai analisis
     * ialah ruang kerja mereka.
     */
    private function halamanMendarat(): string
    {
        return Gate::allows('view-dashboard')
            ? route('dashboard')
            : route('analisis.index');
    }

    public function logout(Request $request)
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }

    private function kunciKadar(Request $request): string
    {
        return 'log-masuk|'
            .Str::lower((string) $request->input('username'))
            .'|'.$request->ip();
    }
}
