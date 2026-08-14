<?php

namespace App\Http\Controllers;

use App\Models\AnalisisInventori;
use App\Models\StatusLaporan;
use App\Services\AnalisisDraftService;
use App\Services\AuditTrailService;
use App\Services\EntityAccessService;
use App\Support\BorangAnalisis;
use App\Support\SeksyenAnalisis;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class AnalisisInventoriController extends Controller
{
    public function __construct(
        private readonly EntityAccessService $access,
        private readonly AnalisisDraftService $draf,
        private readonly AuditTrailService $audit,
    ) {}

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

        // FASA 6 — sambung semula: rekod tersimpan ditindih oleh draf semasa.
        $borang = $this->draf->borangDipulihkan($analisis);

        return view('analisis.form', [
            'sectorCode' => $request->input('sector_code'),
            'sektor' => $sektor,
            'agensi' => $agensi,
            'analisis' => $analisis,
            'borang' => $borang,
            'data' => $borang,
            'draf' => $this->draf->ringkasan($analisis),
        ]);
    }

    /**
     * FASA 6 — simpan draf borang analisis.
     *
     * Draf sengaja TIDAK disahkan supaya kerja separa siap tidak hilang.
     * Pengesahan penuh hanya berlaku pada simpanan muktamad (@see simpan).
     */
    public function draf(Request $request)
    {
        Gate::authorize('manage-analysis');

        $request->validate([
            'sector_code' => ['required', 'string'],
            'agency_code' => ['required', 'string'],
            'seksyen' => ['nullable', 'string'],
        ]);

        $this->access->authorize($request->user(), $request->input('agency_code'));

        [$sektor, $agensi] = $this->sahkanEntiti(
            $request->input('sector_code'),
            $request->input('agency_code'),
        );

        if (! $agensi) {
            return $this->balasDraf($request, false, 'Agensi tidak sah untuk sektor yang dipilih.');
        }

        $entiti = [
            'sector_code' => $request->input('sector_code'),
            'sector_name' => $sektor['name'],
            'agency_code' => $agensi['code'],
            'agency_name' => $agensi['name'],
        ];

        $analisis = $this->draf->mulakan($entiti, $request->user());

        $this->draf->simpanDraf(
            $analisis,
            BorangAnalisis::daripadaRequest($request),
            $request->user(),
            SeksyenAnalisis::wujud($request->input('seksyen')) ? $request->input('seksyen') : null,
        );

        return $this->balasDraf($request, true, 'Draf disimpan. Anda boleh menyambung semula kemudian.');
    }

    /**
     * Balasan simpanan draf — JSON untuk autosave, redirect untuk borang biasa.
     */
    private function balasDraf(Request $request, bool $berjaya, string $mesej)
    {
        if ($request->expectsJson()) {
            return response()->json([
                'berjaya' => $berjaya,
                'mesej' => $mesej,
                'disimpan_pada' => now()->format('d/m/Y H:i'),
            ], $berjaya ? 200 : 422);
        }

        return $berjaya
            ? back()->with('success', $mesej)
            : back()->withInput()->withErrors(['agency_code' => $mesej]);
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

        // Susunan dapatan berstruktur dikongsi dengan simpanan draf supaya
        // draf yang disambung semula menghasilkan struktur yang sama (Fasa 6).
        ['lajur' => $lajur, 'data' => $data] = BorangAnalisis::kepadaModel(
            BorangAnalisis::daripadaRequest($request)
        );

        $sedia = AnalisisInventori::where('agency_code', $agensi['code'])->first();
        $wujudSebelum = $sedia !== null;
        $selesaiSebelum = (bool) $sedia?->selesai;

        $analisis = AnalisisInventori::updateOrCreate(
            ['agency_code' => $agensi['code']],
            $lajur + [
                'sector_code' => $sah['sector_code'],
                'sector_name' => $sektor['name'],
                'agency_name' => $agensi['name'],
                'data' => $data,
                'selesai' => (bool) $request->boolean('selesai'),
                'user_id' => $request->user()->id,
            ],
        );

        // Dapatan telah masuk ke rekod sebenar — draf tidak lagi menjadi
        // sumber pemulihan, tetapi versinya dikekalkan sebagai sejarah.
        $this->draf->tutupDraf($analisis);

        // FASA 8 — simpanan muktamad direkodkan. Kandungan dapatan TIDAK
        // dicatat; hanya perubahan status penyiapan dan kod rujukan.
        $this->audit->rekod(
            ['agency_code' => $agensi['code'], 'agency_name' => $agensi['name']],
            'analysis_saved',
            $wujudSebelum ? ($selesaiSebelum ? 'Selesai' : 'Dalam Proses') : null,
            $analisis->selesai ? 'Selesai' : 'Dalam Proses',
            $request->user(),
            [
                'analisis_inventori_id' => $analisis->id,
                'kod_rujukan' => $analisis->kod_rujukan,
                'status_laporan' => $analisis->status_laporan,
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
