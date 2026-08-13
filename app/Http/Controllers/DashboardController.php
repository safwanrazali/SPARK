<?php

namespace App\Http\Controllers;

use App\Models\AnalisisInventori;
use App\Models\MuatNaik;
use App\Models\StatusLaporan;

class DashboardController extends Controller
{
    public function index()
    {
        $sektorConfig = config('sektor');

        // Entiti dipantau = entiti yang telah terlibat dalam mana-mana proses
        // (muat naik, analisis atau status laporan). Ini menentukan skop
        // pemantauan sebenar berbanding keseluruhan senarai induk.
        $entitiTerlibat = collect()
            ->merge(MuatNaik::query()->pluck('agency_code'))
            ->merge(AnalisisInventori::query()->pluck('agency_code'))
            ->merge(StatusLaporan::query()->pluck('agency_code'))
            ->filter()
            ->unique()
            ->values();

        $jumlahEntiti = $entitiTerlibat->count();
        $analisisSelesai = AnalisisInventori::where('selesai', true)->count();
        $laporanSiap = StatusLaporan::where('status', 'Siap')->count();

        // Pecahan status 3 laporan. Rekod yang belum wujud dikira Belum Bermula.
        $jumlahRekodLaporan = $jumlahEntiti * count(StatusLaporan::JENIS);
        $siap = $laporanSiap;
        $dalamProses = StatusLaporan::where('status', 'Dalam Proses')->count();
        $belum = max(0, $jumlahRekodLaporan - $siap - $dalamProses);

        $kemajuan = $jumlahRekodLaporan > 0
            ? (int) round((($siap + $dalamProses * 0.5) / $jumlahRekodLaporan) * 100)
            : 0;

        // Kemajuan analisis mengikut sektor (entiti terlibat sahaja).
        $mengikutSektor = [];
        foreach ($sektorConfig as $kod => $sektor) {
            $kodAgensi = collect($sektor['agencies'])->pluck('code');
            $terlibat = $entitiTerlibat->intersect($kodAgensi);

            if ($terlibat->isEmpty()) {
                continue;
            }

            $selesai = AnalisisInventori::whereIn('agency_code', $terlibat)
                ->where('selesai', true)
                ->count();

            $mengikutSektor[] = [
                'nama' => $sektor['name'],
                'selesai' => $selesai,
                'jumlah' => $terlibat->count(),
            ];
        }

        return view('dashboard.index', [
            'jumlahSektor' => count($sektorConfig),
            'jumlahEntiti' => $jumlahEntiti,
            'analisisSelesai' => $analisisSelesai,
            'laporanSiap' => $laporanSiap,
            'siap' => $siap,
            'dalamProses' => $dalamProses,
            'belum' => $belum,
            'jumlahRekodLaporan' => $jumlahRekodLaporan,
            'kemajuan' => $kemajuan,
            'mengikutSektor' => $mengikutSektor,
            'aktivitiTerkini' => AnalisisInventori::latest('updated_at')->take(5)->get(),
        ]);
    }
}
