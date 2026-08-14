<?php

namespace App\Http\Controllers;

use App\Models\AnalisisInventori;
use App\Models\StatusLaporan;
use App\Services\EntityAccessService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class AnalisisInventoriController extends Controller
{
    public function __construct(private readonly EntityAccessService $access) {}

    /**
     * Senarai analisis + pemilihan entiti (sektor -> agensi dari config/sektor.php).
     * Senarai dan pemilih entiti ditapis mengikut akses pengguna (Fasa 4).
     */
    public function index(Request $request)
    {
        $rekod = AnalisisInventori::query()
            ->accessibleBy($request->user())
            ->latest('updated_at')
            ->paginate(10);

        return view('analisis.index', [
            'rekod' => $rekod,
            'sektor' => $this->access->sektorFor($request->user()),
        ]);
    }

    /**
     * Borang input analisis berstruktur bagi entiti dipilih.
     */
    public function borang(Request $request)
    {
        $request->validate([
            'sector_code' => ['required', 'string'],
            'agency_code' => ['required', 'string'],
        ]);

        // Kawalan akses entiti sebelum sebarang data entiti didedahkan.
        $this->access->authorize($request->user(), $request->input('agency_code'));

        [$sektor, $agensi] = $this->sahkanEntiti(
            $request->input('sector_code'),
            $request->input('agency_code'),
        );

        if (! $agensi) {
            return back()->withErrors(['agency_code' => 'Agensi tidak sah untuk sektor yang dipilih.']);
        }

        $analisis = AnalisisInventori::where('agency_code', $agensi['code'])->first();

        return view('analisis.form', [
            'sectorCode' => $request->input('sector_code'),
            'sektor' => $sektor,
            'agensi' => $agensi,
            'analisis' => $analisis,
            'data' => $analisis?->data ?? [],
        ]);
    }

    /**
     * Simpan dapatan analisis berstruktur (input pengguna -> JSON).
     */
    public function simpan(Request $request)
    {
        Gate::authorize('manage-analysis');

        // Menulis dapatan analisis bagi entiti yang tidak ditugaskan adalah
        // dilarang, walaupun permintaan dihantar terus tanpa melalui borang.
        $this->access->authorize($request->user(), $request->input('agency_code'));

        $sah = $request->validate([
            'sector_code' => ['required', 'string'],
            'agency_code' => ['required', 'string'],
            'tarikh_laporan' => ['nullable', 'date'],
            'kod_rujukan' => ['nullable', 'string', 'max:255'],
            'status_laporan' => ['required', 'in:Muktamad,Muktamad dengan Catatan,Memerlukan Tindakan Susulan'],
            'ringkasan_data' => ['required', 'in:lengkap,catatan,pengesahan,terhad'],
            'selesai' => ['nullable', 'boolean'],
        ]);

        [$sektor, $agensi] = $this->sahkanEntiti($sah['sector_code'], $sah['agency_code']);

        if (! $agensi) {
            return back()->withErrors(['agency_code' => 'Agensi tidak sah untuk sektor yang dipilih.']);
        }

        // ---- Susun dapatan berstruktur mengikut seksyen templat laporan ----

        $dataStatus = [];
        foreach (['j0', 'j1', 'j2'] as $j) {
            $dataStatus[$j] = [
                'penerimaan' => $request->input("data_status.$j.penerimaan", 'Tiada'),
                'kebolehgunaan' => $request->input("data_status.$j.kebolehgunaan", 'Tidak Boleh Digunakan'),
                'nota' => $request->input("data_status.$j.nota", ''),
            ];
        }

        $profil = [];
        foreach (config('kriptografi.kategori_profil') as $kategori) {
            $profil[$kategori] = [
                'jumlah' => (int) $request->input('profil.'.md5($kategori).'.jumlah', 0),
                'nota' => $request->input('profil.'.md5($kategori).'.nota', ''),
            ];
        }

        // Algoritma: hanya item yang ditanda (checkbox) disimpan.
        $algoritma = [];
        foreach ((array) $request->input('algoritma', []) as $kunci => $nilai) {
            if (empty($nilai['dipilih'])) {
                continue;
            }
            // Kunci borang menggunakan md5(kat|nama); nilai asal dihantar bersama.
            $algoritma[$nilai['id']] = [
                'bilangan' => $nilai['bilangan'] ?? '',
                'nota' => $nilai['nota'] ?? '',
            ];
        }

        $senaraiBaris = fn (string $medan, array $kolum) => collect((array) $request->input($medan, []))
            ->map(fn ($baris) => collect($kolum)->mapWithKeys(
                fn ($k) => [$k => trim((string) ($baris[$k] ?? ''))]
            )->all())
            ->filter(fn ($baris) => collect($baris)->filter()->isNotEmpty())
            ->values()
            ->all();

        $data = [
            'ringkasan_data' => $sah['ringkasan_data'],
            'data_status' => $dataStatus,
            'profil' => $profil,
            'algoritma' => $algoritma,
            'algoritma_lain' => trim((string) $request->input('algoritma_lain', '')),
            'protokol' => $senaraiBaris('protokol', ['nama', 'versi', 'bilangan', 'nota']),
            'pustaka' => $senaraiBaris('pustaka', ['nama', 'versi', 'bilangan', 'nota']),
            'vendor' => $senaraiBaris('vendor', ['nama', 'produk', 'versi', 'bilangan', 'nota']),
            'tindakan' => array_map('intval', (array) $request->input('tindakan', [])),
            'tindakan_lain' => trim((string) $request->input('tindakan_lain', '')),
            'kesimpulan' => array_values(array_intersect(
                (array) $request->input('kesimpulan', []),
                array_keys(config('kriptografi.kesimpulan')),
            )),
            'kesimpulan_lain' => trim((string) $request->input('kesimpulan_lain', '')),
        ];

        $analisis = AnalisisInventori::updateOrCreate(
            ['agency_code' => $agensi['code']],
            [
                'sector_code' => $sah['sector_code'],
                'sector_name' => $sektor['name'],
                'agency_name' => $agensi['name'],
                'tarikh_laporan' => $sah['tarikh_laporan'] ?? null,
                'kod_rujukan' => $sah['kod_rujukan'] ?? null,
                'status_laporan' => $sah['status_laporan'],
                'data' => $data,
                'selesai' => (bool) $request->boolean('selesai'),
                'user_id' => $request->user()->id,
            ],
        );

        // Analisis selesai menaikkan status laporan Inventori ke Dalam Proses
        // sekurang-kurangnya (kemuktamadan status kekal di tangan Penyelaras).
        if ($analisis->selesai) {
            StatusLaporan::firstOrCreate(
                ['agency_code' => $agensi['code'], 'jenis' => 'inventori'],
                [
                    'sector_code' => $sah['sector_code'],
                    'sector_name' => $sektor['name'],
                    'agency_name' => $agensi['name'],
                    'status' => 'Dalam Proses',
                    'user_id' => $request->user()->id,
                ],
            );
        }

        return redirect()
            ->route('analisis.index')
            ->with('success', 'Dapatan analisis bagi '.$agensi['name'].' telah disimpan.');
    }

    private function sahkanEntiti(string $sectorCode, string $agencyCode): array
    {
        $sektor = config('sektor.'.$sectorCode);

        if (! $sektor) {
            return [null, null];
        }

        $agensi = collect($sektor['agencies'])->firstWhere('code', $agencyCode);

        return [$sektor, $agensi];
    }
}
