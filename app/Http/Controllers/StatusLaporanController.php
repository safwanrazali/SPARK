<?php

namespace App\Http\Controllers;

use App\Models\AnalisisInventori;
use App\Models\MuatNaik;
use App\Models\StatusLaporan;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class StatusLaporanController extends Controller
{
    /**
     * Papar status tiga laporan bagi setiap entiti yang dipantau.
     * Semua peranan boleh melihat; kemas kini dikawal oleh gate manage-status.
     */
    public function index()
    {
        $entiti = collect()
            ->merge(MuatNaik::query()->get(['sector_code', 'sector_name', 'agency_code', 'agency_name']))
            ->merge(AnalisisInventori::query()->get(['sector_code', 'sector_name', 'agency_code', 'agency_name']))
            ->merge(StatusLaporan::query()->get(['sector_code', 'sector_name', 'agency_code', 'agency_name']))
            ->unique('agency_code')
            ->sortBy([['sector_code', 'asc'], ['agency_name', 'asc']])
            ->values();

        $status = StatusLaporan::all()->groupBy('agency_code');

        return view('status.index', compact('entiti', 'status'));
    }

    /**
     * Kitar status: Belum Bermula -> Dalam Proses -> Siap -> Belum Bermula.
     */
    public function kitar(Request $request)
    {
        Gate::authorize('manage-status');

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

        $rekod->fill($data);
        $rekod->status = $rekod->exists ? $rekod->statusSeterusnya() : 'Dalam Proses';
        $rekod->user_id = $request->user()->id;
        $rekod->save();

        return back()->with('success', sprintf(
            'Status laporan %s bagi %s dikemas kini kepada %s.',
            StatusLaporan::JENIS[$data['jenis']],
            $data['agency_name'],
            $rekod->status,
        ));
    }
}
