<?php

namespace App\Models;

use App\Models\Concerns\FiltersByEntityAccess;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Model untuk melacak peringkat workflow semasa setiap entiti.
 *
 * Setiap entiti melalui 7 peringkat workflow:
 * 1. Penerimaan & Pendaftaran Data
 * 2. Semakan Awal Data
 * 3. Penyediaan & Pengesahan Data
 * 4. Pelaksanaan Analisis
 * 5. Penjanaan Laporan
 * 6. Semakan & Kelulusan
 * 7. Penyerahan & Penutupan
 *
 * FASA 2 — model ini turut memegang peraturan peralihan peringkat.
 * Perubahan sebenar (simpan + rekod audit) dilakukan melalui
 * App\Services\WorkflowTransitionService supaya setiap peralihan
 * sentiasa direkodkan.
 */
class WorkflowStatus extends Model
{
    use FiltersByEntityAccess, HasFactory;

    protected $table = 'workflow_status';

    protected $fillable = [
        'agency_code',
        'agency_name',
        'sector_code',
        'sector_name',
        'current_stage',
        'stage_name',
        'status',
        'status_since',
        'updated_by_user_id',
        'notes',
    ];

    protected $casts = [
        'status_since' => 'datetime',
        'current_stage' => 'integer',
    ];

    /**
     * Definisi 7 peringkat workflow.
     */
    public const WORKFLOW_STAGES = [
        1 => 'Penerimaan & Pendaftaran Data',
        2 => 'Semakan Awal Data',
        3 => 'Penyediaan & Pengesahan Data',
        4 => 'Pelaksanaan Analisis',
        5 => 'Penjanaan Laporan',
        6 => 'Semakan & Kelulusan',
        7 => 'Penyerahan & Penutupan',
    ];

    public const FIRST_STAGE = 1;

    public const LAST_STAGE = 7;

    /**
     * Status kerja di dalam peringkat semasa.
     *
     * Menggunakan semula kitaran status sedia ada (StatusLaporan::KITARAN)
     * supaya tiada perbendaharaan status pendua diperkenalkan.
     */
    public const STATUSES = StatusLaporan::KITARAN;

    public const DEFAULT_STATUS = 'Belum Bermula';

    /**
     * Pegawai yang mengemas kini peringkat terakhir.
     */
    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by_user_id');
    }

    /**
     * Sejarah peringkat bagi entiti ini — dicatat dalam activity_log
     * supaya jejak audit (Fasa 8) menggunakan sumber yang sama.
     */
    public function activityLogs(): HasMany
    {
        return $this->hasMany(ActivityLog::class, 'agency_code', 'agency_code');
    }

    /**
     * Sejarah peringkat sahaja (tidak termasuk tindakan lain).
     */
    public function stageHistory(): HasMany
    {
        return $this->activityLogs()
            ->whereIn('action', [
                'workflow_initialized',
                'workflow_stage_changed',
                'workflow_status_updated',
            ])
            ->orderByDesc('changed_at')
            ->orderByDesc('id');
    }

    /**
     * Dapatkan nama peringkat untuk stage yang diberikan.
     */
    public static function getStageName($stage)
    {
        return self::WORKFLOW_STAGES[$stage] ?? 'Unknown Stage';
    }

    /**
     * Adakah nombor peringkat sah (1–7)?
     */
    public static function isValidStage($stage): bool
    {
        return is_numeric($stage) && array_key_exists((int) $stage, self::WORKFLOW_STAGES);
    }

    /**
     * Adakah nilai status sah?
     */
    public static function isValidStatus($status): bool
    {
        return in_array($status, self::STATUSES, true);
    }

    /**
     * Dapatkan peringkat seterusnya.
     */
    public function getNextStage()
    {
        $nextStage = $this->current_stage + 1;

        return $nextStage <= self::LAST_STAGE ? $nextStage : null;
    }

    /**
     * Adakah entiti telah selesai semua peringkat?
     */
    public function isComplete()
    {
        return $this->current_stage >= self::LAST_STAGE;
    }

    /**
     * Adakah peralihan ke peringkat $stage dibenarkan?
     *
     * Peraturan (spesifikasi bahagian 12):
     * - Ke hadapan: hanya satu peringkat pada satu masa (01 → 02 → … → 07).
     *   Lompatan rawak (cth 1 → 3) tidak dibenarkan.
     * - Ke belakang: dibenarkan tetapi mesti disertakan sebab dan direkodkan.
     * - Peringkat mesti berada dalam julat 1–7.
     */
    public function canTransitionTo($stage): bool
    {
        return $this->transitionError($stage) === null;
    }

    /**
     * Adakah peralihan ini memerlukan sebab?
     * Ya bagi setiap pengunduran ke peringkat sebelumnya.
     */
    public function requiresReason($stage): bool
    {
        return self::isValidStage($stage) && (int) $stage < $this->current_stage;
    }

    /**
     * Mesej kenapa peralihan tidak dibenarkan, atau null jika dibenarkan.
     */
    public function transitionError($stage): ?string
    {
        if (! self::isValidStage($stage)) {
            return sprintf(
                'Peringkat %s tidak sah. Peringkat mesti antara %d hingga %d.',
                is_scalar($stage) ? (string) $stage : gettype($stage),
                self::FIRST_STAGE,
                self::LAST_STAGE,
            );
        }

        $stage = (int) $stage;

        if ($stage === $this->current_stage) {
            return 'Entiti sudah berada pada peringkat ini. Gunakan kemas kini status untuk perubahan dalam peringkat yang sama.';
        }

        if ($stage > $this->current_stage + 1) {
            return sprintf(
                'Peringkat mesti dilalui secara berturutan. Peringkat seterusnya bagi entiti ini ialah %d — %s.',
                $this->current_stage + 1,
                self::getStageName($this->current_stage + 1),
            );
        }

        return null;
    }

    /**
     * Adakah peringkat $stage telah dilalui?
     */
    public function isStageCompleted($stage): bool
    {
        return (int) $stage < $this->current_stage;
    }

    /**
     * Adakah $stage merupakan peringkat semasa?
     */
    public function isCurrentStage($stage): bool
    {
        return (int) $stage === $this->current_stage;
    }

    /**
     * Kemajuan entiti dikira daripada peringkat semasa — bukan nilai manual.
     */
    public function progressPercentage(): int
    {
        return (int) round(($this->current_stage / self::LAST_STAGE) * 100);
    }

    /**
     * Kelas badge untuk status semasa (selaras dengan modul Status Laporan).
     */
    public function statusBadgeClass(): string
    {
        return [
            'Siap' => 'status-rendah',
            'Dalam Proses' => 'status-sederhana',
        ][$this->status] ?? 'status-tinggi';
    }

    /**
     * Scope untuk menyeleksi berdasarkan sektor.
     */
    public function scopeInSector($query, $sectorCode)
    {
        return $query->where('sector_code', $sectorCode);
    }

    /**
     * Scope untuk menyeleksi berdasarkan peringkat.
     */
    public function scopeInStage($query, $stage)
    {
        return $query->where('current_stage', $stage);
    }
}
