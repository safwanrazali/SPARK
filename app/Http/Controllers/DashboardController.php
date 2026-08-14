<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Services\DashboardStatistikService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * FASA 7 — papan pemuka pemantauan untuk pengurusan.
 *
 * Mengikut spesifikasi bahagian 10 dan 26, papan pemuka keseluruhan hanya
 * untuk peranan pemantauan (Pentadbir, Pegawai Penyelaras Analisis dan
 * kelak Ketua Bahagian). Pegawai Analisis TIDAK mempunyai papan pemuka
 * keseluruhan; mereka dialihkan ke senarai kerja entiti yang ditugaskan.
 *
 * Semua angka dikira daripada rekod sebenar oleh DashboardStatistikService.
 */
class DashboardController extends Controller
{
    public function __construct(private readonly DashboardStatistikService $statistik) {}

    public function index(Request $request)
    {
        // Pegawai Analisis tiada papan pemuka keseluruhan. Mereka dialihkan
        // ke senarai kerja mereka dan bukan diberi versi papan pemuka lain.
        if (! Gate::allows('view-dashboard')) {
            return redirect()
                ->route('analisis.index')
                ->with('success', 'Papan pemuka pemantauan disediakan untuk peranan pengurusan. Berikut ialah senarai entiti yang ditugaskan kepada anda.');
        }

        $pengguna = $request->user();

        $statistik = $this->statistik->kira(
            $pengguna,
            $request->query('sector_code'),
            $request->query('dari'),
            $request->query('hingga'),
        );

        return view('dashboard.index', $statistik + [
            'aktivitiTerkini' => ActivityLog::query()
                ->accessibleBy($pengguna)
                ->with('changedBy')
                ->orderByDesc('changed_at')
                ->orderByDesc('id')
                ->limit(8)
                ->get(),
        ]);
    }
}
