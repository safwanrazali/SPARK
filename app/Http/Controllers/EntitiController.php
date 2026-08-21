<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\AnalisisInventori;
use App\Models\StatusLaporan;
use App\Models\WorkflowStatus;
use App\Services\EntityAccessService;
use App\Services\EntityAssignmentService;
use App\Support\Halaman;
use App\Support\SektorDirectory;
use Illuminate\Http\Request;

/**
 * FASA 5 — pusat maklumat entiti (spesifikasi bahagian 13).
 *
 * Menghimpunkan maklumat yang telah dibina dalam fasa terdahulu:
 * - Maklumat Entiti      (senarai induk sektor)
 * - Penugasan            (Fasa 3)
 * - Kemajuan Analisis    (Fasa 2, termasuk stepper)
 * - Dapatan Analisis     (modul sedia ada)
 * - Laporan              (status tiga laporan sedia ada)
 * - Sejarah              (activity_log)
 *
 * Tiada data baharu dikira di sini; halaman ini hanya memaparkan rekod
 * sedia ada. Setiap query ditapis mengikut kawalan akses Fasa 4 dan route
 * dilindungi middleware `entity.access`.
 */
class EntitiController extends Controller
{
    public function __construct(
        private readonly EntityAccessService $access,
        private readonly EntityAssignmentService $assignments,
    ) {}

    public function show(Request $request, string $agencyCode)
    {
        $pengguna = $request->user();

        // Lapisan kawalan akses kedua selepas middleware route.
        $this->access->authorize($pengguna, $agencyCode);

        $entiti = SektorDirectory::cariEntiti($agencyCode);

        abort_if($entiti === null, 404, 'Entiti tidak ditemui dalam senarai induk sektor.');

        return view('entiti.show', [
            'entiti' => $entiti,
            'penugasan' => $this->assignments->activeFor($agencyCode),
            'workflow' => WorkflowStatus::query()
                ->accessibleBy($pengguna)
                ->where('agency_code', $agencyCode)
                ->first(),
            'analisis' => AnalisisInventori::query()
                ->accessibleBy($pengguna)
                ->where('agency_code', $agencyCode)
                ->first(),
            'statusLaporan' => StatusLaporan::query()
                ->accessibleBy($pengguna)
                ->where('agency_code', $agencyCode)
                ->get()
                ->keyBy('jenis'),
            // Dahulunya 20 rekod terakhir tanpa jalan ke rekod lebih lama;
            // kini bernombor mengikut peraturan sepunya. Jejak penuh kekal
            // tersedia melalui pautan Jejak Audit pada halaman ini.
            'sejarah' => ActivityLog::query()
                ->accessibleBy($pengguna)
                ->where('agency_code', $agencyCode)
                ->with('changedBy')
                ->orderByDesc('changed_at')
                ->orderByDesc('id')
                ->paginate(Halaman::SETIAP_MUKA, ['*'], 'muka_sejarah'),
        ]);
    }
}
