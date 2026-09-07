<?php

namespace App\Http\Controllers;

use App\Models\AnalisisInventori;
use App\Models\StatusLaporan;
use App\Services\StatusTigaLaporanService;
use App\Support\Halaman;
use Illuminate\Http\Request;

/**
 * Status Tiga Laporan — PAPARAN SAHAJA.
 *
 * Tiada tindakan kemas kini di sini, dan tiada route yang membenarkannya.
 * Ketiga-tiga status dikira daripada Kemajuan Analisis Entiti melalui
 * StatusTigaLaporanService; satu-satunya cara mengubahnya ialah dengan
 * menggerakkan aliran kerja entiti itu (PA hantar → PPA semak → KB sahkan).
 */
class StatusLaporanController extends Controller
{
    public function __construct(
        private readonly StatusTigaLaporanService $status,
    ) {}

    /**
     * Papar status tiga laporan bagi setiap entiti yang dipantau.
     * Senarai ditapis mengikut entiti yang boleh diakses pengguna (Fasa 4).
     */
    public function index(Request $request)
    {
        $lajur = ['sector_code', 'sector_name', 'agency_code', 'agency_name'];
        $pengguna = $request->user();

        $entiti = collect()
            ->merge(AnalisisInventori::query()->accessibleBy($pengguna)->get($lajur))
            ->merge(StatusLaporan::query()->accessibleBy($pengguna)->get($lajur))
            ->unique('agency_code')
            ->sortBy([['sector_code', 'asc'], ['agency_name', 'asc']])
            ->values();

        $halaman = Halaman::daripada($request, $entiti);

        return view('status.index', [
            'entiti' => $halaman,
            // Dikira hanya bagi baris yang dipaparkan — dua query, bukan satu
            // setiap baris.
            'status' => $this->status->untukBanyak(
                collect($halaman->items())->pluck('agency_code')->all(),
            ),
        ]);
    }
}
