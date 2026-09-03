<?php

namespace App\Http\Controllers;

use App\Models\AnalisisInventori as RekodAnalisis;
use App\Models\EntitiAssignment;
use App\Models\User;
use App\Models\WorkflowStatus;
use App\Services\EntityAccessService;
use App\Services\EntityAssignmentService;
use App\Services\KemajuanAnalisisService;
use App\Services\LaporanSemakanService;
use App\Services\WorkflowTransitionService;
use App\Support\Halaman;
use App\Support\SektorDirectory;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * Pemantauan kedudukan setiap entiti dalam aliran kerja lima peringkat.
 *
 * FASA 4 — setiap senarai ditapis melalui accessibleBy() dan setiap route
 * bagi satu entiti dilindungi middleware `entity.access`. Pegawai Analisis
 * hanya melihat entiti yang ditugaskan kepadanya.
 *
 * Controller ini kini PAPARAN sahaja. Kemas kini peringkat secara manual
 * telah dibuang: menggerakkan Kemajuan Analisis Entiti ialah hak Pegawai
 * Analisis melalui KemajuanAnalisisController, dan tiada peranan lain —
 * termasuk Pentadbir Sistem — memiliki jalan pintas mengelilinginya.
 */
class WorkflowController extends Controller
{
    /**
     * Paparan LALAI: entiti yang Buku Kerja MPQ-nya telah diterima, yang
     * paling baru dikemas kini di atas.
     *
     * Sebelum ini skrin ini bermula kosong sehingga satu sektor dipilih.
     * Itu betul ketika senarai induk ialah satu-satunya sumber, tetapi kerja
     * sebenar bermula pada penerimaan — jadi senarai itulah yang sepatutnya
     * menyambut pengguna.
     */
    public const SKOP_DITERIMA = 'diterima';

    /**
     * Entiti yang ditugaskan kepada Pegawai Analisis yang sedang log masuk.
     *
     * Hanya bermakna kepada PA: peranan lain tidak menerima penugasan, jadi
     * pilihan ini tidak ditawarkan kepada mereka dan permintaan langsung
     * jatuh kembali kepada paparan lalai.
     */
    public const SKOP_DITUGASKAN = 'ditugaskan';

    public function __construct(
        private readonly WorkflowTransitionService $workflow,
        private readonly EntityAccessService $access,
        private readonly KemajuanAnalisisService $kemajuan,
        private readonly LaporanSemakanService $semakan,
        private readonly EntityAssignmentService $assignments,
    ) {}

    /**
     * Senarai entiti dan kedudukan workflow masing-masing.
     *
     * TIGA skop, dipilih daripada satu menu:
     *
     *   diterima    (LALAI) entiti yang Buku Kerja MPQ-nya telah diterima,
     *                       terbaru dahulu mengikut masa kemas kini.
     *   ditugaskan          entiti yang ditugaskan kepada PA yang log masuk.
     *   <kod sektor>        keseluruhan entiti sektor tersebut.
     */
    public function index(Request $request)
    {
        $pengguna = $request->user();

        $skop = $this->skop($request, $pengguna);
        $sectorCode = SektorDirectory::sektorWujud($skop) ? $skop : null;

        // Pegawai yang mengemas kini dipaparkan pada setiap baris senarai —
        // dimuatkan sekali gus supaya senarai tidak mengeluarkan satu query
        // bagi setiap entiti.
        $rekod = WorkflowStatus::query()
            ->accessibleBy($pengguna)
            ->with('updatedBy')
            ->get()
            ->keyBy('agency_code');

        /*
         * Senarai induk mengandungi ratusan entiti merentas sebelas sektor,
         * jadi "semua entiti sekali gus" bukan paparan yang boleh dibaca.
         * Setiap skop di bawah ialah potongan yang bermakna, bukan penapis
         * pilihan di atas senarai penuh.
         */
        $entiti = match (true) {
            $sectorCode !== null => $this->access->entitiDalamSektorFor($pengguna, $sectorCode),
            $skop === self::SKOP_DITUGASKAN => $this->entitiDitugaskan($pengguna),
            default => $this->entitiDiterima($pengguna),
        };

        $peringkat = $this->kemajuan->peringkatUntukBanyak($entiti->pluck('agency_code')->all());

        // Setiap baris memaparkan pegawai yang ditugaskan, status keseluruhan
        // dan kedudukan laporan; ketiga-tiganya dimuatkan sekali gus supaya
        // senarai tidak mengeluarkan query bagi setiap entiti.
        $kod = $entiti->pluck('agency_code')->all();
        $penugasan = $this->assignments->activeForMany($kod);
        $laporan = $this->semakan->untukBanyak($kod);

        $senarai = $entiti
            ->map(function (array $e) use ($rekod, $peringkat, $penugasan, $laporan) {
                $milikEntiti = $peringkat->get($e['agency_code']);

                return $e + [
                    'workflow' => $rekod->get($e['agency_code']),
                    'peringkat' => $milikEntiti,
                    'keseluruhan' => $this->kemajuan->keseluruhanDaripada($milikEntiti),
                    'bilanganSelesai' => $this->kemajuan->bilanganSelesai($milikEntiti),
                    'peringkatSemasa' => $this->kemajuan->peringkatSemasa($milikEntiti),
                    'penugasan' => $penugasan->get($e['agency_code']),
                    'laporan' => $laporan->get($e['agency_code']),
                ];
            });

        // Paparan SEKTOR disusun mengikut KOD entiti, bukan namanya: kod
        // itulah yang dipaparkan sebagai pengenal setiap baris, jadi susunan
        // mengikut nama yang tidak kelihatan membacanya sebagai tiada
        // susunan. Sama seperti panel Penetapan Entiti.
        //
        // Skop "diterima" dan "ditugaskan" TIDAK disusun semula di sini —
        // susunannya ialah masa kemas kini (terbaru dahulu), yang datang
        // daripada query dan akan hilang jika ditindih.
        if ($sectorCode !== null) {
            $senarai = $senarai->sortBy([['sector_code', 'asc'], ['agency_code', 'asc']]);
        }

        $senarai = $senarai->values();

        return view('workflow.index', [
            'entiti' => Halaman::daripada($request, $senarai),
            'sectorCode' => $sectorCode,
            'skop' => $skop,
            // Pilihan "Entiti Ditugaskan" hanya ditawarkan kepada peranan
            // yang benar-benar menerima penugasan.
            'bolehLihatDitugaskan' => $pengguna->isAnalyst(),
            // Dikira daripada peringkat 1.1, bukan daripada bilangan baris
            // workflow_status: baris itu kekal selepas entiti ditetapkan
            // semula, jadi ia akan melaporkan entiti yang tidak lagi berdaftar.
            'jumlahDidaftar' => count($this->kemajuan->kodPenerimaanSelesai($pengguna)),
            'sektor' => $this->access->sektorFor($pengguna),
        ]);
    }

    /**
     * Skop paparan yang diminta, setelah disahkan.
     *
     * `sector_code` masih dibaca supaya setiap pautan dan penanda halaman
     * lama (`?sector_code=001`) terus berfungsi seperti sebelum ini.
     *
     * Nilai yang tidak dikenali — kod sektor yang tidak wujud, atau
     * "ditugaskan" yang diminta oleh peranan tanpa penugasan — jatuh kembali
     * kepada paparan lalai dan bukan kepada ralat: menu ini penapis paparan,
     * bukan kawalan akses. Kawalan akses sebenar kekal pada
     * EntityAccessService, yang menapis SETIAP senarai di bawah.
     */
    private function skop(Request $request, User $pengguna): string
    {
        $diminta = $request->query('skop') ?? $request->query('sector_code');

        if (! is_string($diminta) || $diminta === '') {
            return self::SKOP_DITERIMA;
        }

        if (SektorDirectory::sektorWujud($diminta)) {
            return $diminta;
        }

        if ($diminta === self::SKOP_DITUGASKAN && $pengguna->isAnalyst()) {
            return self::SKOP_DITUGASKAN;
        }

        return self::SKOP_DITERIMA;
    }

    /**
     * Entiti yang Buku Kerja MPQ-nya telah diterima, terbaru dahulu.
     *
     * Susunan datang daripada KemajuanAnalisisService dan dikekalkan di sini:
     * SektorDirectory::cariEntiti() dipanggil mengikut susunan itu, bukan
     * ditapis daripada senarai induk yang tersusun mengikut kod.
     *
     * @return Collection<int, array<string, string>>
     */
    private function entitiDiterima(User $pengguna): Collection
    {
        return collect($this->kemajuan->kodDiterima($pengguna))
            ->map(fn (string $kod) => SektorDirectory::cariEntiti($kod))
            ->filter()
            ->values();
    }

    /**
     * Entiti yang ditugaskan secara AKTIF kepada pengguna ini, penugasan
     * terbaru dahulu.
     *
     * Ditapis semula melalui EntityAccessService walaupun penugasan itu
     * sendiri yang menentukan capaian PA — supaya senarai ini tidak boleh
     * terpesong daripada sumber tunggal kebenaran capaian entiti.
     *
     * @return Collection<int, array<string, string>>
     */
    private function entitiDitugaskan(User $pengguna): Collection
    {
        $kod = EntitiAssignment::query()
            ->forUser($pengguna->id)
            ->active()
            ->orderByDesc('assigned_at')
            ->orderByDesc('id')
            ->pluck('agency_code')
            ->unique();

        return $kod
            ->filter(fn (string $k) => $this->access->canAccess($pengguna, $k))
            ->map(fn (string $k) => SektorDirectory::cariEntiti($k))
            ->filter()
            ->values();
    }

    /**
     * Kedudukan workflow bagi satu entiti — stepper, status semasa dan sejarah.
     */
    public function show(Request $request, string $agencyCode)
    {
        $entiti = $this->entitiAtauGagal($agencyCode, $request);
        $workflow = WorkflowStatus::where('agency_code', $agencyCode)->first();
        $peringkat = $this->kemajuan->peringkat($agencyCode);

        return view('workflow.show', [
            'entiti' => $entiti,
            'workflow' => $workflow,
            'peringkat' => $peringkat,
            'keseluruhan' => $this->kemajuan->keseluruhanDaripada($peringkat),
            'bilanganSelesai' => $this->kemajuan->bilanganSelesai($peringkat),
            'peringkatSemasa' => $this->kemajuan->peringkatSemasa($peringkat),
            'laporan' => $this->semakan->untuk($agencyCode),
            // Penugasan Pegawai Analisis ialah kerja peringkat 1.2, jadi
            // halaman ini membawanya bersama peringkat yang lain.
            'penugasan' => $this->assignments->activeFor($agencyCode),
            'analysts' => $request->user()->can('manage-assignment')
                ? $this->assignments->analystsAvailable()
                : collect(),
            'analisis' => RekodAnalisis::where('agency_code', $agencyCode)->first(),
        ]);
    }

    /**
     * Lapisan kawalan akses kedua di dalam controller — middleware
     * `entity.access` telah menapis route, semakan ini memastikan tiada
     * laluan kod yang terlepas pandang.
     *
     * @return array<string, string>
     */
    private function entitiAtauGagal(string $agencyCode, Request $request): array
    {
        $this->access->authorize($request->user(), $agencyCode);

        $entiti = SektorDirectory::cariEntiti($agencyCode);

        abort_if($entiti === null, 404, 'Entiti tidak ditemui dalam senarai induk sektor.');

        return $entiti;
    }
}
