<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Services\DashboardStatistikService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * FASA 7 — papan pemuka pemantauan untuk pengurusan.
 *
 * Mengikut matriks kebenaran, papan pemuka keseluruhan terbuka kepada semua
 * peranan KECUALI Pegawai Analisis, yang bekerja daripada senarai entiti yang
 * ditugaskan kepadanya. Capaian tidak dibenarkan ditolak dengan 403, bukan
 * dialihkan — menyembunyikan pautan sahaja bukan kebenaran.
 *
 * Semua angka dikira daripada rekod sebenar oleh DashboardStatistikService.
 */
class DashboardController extends Controller
{
    public function __construct(private readonly DashboardStatistikService $statistik) {}

    public function index(Request $request)
    {
        // Lapisan kedua selepas middleware `can:view-dashboard` pada route —
        // supaya tiada laluan kod boleh sampai ke sini tanpa kebenaran.
        Gate::authorize('view-dashboard');

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
