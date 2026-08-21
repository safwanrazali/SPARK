<?php

namespace App\Http\Controllers;

use App\Exceptions\InvalidAssignmentException;
use App\Models\EntitiAssignment;
use App\Models\User;
use App\Models\WorkflowStatus;
use App\Services\EntityAssignmentService;
use App\Services\KemajuanAnalisisService;
use App\Support\Halaman;
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
    public function __construct(
        private readonly EntityAssignmentService $assignments,
        private readonly KemajuanAnalisisService $kemajuan,
    ) {}

    /**
     * "Penetapan Entiti" — satu skrin dengan dua panel.
     *
     * Panel pendaftaran (peringkat 1) untuk PPR dan Ketua Bahagian; panel
     * penugasan untuk PPA. Setiap panel disediakan hanya apabila pengguna
     * berhak melihatnya, supaya tiada data dikumpulkan tanpa keperluan.
     */
    public function index(Request $request)
    {
        $bolehDaftar = Gate::allows('register-entity-data') || Gate::allows('reset-entity-registration');
        $bolehTugas = Gate::allows('manage-assignment');

        abort_unless($bolehDaftar || $bolehTugas, 403, 'Anda tidak mempunyai akses kepada Penetapan Entiti.');

        $sectorCode = $request->query('sector_code');

        if (! SektorDirectory::sektorWujud($sectorCode)) {
            $sectorCode = null;
        }

        return view('penugasan.index', [
            'sectorCode' => $sectorCode,
            'bolehDaftar' => $bolehDaftar,
            'bolehTugas' => $bolehTugas,
            'pendaftaran' => $bolehDaftar ? $this->senaraiPendaftaran($request, $sectorCode) : null,
            'entiti' => $bolehTugas ? $this->senaraiPenugasan($request, $sectorCode) : null,
            'analysts' => $bolehTugas ? $this->assignments->analystsAvailable() : collect(),
            'jumlahAktif' => $bolehTugas ? EntitiAssignment::query()->active()->count() : 0,
            'jumlahDidaftar' => count($this->kemajuan->kodPendaftaranSelesai()),
        ]);
    }

    /**
     * Panel pendaftaran — senarai induk sektor beserta status peringkat 1.
     *
     * Sumbernya ialah config/sektor.php, bukan rekod entiti, kerana PPR
     * mendaftarkan entiti yang belum mempunyai sebarang rekod lagi.
     *
     * @return LengthAwarePaginator<int, array<string, mixed>>
     */
    private function senaraiPendaftaran(Request $request, ?string $sectorCode): LengthAwarePaginator
    {
        $entiti = $sectorCode !== null
            ? SektorDirectory::entitiDalamSektor($sectorCode)
            : collect($this->kemajuan->kodPendaftaranSelesai())
                ->map(fn (string $kod) => SektorDirectory::cariEntiti($kod))
                ->filter()
                ->values();

        $peringkat = $this->kemajuan->peringkatUntukBanyak($entiti->pluck('agency_code')->all());

        $senarai = $entiti
            ->map(function (array $e) use ($peringkat) {
                $rekod = $peringkat->get($e['agency_code']);

                return $e + [
                    'pendaftaran' => $rekod?->get(WorkflowStatus::STAGE_PENDAFTARAN),
                    'keseluruhan' => $this->kemajuan->keseluruhanDaripada($rekod),
                ];
            })
            ->sortBy([['sector_code', 'asc'], ['agency_code', 'asc']])
            ->values();

        return Halaman::daripada($request, $senarai, 'muka_daftar');
    }

    /**
     * Panel penugasan — HANYA entiti yang telah menyelesaikan pendaftaran.
     *
     * Inilah kesan "entiti menjadi tersedia kepada PPA": sebelum peringkat 1
     * Selesai, entiti langsung tidak muncul di sini.
     *
     * @return LengthAwarePaginator<int, array<string, mixed>>
     */
    private function senaraiPenugasan(Request $request, ?string $sectorCode): LengthAwarePaginator
    {
        $entiti = collect($this->kemajuan->kodPendaftaranSelesai())
            ->map(fn (string $kod) => SektorDirectory::cariEntiti($kod))
            ->filter()
            ->when($sectorCode !== null, fn (Collection $e) => $e->where('sector_code', $sectorCode))
            ->values();

        $penugasan = $this->assignments->activeForMany($entiti->pluck('agency_code')->all());

        $senarai = $entiti
            ->map(fn (array $e) => $e + ['penugasan' => $penugasan->get($e['agency_code'])])
            ->sortBy([['sector_code', 'asc'], ['agency_code', 'asc']])
            ->values();

        return Halaman::daripada($request, $senarai, 'muka_tugas');
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
            'sejarah' => $this->assignments->historyQuery($agencyCode)
                ->paginate(Halaman::SETIAP_MUKA, ['*'], 'muka_sejarah'),
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

        // Entiti hanya tersedia kepada PPA selepas "Penerimaan & Pendaftaran
        // Data" Selesai. Senarai sudah menapisnya; semakan ini menutup
        // laluan permintaan langsung.
        if (! $this->kemajuan->pendaftaranSelesai($agencyCode)) {
            return back()->withErrors([
                'assigned_to_user_id' => sprintf(
                    '%s belum menyelesaikan Penerimaan & Pendaftaran Data, jadi ia belum boleh ditugaskan.',
                    $entiti['agency_code'],
                ),
            ]);
        }

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
     * @return array<string, string>
     */
    private function entitiAtauGagal(string $agencyCode): array
    {
        $entiti = SektorDirectory::cariEntiti($agencyCode);

        abort_if($entiti === null, 404, 'Entiti tidak ditemui dalam senarai induk sektor.');

        return $entiti;
    }
}
