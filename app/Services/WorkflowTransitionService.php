<?php

namespace App\Services;

use App\Exceptions\InvalidWorkflowTransitionException;
use App\Models\ActivityLog;
use App\Models\User;
use App\Models\WorkflowStatus;
use Illuminate\Support\Facades\DB;

/**
 * FASA 2 — satu-satunya tempat peringkat workflow entiti boleh berubah.
 *
 * Setiap perubahan:
 * - disahkan terhadap peraturan peralihan (WorkflowStatus::canTransitionTo)
 * - menyimpan peringkat, status, tarikh status dan pegawai yang mengemas kini
 * - dicatat dalam activity_log supaya jejak audit (Fasa 8) mempunyai sumber data
 *
 * Kebenaran peranan TIDAK dikuatkuasakan di sini; ia dikawal oleh gate
 * `manage-workflow` pada lapisan route/controller supaya architecture
 * role/permission sedia ada digunakan.
 */
class WorkflowTransitionService
{
    public const ACTION_INITIALIZED = 'workflow_initialized';

    public const ACTION_STAGE_CHANGED = 'workflow_stage_changed';

    public const ACTION_STATUS_UPDATED = 'workflow_status_updated';

    /**
     * Daftarkan entiti ke dalam workflow pada peringkat 1.
     * Jika rekod telah wujud, rekod sedia ada dikembalikan tanpa perubahan.
     *
     * @param  array<string, string>  $entiti  sector_code, sector_name, agency_code, agency_name
     */
    public function initialize(array $entiti, ?User $user = null): WorkflowStatus
    {
        $sedia = WorkflowStatus::where('agency_code', $entiti['agency_code'])->first();

        if ($sedia !== null) {
            return $sedia;
        }

        return DB::transaction(function () use ($entiti, $user) {
            $workflow = WorkflowStatus::create([
                'agency_code' => $entiti['agency_code'],
                'agency_name' => $entiti['agency_name'],
                'sector_code' => $entiti['sector_code'],
                'sector_name' => $entiti['sector_name'],
                'current_stage' => WorkflowStatus::FIRST_STAGE,
                'stage_name' => WorkflowStatus::getStageName(WorkflowStatus::FIRST_STAGE),
                'status' => WorkflowStatus::DEFAULT_STATUS,
                'status_since' => now(),
                'updated_by_user_id' => $user?->id,
            ]);

            $this->log($workflow, self::ACTION_INITIALIZED, null, (string) $workflow->current_stage, $user, [
                'to_stage' => $workflow->current_stage,
                'to_stage_name' => $workflow->stage_name,
                'to_status' => $workflow->status,
            ]);

            return $workflow;
        });
    }

    /**
     * Majukan entiti ke peringkat seterusnya (n → n+1).
     *
     * @throws InvalidWorkflowTransitionException
     */
    public function advance(WorkflowStatus $workflow, ?User $user = null, ?string $status = null): WorkflowStatus
    {
        return $this->transitionTo($workflow, $workflow->current_stage + 1, $user, null, $status);
    }

    /**
     * Tukar peringkat entiti.
     *
     * - Ke hadapan: hanya satu peringkat pada satu masa.
     * - Ke belakang: mesti disertakan sebab.
     *
     * @throws InvalidWorkflowTransitionException
     */
    public function transitionTo(
        WorkflowStatus $workflow,
        $toStage,
        ?User $user = null,
        ?string $reason = null,
        ?string $status = null,
    ): WorkflowStatus {
        $ralat = $workflow->transitionError($toStage);

        if ($ralat !== null) {
            throw new InvalidWorkflowTransitionException($ralat);
        }

        $toStage = (int) $toStage;
        $mundur = $toStage < $workflow->current_stage;
        $reason = is_string($reason) ? trim($reason) : null;

        if ($mundur && ($reason === null || $reason === '')) {
            throw new InvalidWorkflowTransitionException(
                'Sebab wajib diberikan apabila entiti dikembalikan ke peringkat sebelumnya.'
            );
        }

        $status = $this->sahkanStatus($status);

        return DB::transaction(function () use ($workflow, $toStage, $user, $reason, $status, $mundur) {
            $dariStage = $workflow->current_stage;
            $dariStatus = $workflow->status;

            $workflow->fill([
                'current_stage' => $toStage,
                'stage_name' => WorkflowStatus::getStageName($toStage),
                // Peringkat baharu bermula semula pada status lalai melainkan
                // pegawai menetapkan status secara eksplisit.
                'status' => $status ?? WorkflowStatus::DEFAULT_STATUS,
                'status_since' => now(),
                'updated_by_user_id' => $user?->id,
            ]);

            if ($reason !== null && $reason !== '') {
                $workflow->notes = $reason;
            }

            $workflow->save();

            $this->log(
                $workflow,
                self::ACTION_STAGE_CHANGED,
                (string) $dariStage,
                (string) $toStage,
                $user,
                [
                    'from_stage' => $dariStage,
                    'from_stage_name' => WorkflowStatus::getStageName($dariStage),
                    'to_stage' => $toStage,
                    'to_stage_name' => $workflow->stage_name,
                    'from_status' => $dariStatus,
                    'to_status' => $workflow->status,
                    'direction' => $mundur ? 'backward' : 'forward',
                    'reason' => $reason,
                ],
            );

            return $workflow;
        });
    }

    /**
     * Kemas kini status kerja dalam peringkat semasa (tanpa menukar peringkat).
     *
     * @throws InvalidWorkflowTransitionException
     */
    public function updateStatus(WorkflowStatus $workflow, string $status, ?User $user = null): WorkflowStatus
    {
        if (! WorkflowStatus::isValidStatus($status)) {
            throw new InvalidWorkflowTransitionException(sprintf(
                'Status "%s" tidak sah. Status yang dibenarkan: %s.',
                $status,
                implode(', ', WorkflowStatus::STATUSES),
            ));
        }

        return DB::transaction(function () use ($workflow, $status, $user) {
            $dariStatus = $workflow->status;

            $workflow->fill([
                'status' => $status,
                'status_since' => now(),
                'updated_by_user_id' => $user?->id,
            ])->save();

            $this->log($workflow, self::ACTION_STATUS_UPDATED, $dariStatus, $status, $user, [
                'stage' => $workflow->current_stage,
                'stage_name' => $workflow->stage_name,
                'from_status' => $dariStatus,
                'to_status' => $status,
            ]);

            return $workflow;
        });
    }

    /**
     * Sejarah peringkat bagi satu entiti (sumber yang sama dengan jejak audit).
     */
    public function history(string $agencyCode, int $had = 50)
    {
        return ActivityLog::query()
            ->where('agency_code', $agencyCode)
            ->whereIn('action', [
                self::ACTION_INITIALIZED,
                self::ACTION_STAGE_CHANGED,
                self::ACTION_STATUS_UPDATED,
            ])
            ->with('changedBy')
            ->orderByDesc('changed_at')
            ->orderByDesc('id')
            ->limit($had)
            ->get();
    }

    /**
     * @throws InvalidWorkflowTransitionException
     */
    protected function sahkanStatus(?string $status): ?string
    {
        if ($status === null) {
            return null;
        }

        if (! WorkflowStatus::isValidStatus($status)) {
            throw new InvalidWorkflowTransitionException(sprintf(
                'Status "%s" tidak sah. Status yang dibenarkan: %s.',
                $status,
                implode(', ', WorkflowStatus::STATUSES),
            ));
        }

        return $status;
    }

    /**
     * Rekod perubahan ke dalam activity_log.
     *
     * @param  array<string, mixed>  $metadata
     */
    protected function log(
        WorkflowStatus $workflow,
        string $action,
        ?string $lama,
        ?string $baharu,
        ?User $user,
        array $metadata = [],
    ): ActivityLog {
        return ActivityLog::create([
            'agency_code' => $workflow->agency_code,
            'agency_name' => $workflow->agency_name,
            'action' => $action,
            'old_value' => $lama,
            'new_value' => $baharu,
            'changed_by_user_id' => $user?->id,
            'changed_at' => now(),
            'metadata' => $metadata,
        ]);
    }
}
