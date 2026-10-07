<?php

namespace App\Http\Controllers;

use App\Support\HalamanMendarat;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Akar tapak — PENGALIH, bukan modul.
 *
 * Ia tidak memaparkan apa-apa. Tugasnya satu sahaja: membawa pengguna ke
 * halaman mendarat yang BOLEH dibukanya.
 *
 * Dahulunya '/' ialah papan pemuka itu sendiri. Pegawai Analisis — satu-satunya
 * peranan tanpa gate `view-dashboard` — menerima 403 apabila membuka alamat
 * tapak, iaitu perkara PERTAMA yang dilakukan setiap pengguna.
 *
 * Memisahkan akar daripada papan pemuka menyelesaikannya TANPA melonggarkan
 * kebenaran: papan pemuka kekal bergate pada `/papan-pemuka` dan kekal menolak
 * PA dengan 403 di sana.
 *
 * Peraturan halaman mendarat TIDAK diduakan di sini — ia dibaca daripada
 * App\Support\HalamanMendarat, sumber yang sama digunakan oleh LoginController
 * dan TukarKataLaluanController.
 */
class LamanUtamaController extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        return redirect()->to(HalamanMendarat::url($request->user()));
    }
}
