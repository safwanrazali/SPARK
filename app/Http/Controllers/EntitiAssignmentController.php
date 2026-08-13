<?php

namespace App\Http\Controllers;

use App\Exceptions\InvalidAssignmentException;
use App\Models\EntitasAssignment;
use App\Models\User;
use App\Services\EntityAssignmentService;
use App\Support\SektorDirectory;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;

/**
 * FASA 3 — penugasan entiti kepada Pegawai Analisis.
 *
 * Aliran mengikut spesifikasi bahagian 7 dan 8:
 * Pilih Sektor → Paparkan Entiti → Pilih Entiti → Tugaskan Pegawai Analisis
 *
 * Penapisan "assigned-only" bagi Pegawai Analisis di seluruh aplikasi ialah
 * skop Fasa 4 dan tidak dilaksanakan di sini.
 */
class EntitiAssignmentController extends Controller
{
    public function __construct(private readonly EntityAssignmentService $assignments) {}

    /**
     * Senarai entiti mengikut sektor beserta penugasan semasa.
     */
    public function index(Request $request)
    {
        Gate::authorize('manage-assignment');

        $sectorCode = $request->query('sector_code');

        if (! SektorDirectory::sektorWujud($sectorCode)) {
            $sectorCode = null;
        }

        $entiti = $sectorCode !== null
            ? SektorDirectory::entitiDalamSektor($sectorCode)
            : $this->entitiDitugaskan();

        $penugasan = $this->assignments->activeForMany(
            $entiti->pluck('agency_code')->all()
        );

        $senarai = $entiti
            ->map(fn (array $e) => $e + ['penugasan' => $penugasan->get($e['agency_code'])])
            ->sortBy([['sector_code', 'asc'], ['agency_name', 'asc']])
            ->values();

        $muka = LengthAwarePaginator::resolveCurrentPage();
        $setiapMuka = 25;

        return view('penugasan.index', [
            'entiti' => new LengthAwarePaginator(
                $senarai->forPage($muka, $setiapMuka)->values(),
                $senarai->count(),
                $setiapMuka,
                $muka,
                ['path' => $request->url(), 'query' => $request->query()],
            ),
            'sectorCode' => $sectorCode,
            'analysts' => $this->assignments->analystsAvailable(),
            'jumlahAktif' => EntitasAssignment::query()->active()->count(),
        ]);
    }

    /**
     * Penugasan semasa dan sejarah penugasan bagi satu entiti.
     */
    public function show(string $agencyCode)
    {
        Gate::authorize('manage-assignment');

        $entiti = $this->entitiAtauGagal($agencyCode);

        return view('penugasan.show', [
            'entiti' => $entiti,
            'aktif' => $this->assignments->activeFor($agencyCode),
            'sejarah' => $this->assignments->history($agencyCode),
            'analysts' => $this->assignments->analystsAvailable(),
        ]);
    }

    /**
     * Tugaskan atau tukar ganti Pegawai Analisis bagi satu entiti.
     */
    public function simpan(Request $request, string $agencyCode)
    {
        Gate::authorize('manage-assignment');

        $data = $request->validate([
            'assigned_to_user_id' => ['required', 'integer', 'exists:users,id'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ], [], [
            'assigned_to_user_id' => 'pegawai analisis',
            'notes' => 'catatan',
        ]);

        $entiti = $this->entitiAtauGagal($agencyCode);
        $analyst = User::findOrFail($data['assigned_to_user_id']);

        try {
            $penugasan = $this->assignments->assign(
                $entiti,
                $analyst,
                $request->user(),
                $data['notes'] ?? null,
            );
        } catch (InvalidAssignmentException $e) {
            return back()->withInput()->withErrors(['assigned_to_user_id' => $e->getMessage()]);
        }

        return back()->with('success', sprintf(
            '%s ditugaskan kepada %s.',
            $entiti['agency_name'],
            $penugasan->assignedTo->name,
        ));
    }

    /**
     * Tarik balik penugasan aktif bagi satu entiti.
     */
    public function tarik(Request $request, string $agencyCode)
    {
        Gate::authorize('manage-assignment');

        $data = $request->validate([
            'reason' => ['nullable', 'string', 'max:1000'],
        ]);

        $entiti = $this->entitiAtauGagal($agencyCode);

        try {
            $this->assignments->unassign($agencyCode, $request->user(), $data['reason'] ?? null);
        } catch (InvalidAssignmentException $e) {
            return back()->withInput()->withErrors(['assigned_to_user_id' => $e->getMessage()]);
        }

        return back()->with('success', sprintf(
            'Penugasan bagi %s telah ditarik balik.',
            $entiti['agency_name'],
        ));
    }

    /**
     * Entiti yang pernah mempunyai rekod penugasan (paparan lalai tanpa penapis sektor).
     *
     * @return Collection<int, array<string, string>>
     */
    private function entitiDitugaskan(): Collection
    {
        return EntitasAssignment::query()
            ->distinct()
            ->pluck('agency_code')
            ->map(fn (string $agencyCode) => SektorDirectory::cariEntiti($agencyCode))
            ->filter()
            ->values();
    }

    /**
     * @return array<string, string>
     */
    private function entitiAtauGagal(string $agencyCode): array
    {
        $entiti = SektorDirectory::cariEntiti($agencyCode);

        abort_if($entiti === null, 404, 'Entiti tidak ditemui dalam senarai induk sektor.');

        return $entiti;
    }
}
