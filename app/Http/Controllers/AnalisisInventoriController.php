<?php

namespace App\Http\Controllers;

use App\Models\AnalisisInventori;
use App\Models\StatusLaporan;
use App\Services\AnalisisDraftService;
use App\Services\AuditTrailService;
use App\Services\EntityAccessService;
use App\Services\KemajuanAnalisisService;
use App\Services\LaporanSemakanService;
use App\Support\AliranKerja;
use App\Support\BorangAnalisis;
use App\Support\Halaman;
use App\Support\SeksyenAnalisis;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

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

        // Membuka borang bermakna kerja peringkat 3.1 telah bermula, jadi
        // "Analisis Inventori Kriptografi" menjadi Dalam Proses. Panggilan
        // ini tidak berkesan jika peringkat itu belum terbuka (peringkat 2
        // belum Selesai) atau telah pun Selesai, jadi ia selamat di sini.
        $this->kemajuan->tandakanDalamProses(
            $agensi['code'],
            AliranKerja::ANALISIS_INVENTORI,
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

        // Kod rujukan dan status laporan mengikut templat rasmi Laporan
        // Analisis Inventori Kriptografi; senarai nilainya disimpan dalam
        // config/kriptografi.php supaya borang, laporan dan pengesahan
        // tidak terpesong. Pengesahan ini hanya dikenakan pada SIMPANAN
        // MUKTAMAD — laluan draf (AnalisisInventoriController@draf) sengaja
        // membenarkan kod separa supaya kerja boleh disimpan pertengahan.
        $sah = $request->validate([
            'sector_code' => ['required', 'string'],
            'agency_code' => ['required', 'string'],
            'tarikh_laporan' => ['nullable', 'date'],
            'kod_rujukan' => [
                'nullable', 'string', 'max:255',
                'regex:/^'.config('kriptografi.kod_rujukan.corak').'$/',
            ],
            'status_laporan' => ['required', Rule::in(config('kriptografi.status_laporan'))],
        ], [
            'kod_rujukan.regex' => 'Kod Rujukan Laporan mesti mengikut format '
                .config('kriptografi.kod_rujukan.format')
                .' (cth. '.config('kriptografi.kod_rujukan.contoh').').',
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

        // Baris kehadiran sahaja: entiti yang mempunyai dapatan analisis terus
        // muncul dalam senarai pemantauan. Lajur `status` di sini tidak lagi
        // dipaparkan — Status Tiga Laporan dikira daripada Kemajuan Analisis
        // Entiti (lihat App\Services\StatusTigaLaporanService).
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

        // Dahulu simpanan muktamad turut MENYERAHKAN laporan kepada PPA.
        // Penyerahan itu milik peringkat 4 dan 5, yang belum dibina — jadi
        // borang kini hanya menyimpan dapatan, dan peringkat 3.1 ditutup
        // melalui tindakan "Selesai" pada halaman Kemajuan Analisis Entiti.
        return redirect()
            ->route('workflow.show', $agensi['code'])
            ->with('success', sprintf(
                'Dapatan Analisis Inventori Kriptografi bagi %s telah disimpan.',
                $agensi['name'],
            ));
    }

    /**
     * Sebab borang dikunci daripada Pegawai Analisis, atau null jika terbuka.
     *
     * Kitaran semakan laporan (peringkat 4 dan 5) belum dibina, jadi tiada
     * rekod baharu dicipta dan semakan ini melepasi setiap entiti dalam
     * fasa semasa. Ia DIKEKALKAN kerana rekod semakan yang dicipta SEBELUM
     * restruktur masih wujud: borang bagi entiti tersebut mesti terus
     * dikunci, bukan dibuka semula secara senyap.
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
