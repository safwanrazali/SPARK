<?php

namespace App\Http\Controllers;

use App\Exceptions\InvalidWorkflowTransitionException;
use App\Models\AnalisisInventori;
use App\Models\MuatNaik;
use App\Models\StatusLaporan;
use App\Models\User;
use App\Models\WorkflowStatus;
use App\Services\EntityAccessService;
use App\Services\WorkflowTransitionService;
use App\Support\SektorDirectory;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;

/**
 * FASA 2 — pemantauan kedudukan setiap entiti dalam 7 peringkat workflow.
 *
 * FASA 4 — setiap senarai ditapis melalui accessibleBy() dan setiap route
 * bagi satu entiti dilindungi middleware `entity.access`. Pegawai Analisis
 * hanya melihat entiti yang ditugaskan kepadanya.
 */
class WorkflowController extends Controller
{
    public function __construct(
        private readonly WorkflowTransitionService $workflow,
        private readonly EntityAccessService $access,
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

        $senarai = $entiti
            ->map(fn (array $e) => $e + ['workflow' => $rekod->get($e['agency_code'])])
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

        return view('workflow.show', [
            'entiti' => $entiti,
            'workflow' => $workflow,
            'sejarah' => $workflow !== null ? $this->workflow->history($agencyCode) : collect(),
        ]);
    }

    /**
     * Daftarkan entiti ke dalam workflow pada peringkat 1.
     */
    public function mula(Request $request, string $agencyCode)
    {
        Gate::authorize('manage-workflow');

        $entiti = $this->entitiAtauGagal($agencyCode, $request);

        $this->workflow->initialize($entiti, $request->user());

        return redirect()
            ->route('workflow.show', $agencyCode)
            ->with('success', sprintf(
                '%s didaftarkan dalam workflow pada peringkat 1 — %s.',
                $entiti['agency_name'],
                WorkflowStatus::getStageName(WorkflowStatus::FIRST_STAGE),
            ));
    }

    /**
     * Tukar peringkat workflow (maju satu peringkat atau kembali dengan sebab).
     */
    public function peringkat(Request $request, string $agencyCode)
    {
        Gate::authorize('manage-workflow');
        $this->access->authorize($request->user(), $agencyCode);

        $data = $request->validate([
            'to_stage' => ['required', 'integer', 'between:'.WorkflowStatus::FIRST_STAGE.','.WorkflowStatus::LAST_STAGE],
            'reason' => ['nullable', 'string', 'max:1000'],
            'status' => ['nullable', 'in:'.implode(',', WorkflowStatus::STATUSES)],
        ], [], [
            'to_stage' => 'peringkat',
            'reason' => 'sebab',
        ]);

        $workflow = $this->workflowAtauGagal($agencyCode);

        try {
            $this->workflow->transitionTo(
                $workflow,
                $data['to_stage'],
                $request->user(),
                $data['reason'] ?? null,
                $data['status'] ?? null,
            );
        } catch (InvalidWorkflowTransitionException $e) {
            return back()->withInput()->withErrors(['to_stage' => $e->getMessage()]);
        }

        return back()->with('success', sprintf(
            '%s dikemas kini ke peringkat %d — %s.',
            $workflow->agency_name,
            $workflow->current_stage,
            $workflow->stage_name,
        ));
    }

    /**
     * Kemas kini status kerja dalam peringkat semasa.
     */
    public function status(Request $request, string $agencyCode)
    {
        Gate::authorize('manage-workflow');
        $this->access->authorize($request->user(), $agencyCode);

        $data = $request->validate([
            'status' => ['required', 'in:'.implode(',', WorkflowStatus::STATUSES)],
        ]);

        $workflow = $this->workflowAtauGagal($agencyCode);

        try {
            $this->workflow->updateStatus($workflow, $data['status'], $request->user());
        } catch (InvalidWorkflowTransitionException $e) {
            return back()->withInput()->withErrors(['status' => $e->getMessage()]);
        }

        return back()->with('success', sprintf(
            'Status peringkat %d bagi %s dikemas kini kepada %s.',
            $workflow->current_stage,
            $workflow->agency_name,
            $workflow->status,
        ));
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

    private function workflowAtauGagal(string $agencyCode): WorkflowStatus
    {
        $workflow = WorkflowStatus::where('agency_code', $agencyCode)->first();

        abort_if($workflow === null, 404, 'Entiti belum didaftarkan dalam workflow.');

        return $workflow;
    }
}
