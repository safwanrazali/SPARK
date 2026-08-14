<?php

namespace App\Http\Controllers;

use App\Models\AnalisisInventori;
use App\Models\MuatNaik;
use App\Models\StatusLaporan;
use App\Services\EntityAccessService;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __construct(private readonly EntityAccessService $access) {}

    public function index(Request $request)
    {
        $pengguna = $request->user();

        // FASA 4 — dashboard keseluruhan hanya untuk peranan yang tidak
        // tertakluk kepada penapisan entiti (spesifikasi bahagian 26).
        // Pegawai Analisis melihat kiraan bagi entiti yang ditugaskan
        // kepadanya sahaja; tiada angka peringkat sistem didedahkan.
        $sektorConfig = $this->access->sektorFor($pengguna);

        // Entiti dipantau = entiti yang telah terlibat dalam mana-mana proses
        // (muat naik, analisis atau status laporan). Ini menentukan skop
        // pemantauan sebenar berbanding keseluruhan senarai induk.
        $entitiTerlibat = collect()
            ->merge(MuatNaik::query()->accessibleBy($pengguna)->pluck('agency_code'))
            ->merge(AnalisisInventori::query()->accessibleBy($pengguna)->pluck('agency_code'))
            ->merge(StatusLaporan::query()->accessibleBy($pengguna)->pluck('agency_code'))
            ->filter()
            ->unique()
            ->values();

        $jumlahEntiti = $entitiTerlibat->count();

        $analisisSelesai = AnalisisInventori::query()
            ->accessibleBy($pengguna)
            ->where('selesai', true)
            ->count();

        $laporanSiap = StatusLaporan::query()
            ->accessibleBy($pengguna)
            ->where('status', 'Siap')
            ->count();

        // Pecahan status 3 laporan. Rekod yang belum wujud dikira Belum Bermula.
        $jumlahRekodLaporan = $jumlahEntiti * count(StatusLaporan::JENIS);
        $siap = $laporanSiap;
        $dalamProses = StatusLaporan::query()
            ->accessibleBy($pengguna)
            ->where('status', 'Dalam Proses')
            ->count();
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

            $selesai = AnalisisInventori::query()
                ->accessibleBy($pengguna)
                ->whereIn('agency_code', $terlibat)
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
            'aktivitiTerkini' => AnalisisInventori::query()
                ->accessibleBy($pengguna)
                ->latest('updated_at')
                ->take(5)
                ->get(),
            'dashboardKeseluruhan' => ! $this->access->isRestricted($pengguna),
        ]);
    }
}
