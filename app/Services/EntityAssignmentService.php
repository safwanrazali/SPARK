<?php

namespace App\Services;

use App\Exceptions\InvalidAssignmentException;
use App\Models\ActivityLog;
use App\Models\EntitasAssignment;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * FASA 3 — satu-satunya tempat penugasan entiti boleh berubah.
 *
 * Peraturan yang dikuatkuasakan:
 * - entiti hanya boleh ditugaskan kepada Pegawai Analisis (spesifikasi bahagian 8)
 * - satu entiti hanya mempunyai SATU penugasan aktif pada satu masa
 * - penugasan berulang kepada pegawai yang sama ditolak sebagai pendua
 * - penugasan lama tidak dibuang; ia ditandakan sebagai sejarah
 *
 * Kebenaran peranan dikawal oleh gate `manage-assignment` pada lapisan
 * route/controller, bukan di sini.
 */
class EntityAssignmentService
{
    public function __construct(private readonly AuditTrailService $audit) {}

    public const ACTION_CREATED = 'assignment_created';

    public const ACTION_UPDATED = 'assignment_updated';

    public const ACTION_REMOVED = 'assignment_removed';

    /**
     * Tugaskan entiti kepada seorang Pegawai Analisis.
     *
     * Jika entiti telah mempunyai penugasan aktif kepada pegawai lain,
     * penugasan tersebut ditukar ganti secara automatik (reassign) dan
     * rekod lama dikekalkan sebagai sejarah.
     *
     * @param  array<string, string>  $entiti  sector_code, sector_name, agency_code, agency_name
     *
     * @throws InvalidAssignmentException
     */
    public function assign(
        array $entiti,
        User $analyst,
        ?User $coordinator = null,
        ?string $notes = null,
    ): EntitasAssignment {
        $this->sahkanPegawaiAnalisis($analyst);

        return DB::transaction(function () use ($entiti, $analyst, $coordinator, $notes) {
            $sediaAda = $this->activeFor($entiti['agency_code'], lock: true);

            if ($sediaAda !== null && $sediaAda->assigned_to_user_id === $analyst->id) {
                throw new InvalidAssignmentException(sprintf(
                    '%s telah ditugaskan kepada %s. Tiada penugasan pendua dibenarkan.',
                    $entiti['agency_name'],
                    $analyst->name,
                ));
            }

            $pegawaiSebelum = $sediaAda?->assignedTo;

            if ($sediaAda !== null) {
                $sediaAda->update(['status' => EntitasAssignment::STATUS_REASSIGNED]);
            }

            $penugasan = EntitasAssignment::create([
                'agency_code' => $entiti['agency_code'],
                'agency_name' => $entiti['agency_name'],
                'sector_code' => $entiti['sector_code'],
                'sector_name' => $entiti['sector_name'],
                'assigned_to_user_id' => $analyst->id,
                'assigned_by_user_id' => $coordinator?->id,
                'assigned_at' => now(),
                'status' => EntitasAssignment::STATUS_ACTIVE,
                'notes' => $notes,
            ]);

            $this->log(
                $penugasan,
                $sediaAda !== null ? self::ACTION_UPDATED : self::ACTION_CREATED,
                $pegawaiSebelum?->name,
                $analyst->name,
                $coordinator,
                [
                    'assigned_to_user_id' => $analyst->id,
                    'previous_user_id' => $pegawaiSebelum?->id,
                    'previous_assignment_id' => $sediaAda?->id,
                    'notes' => $notes,
                ],
            );

            return $penugasan;
        });
    }

    /**
     * Tukar ganti penugasan aktif kepada pegawai lain.
     * Berbeza dengan assign(), penugasan aktif WAJIB wujud terlebih dahulu.
     *
     * @param  array<string, string>  $entiti
     *
     * @throws InvalidAssignmentException
     */
    public function reassign(
        array $entiti,
        User $analyst,
        ?User $coordinator = null,
        ?string $notes = null,
    ): EntitasAssignment {
        if ($this->activeFor($entiti['agency_code']) === null) {
            throw new InvalidAssignmentException(sprintf(
                '%s belum mempunyai penugasan aktif untuk ditukar ganti.',
                $entiti['agency_name'],
            ));
        }

        return $this->assign($entiti, $analyst, $coordinator, $notes);
    }

    /**
     * Tarik balik penugasan aktif tanpa gantian.
     *
     * @throws InvalidAssignmentException
     */
    public function unassign(string $agencyCode, ?User $actor = null, ?string $reason = null): EntitasAssignment
    {
        return DB::transaction(function () use ($agencyCode, $actor, $reason) {
            $aktif = $this->activeFor($agencyCode, lock: true);

            if ($aktif === null) {
                throw new InvalidAssignmentException('Entiti ini tiada penugasan aktif untuk ditarik balik.');
            }

            $pegawai = $aktif->assignedTo;

            $aktif->update([
                'status' => EntitasAssignment::STATUS_UNASSIGNED,
                'notes' => $reason ?? $aktif->notes,
            ]);

            $this->log($aktif, self::ACTION_REMOVED, $pegawai?->name, null, $actor, [
                'previous_user_id' => $pegawai?->id,
                'reason' => $reason,
            ]);

            return $aktif;
        });
    }

    /**
     * Penugasan aktif bagi satu entiti.
     */
    public function activeFor(string $agencyCode, bool $lock = false): ?EntitasAssignment
    {
        $query = EntitasAssignment::query()
            ->forAgency($agencyCode)
            ->active();

        if ($lock) {
            $query->lockForUpdate();
        }

        return $query->first();
    }

    /**
     * Penugasan aktif bagi banyak entiti sekaligus — dikunci mengikut agency_code.
     *
     * @param  array<int, string>  $agencyCodes
     * @return Collection<string, EntitasAssignment>
     */
    public function activeForMany(array $agencyCodes): Collection
    {
        if ($agencyCodes === []) {
            return collect();
        }

        return EntitasAssignment::query()
            ->whereIn('agency_code', $agencyCodes)
            ->active()
            ->with(['assignedTo', 'assignedBy'])
            ->get()
            ->keyBy('agency_code');
    }

    /**
     * Sejarah penugasan bagi satu entiti — termasuk penugasan aktif.
     *
     * @return Collection<int, EntitasAssignment>
     */
    public function history(string $agencyCode): Collection
    {
        return EntitasAssignment::query()
            ->forAgency($agencyCode)
            ->with(['assignedTo', 'assignedBy'])
            ->terkini()
            ->get();
    }

    /**
     * Senarai Pegawai Analisis yang boleh ditugaskan.
     *
     * @return Collection<int, User>
     */
    public function analystsAvailable(): Collection
    {
        return User::query()
            ->where('role', User::ROLE_ANALYST)
            ->orderBy('name')
            ->get();
    }

    /**
     * @throws InvalidAssignmentException
     */
    protected function sahkanPegawaiAnalisis(User $user): void
    {
        if (! $user->isAnalyst()) {
            throw new InvalidAssignmentException(sprintf(
                'Entiti hanya boleh ditugaskan kepada Pegawai Analisis. %s ialah %s.',
                $user->name,
                $user->roleLabel(),
            ));
        }
    }

    /**
     * Rekod perubahan ke dalam activity_log untuk jejak audit (Fasa 8).
     *
     * @param  array<string, mixed>  $metadata
     */
    protected function log(
        EntitasAssignment $penugasan,
        string $action,
        ?string $lama,
        ?string $baharu,
        ?User $actor,
        array $metadata = [],
    ): ActivityLog {
        return $this->audit->rekod(
            [
                'agency_code' => $penugasan->agency_code,
                'agency_name' => $penugasan->agency_name,
            ],
            $action,
            $lama,
            $baharu,
            $actor,
            $metadata,
        );
    }
}
