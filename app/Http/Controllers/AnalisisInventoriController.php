<?php

namespace App\Http\Controllers;

use App\Exceptions\InvalidWorkflowTransitionException;
use App\Models\AnalisisInventori;
use App\Models\StatusLaporan;
use App\Models\WorkflowStatus;
use App\Services\AnalisisDraftService;
use App\Services\AuditTrailService;
use App\Services\EntityAccessService;
use App\Services\KemajuanAnalisisService;
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
        private readonly KemajuanAnalisisService $kemajuan,
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

        // Membuka borang bermakna kerja peringkat 05 telah bermula, jadi
        // "Jana Laporan" menjadi Dalam Proses. Panggilan ini tidak berkesan
        // jika peringkat itu belum terbuka (Analisis Data belum Selesai)
        // atau telah pun Selesai, jadi ia selamat dipanggil di sini.
        $this->kemajuan->tandakanDalamProses(
            $agensi['code'],
            WorkflowStatus::STAGE_JANA_LAPORAN,
            $request->user(),
        );

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
     * "Hantar" — muktamadkan dapatan analisis DAN serahkannya kepada PPA.
     *
     * Dahulu butang ini bernama "Simpan Dapatan" dan penyiapan diisytiharkan
     * sendiri melalui kotak semak. Kotak itu telah dibuang: menekan "Hantar"
     * ialah pengisytiharan itu, dan penyerahan kepada PPA berlaku serentak
     * supaya PA tidak perlu mengingati langkah kedua di skrin lain.
     *
     * Kerja separa siap disimpan melalui "Simpan Draf" (@see draf).
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
                // Menekan "Hantar" ialah pengisytiharan siap; tiada lagi
                // kotak semak berasingan untuk ditanda (atau terlupa ditanda).
                'data' => $data,
                'selesai' => true,
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

        return $this->serahkanKepadaPPA($request, $agensi + ['sector_code' => $sah['sector_code'], 'sector_name' => $sektor['name']]);
    }

    /**
     * Serahkan laporan yang baru dimuktamadkan kepada PPA.
     *
     * Penyerahan dipisahkan daripada penyimpanan kerana kedua-duanya boleh
     * berlaku secara berasingan: borang ini turut boleh dicapai melalui modul
     * Analisis Inventori Kriptografi bagi entiti yang belum sampai ke
     * peringkat 05. Dalam keadaan itu dapatan tetap disimpan — cuma tiada
     * apa untuk diserahkan lagi, dan sebabnya dinyatakan kepada pengguna
     * dan bukan disenyapkan.
     *
     * @param  array<string, string>  $agensi
     */
    private function serahkanKepadaPPA(Request $request, array $agensi)
    {
        $agencyCode = $agensi['code'];

        $entiti = [
            'agency_code' => $agencyCode,
            'agency_name' => $agensi['name'],
            'sector_code' => $agensi['sector_code'],
            'sector_name' => $agensi['sector_name'],
        ];

        $analisisSelesai = $this->kemajuan
            ->peringkat($agencyCode)
            ->get(WorkflowStatus::STAGE_ANALISIS)?->isSelesai() ?? false;

        if (! $analisisSelesai) {
            return redirect()
                ->route('analisis.index')
                ->with('success', sprintf(
                    'Dapatan analisis bagi %s telah disimpan. Laporan belum diserahkan kerana '
                    .'peringkat 04 — Analisis Data belum Selesai.',
                    $agensi['name'],
                ));
        }

        try {
            $this->semakan->hantarKepadaPPA($this->semakan->mulakan($entiti), $request->user());
        } catch (InvalidWorkflowTransitionException $e) {
            return redirect()
                ->route('workflow.show', $agencyCode)
                ->withErrors(['laporan' => $e->getMessage()]);
        }

        return redirect()
            ->route('workflow.show', $agencyCode)
            ->with('success', sprintf(
                'Laporan bagi %s telah dihantar kepada Pegawai Penyelaras Analisis untuk semakan.',
                $agensi['code'],
            ));
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
