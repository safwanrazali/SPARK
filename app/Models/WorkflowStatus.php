<?php

namespace App\Models;

use App\Models\Concerns\FiltersByEntityAccess;
use App\Support\AliranKerja;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Kedudukan SEMASA satu entiti dalam aliran kerja lima peringkat.
 *
 * Kedudukan itu ada dua bahagian, kerana peringkat utama boleh mengandungi
 * sub-peringkat:
 *
 *   `current_stage`      nombor peringkat UTAMA (1–5)
 *   `current_stage_key`  kunci sub-peringkat sebenar ('1.2', '3.1', …)
 *
 * Struktur peringkat itu sendiri TIDAK ditakrifkan di sini — ia milik
 * App\Support\AliranKerja, supaya satu-satunya tempat "berapa peringkat dan
 * apa namanya" dijawab ialah takrifan itu.
 *
 * Model ini turut memegang peraturan peralihan antara peringkat UTAMA.
 * Perubahan sebenar (simpan + rekod audit) dilakukan melalui
 * App\Services\WorkflowTransitionService supaya setiap peralihan sentiasa
 * direkodkan.
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
        'current_stage_key',
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
     * Lima peringkat utama — dibaca daripada takrifan aliran kerja.
     *
     * Dikekalkan sebagai pemalar dengan nama yang sama supaya paparan dan
     * ujian sedia ada yang mengulanginya tidak perlu tahu dari mana ia
     * datang; nilainya kini LIMA, bukan tujuh.
     */
    public const WORKFLOW_STAGES = AliranKerja::UTAMA;

    public const FIRST_STAGE = AliranKerja::UTAMA_PERTAMA;

    public const LAST_STAGE = AliranKerja::UTAMA_TERAKHIR;

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
     * supaya jejak audit menggunakan sumber yang sama.
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
     * Nama peringkat UTAMA yang diberikan.
     */
    public static function getStageName($stage)
    {
        return AliranKerja::labelUtama((int) $stage);
    }

    /**
     * Nama penuh kedudukan semasa, termasuk sub-peringkat jika ada.
     * Contoh: "1.2 Pendaftaran Data".
     */
    public function currentStageLabel(): string
    {
        return AliranKerja::wujud($this->current_stage_key)
            ? AliranKerja::labelPenuh($this->current_stage_key)
            : sprintf('%d %s', $this->current_stage, self::getStageName($this->current_stage));
    }

    /**
     * Adakah nombor peringkat utama sah (1–5)?
     */
    public static function isValidStage($stage): bool
    {
        return is_numeric($stage) && array_key_exists((int) $stage, AliranKerja::UTAMA);
    }

    /**
     * Adakah nilai status sah?
     */
    public static function isValidStatus($status): bool
    {
        return in_array($status, self::STATUSES, true);
    }

    /**
     * Dapatkan peringkat utama seterusnya.
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
     * Adakah peralihan ke peringkat utama $stage dibenarkan?
     *
     * - Ke hadapan: hanya satu peringkat utama pada satu masa (1 → 2 → … → 5).
     * - Ke belakang: dibenarkan tetapi mesti disertakan sebab dan direkodkan.
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
     * Adakah peringkat utama $stage telah dilalui?
     */
    public function isStageCompleted($stage): bool
    {
        return (int) $stage < $this->current_stage;
    }

    /**
     * Adakah $stage merupakan peringkat utama semasa?
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
     * Scope untuk menyeleksi berdasarkan peringkat utama.
     */
    public function scopeInStage($query, $stage)
    {
        return $query->where('current_stage', $stage);
    }
}
