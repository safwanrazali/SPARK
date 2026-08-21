<?php

namespace App\Http\Controllers;

use App\Models\AnalisisInventori;
use App\Models\MuatNaik;
use App\Models\StatusLaporan;
use App\Services\AuditTrailService;
use App\Services\EntityAccessService;
use App\Support\Halaman;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class StatusLaporanController extends Controller
{
    public function __construct(
        private readonly EntityAccessService $access,
        private readonly AuditTrailService $audit,
    ) {}

    /**
     * Papar status tiga laporan bagi setiap entiti yang dipantau.
     * Semua peranan boleh melihat; kemas kini dikawal oleh gate manage-status.
     * Senarai ditapis mengikut entiti yang boleh diakses pengguna (Fasa 4).
     */
    public function index(Request $request)
    {
        $lajur = ['sector_code', 'sector_name', 'agency_code', 'agency_name'];
        $pengguna = $request->user();

        $entiti = collect()
            ->merge(MuatNaik::query()->accessibleBy($pengguna)->get($lajur))
            ->merge(AnalisisInventori::query()->accessibleBy($pengguna)->get($lajur))
            ->merge(StatusLaporan::query()->accessibleBy($pengguna)->get($lajur))
            ->unique('agency_code')
            ->sortBy([['sector_code', 'asc'], ['agency_name', 'asc']])
            ->values();

        $status = StatusLaporan::query()
            ->accessibleBy($pengguna)
            ->get()
            ->groupBy('agency_code');

        return view('status.index', [
            'entiti' => Halaman::daripada($request, $entiti),
            'status' => $status,
        ]);
    }

    /**
     * Kitar status: Belum Bermula -> Dalam Proses -> Siap -> Belum Bermula.
     */
    public function kitar(Request $request)
    {
        Gate::authorize('manage-status');

        $this->access->authorize($request->user(), $request->input('agency_code'));

        $data = $request->validate([
            'sector_code' => ['required', 'string'],
            'sector_name' => ['required', 'string'],
            'agency_code' => ['required', 'string'],
            'agency_name' => ['required', 'string'],
            'jenis' => ['required', 'in:'.implode(',', array_keys(StatusLaporan::JENIS))],
        ]);

        $rekod = StatusLaporan::firstOrNew([
            'agency_code' => $data['agency_code'],
            'jenis' => $data['jenis'],
        ]);

        $statusSebelum = $rekod->exists ? $rekod->status : null;

        $rekod->fill($data);
        $rekod->status = $rekod->exists ? $rekod->statusSeterusnya() : 'Dalam Proses';
        $rekod->user_id = $request->user()->id;
        $rekod->save();

        // FASA 8 — perubahan status laporan direkodkan dalam jejak audit.
        $this->audit->rekod(
            ['agency_code' => $data['agency_code'], 'agency_name' => $data['agency_name']],
            'report_status_changed',
            $statusSebelum,
            $rekod->status,
            $request->user(),
            [
                'jenis' => $data['jenis'],
                'jenis_label' => StatusLaporan::JENIS[$data['jenis']],
            ],
        );

        return back()->with('success', sprintf(
            'Status laporan %s bagi %s dikemas kini kepada %s.',
            StatusLaporan::JENIS[$data['jenis']],
            $data['agency_name'],
            $rekod->status,
        ));
    }
}
