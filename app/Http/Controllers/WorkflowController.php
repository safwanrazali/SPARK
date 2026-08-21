<?php

namespace App\Http\Controllers;

use App\Models\AnalisisInventori;
use App\Models\MuatNaik;
use App\Models\StatusLaporan;
use App\Models\User;
use App\Models\WorkflowStatus;
use App\Models\AnalisisInventori as RekodAnalisis;
use App\Services\EntityAccessService;
use App\Services\EntityAssignmentService;
use App\Services\KemajuanAnalisisService;
use App\Services\LaporanSemakanService;
use App\Services\WorkflowTransitionService;
use App\Support\SektorDirectory;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

/**
 * FASA 2 — pemantauan kedudukan setiap entiti dalam 7 peringkat workflow.
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

        $entiti = $sectorCode !== null
            ? $this->access->entitiDalamSektorFor($pengguna, $sectorCode)
            : $this->entitiDipantau($rekod->keys()->all(), $pengguna);

        // Setiap baris memaparkan pegawai yang ditugaskan, status keseluruhan
        // dan kedudukan laporan; ketiga-tiganya dimuatkan sekali gus supaya
        // senarai tidak mengeluarkan query bagi setiap entiti.
        $kod = $entiti->pluck('agency_code')->all();
        $peringkat = $this->kemajuan->peringkatUntukBanyak($kod);
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
            ->sortBy([['sector_code', 'asc'], ['agency_name', 'asc']])
            ->values();

        $muka = LengthAwarePaginator::resolveCurrentPage();
        $setiapMuka = 25;

        $paginator = new LengthAwarePaginator(
            $senarai->forPage($muka, $setiapMuka)->values(),
            $senarai->count(),
            $setiapMuka,
            $muka,
            ['path' => $request->url(), 'query' => $request->query()],
        );

        return view('workflow.index', [
            'entiti' => $paginator,
            'sectorCode' => $sectorCode,
            'jumlahDidaftar' => $rekod->count(),
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
            'analisis' => RekodAnalisis::where('agency_code', $agencyCode)->first(),
            'sejarah' => $workflow !== null ? $this->workflow->history($agencyCode) : collect(),
        ]);
    }

    /**
     * Entiti yang telah terlibat dalam mana-mana proses sedia ada, digabungkan
     * dengan entiti yang telah mempunyai rekod workflow. Setiap sumber ditapis
     * mengikut akses pengguna.
     *
     * @param  array<int, string>  $kodBerekod
     * @return Collection<int, array<string, string>>
     */
    private function entitiDipantau(array $kodBerekod, User $pengguna)
    {
        $kod = collect($kodBerekod)
            ->merge(MuatNaik::query()->accessibleBy($pengguna)->pluck('agency_code'))
            ->merge(AnalisisInventori::query()->accessibleBy($pengguna)->pluck('agency_code'))
            ->merge(StatusLaporan::query()->accessibleBy($pengguna)->pluck('agency_code'))
            ->filter()
            ->unique()
            ->filter(fn (string $agencyCode) => $this->access->canAccess($pengguna, $agencyCode));

        return $kod
            ->map(fn (string $agencyCode) => SektorDirectory::cariEntiti($agencyCode))
            ->filter()
            ->values();
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
