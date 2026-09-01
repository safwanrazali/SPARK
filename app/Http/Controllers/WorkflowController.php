<?php

namespace App\Http\Controllers;

use App\Models\AnalisisInventori as RekodAnalisis;
use App\Models\WorkflowStatus;
use App\Services\EntityAccessService;
use App\Services\EntityAssignmentService;
use App\Services\KemajuanAnalisisService;
use App\Services\LaporanSemakanService;
use App\Services\WorkflowTransitionService;
use App\Support\Halaman;
use App\Support\SektorDirectory;
use Illuminate\Http\Request;

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
    public function __construct(
        private readonly WorkflowTransitionService $workflow,
        private readonly EntityAccessService $access,
        private readonly KemajuanAnalisisService $kemajuan,
        private readonly LaporanSemakanService $semakan,
        private readonly EntityAssignmentService $assignments,
    ) {}

    /**
     * Senarai entiti dan kedudukan workflow masing-masing.
     * Pilih sektor untuk melihat keseluruhan entiti dalam sektor tersebut.
     */
    public function index(Request $request)
    {
        $pengguna = $request->user();
        $sectorCode = $request->query('sector_code');

        if (! SektorDirectory::sektorWujud($sectorCode)) {
            $sectorCode = null;
        }

        // Pegawai yang mengemas kini dipaparkan pada setiap baris senarai —
        // dimuatkan sekali gus supaya senarai tidak mengeluarkan satu query
        // bagi setiap entiti.
        $rekod = WorkflowStatus::query()
            ->accessibleBy($pengguna)
            ->with('updatedBy')
            ->get()
            ->keyBy('agency_code');

        /*
         * Entiti disenaraikan MENGIKUT SEKTOR: tiada senarai sehingga satu
         * sektor dipilih.
         *
         * Senarai induk mengandungi ratusan entiti merentas sebelas sektor,
         * jadi "semua entiti sekali gus" bukan paparan yang boleh dibaca.
         * Memilih sektor ialah langkah pertama aliran kerja, bukan penapis
         * pilihan di atas senarai sedia ada.
         */
        $entiti = $sectorCode !== null
            ? $this->access->entitiDalamSektorFor($pengguna, $sectorCode)
            : collect();

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
            })
            // Disusun mengikut KOD entiti, bukan namanya: kod itulah yang
            // dipaparkan sebagai pengenal setiap baris, jadi susunan mengikut
            // nama yang tidak kelihatan membacanya sebagai tiada susunan.
            // Sama seperti panel Penetapan Entiti.
            ->sortBy([['sector_code', 'asc'], ['agency_code', 'asc']])
            ->values();

        return view('workflow.index', [
            'entiti' => Halaman::daripada($request, $senarai),
            'sectorCode' => $sectorCode,
            // Dikira daripada peringkat 1.1, bukan daripada bilangan baris
            // workflow_status: baris itu kekal selepas entiti ditetapkan
            // semula, jadi ia akan melaporkan entiti yang tidak lagi berdaftar.
            'jumlahDidaftar' => count($this->kemajuan->kodPenerimaanSelesai($pengguna)),
            'sektor' => $this->access->sektorFor($pengguna),
        ]);
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
            // Sumbernya ialah KemajuanAnalisisService, bukan
            // WorkflowTransitionService: aliran semasa menulis tindakan
            // 'stage_status_changed' dan kitaran laporan, yang tiada dalam
            // perbendaharaan workflow lama. Lihat TINDAKAN_SEJARAH.
            'sejarah' => $this->kemajuan->sejarahQuery($agencyCode)
                ->paginate(Halaman::SETIAP_MUKA, ['*'], 'muka_sejarah'),
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
