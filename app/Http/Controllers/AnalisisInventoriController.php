<?php

namespace App\Http\Controllers;

use App\Models\AnalisisInventori;
use App\Models\StatusLaporan;
use App\Services\AnalisisDraftService;
use App\Services\AuditTrailService;
use App\Services\EntityAccessService;
use App\Services\LaporanSemakanService;
use App\Support\BorangAnalisis;
use App\Support\Halaman;
use App\Support\SeksyenAnalisis;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class AnalisisInventoriController extends Controller
{
    public function __construct(
        private readonly EntityAccessService $access,
        private readonly AnalisisDraftService $draf,
        private readonly AuditTrailService $audit,
        private readonly LaporanSemakanService $semakan,
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
            ->paginate(Halaman::SETIAP_MUKA);

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

        // Halaman kemajuan entiti ialah tempat PA melihat kenapa borang
        // dikunci dan apa tindakan seterusnya — bukan halaman sebelumnya,
        // yang mungkin tidak terbuka kepada Pegawai Analisis.
        if ($terkunci = $this->kunciSemakan($agensi['code'])) {
            return redirect()
                ->route('workflow.show', $agensi['code'])
                ->withErrors(['agency_code' => $terkunci]);
        }

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

        if ($terkunci = $this->kunciSemakan($agensi['code'])) {
            return $this->balasDraf($request, false, $terkunci);
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

        if ($terkunci = $this->kunciSemakan($agensi['code'])) {
            return back()->withInput()->withErrors(['agency_code' => $terkunci]);
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

    /**
     * Sebab borang dikunci daripada Pegawai Analisis, atau null jika terbuka.
     *
     * Aliran kerja bahagian 8 dan 11: sebaik laporan dihantar, ia berada di
     * tangan PPA atau Ketua Bahagian dan PA tidak boleh mengubahnya lagi.
     * Laporan yang telah disahkan kekal terkunci selama-lamanya. Laporan
     * yang DIKEMBALIKAN sengaja tidak dikunci — itulah caranya PA
     * membetulkan dan menghantar semula.
     *
     * Disemak di pelayan, bukan sekadar disembunyikan pada antara muka,
     * supaya borang yang dihantar terus turut ditolak.
     */
    private function kunciSemakan(string $agencyCode): ?string
    {
        $laporan = $this->semakan->untuk($agencyCode);

        if ($laporan === null || $laporan->bolehDisuntingPA()) {
            return null;
        }

        return $laporan->isSah()
            ? 'Laporan bagi entiti ini telah disahkan Ketua Bahagian dan tidak boleh diubah lagi.'
            : 'Laporan bagi entiti ini sedang disemak. Borang hanya boleh disunting semula jika laporan dikembalikan kepada anda.';
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
