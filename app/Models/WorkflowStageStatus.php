<?php

namespace App\Models;

use App\Models\Concerns\FiltersByEntityAccess;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Status satu peringkat workflow bagi satu entiti.
 *
 * WorkflowStatus menjawab "di mana entiti ini sekarang"; model ini menjawab
 * "apa status setiap peringkatnya". Papan pemuka membaca daripada sini supaya
 * angka tidak perlu dikira semula daripada jejak audit — pangkalan data ialah
 * sumber kebenaran, bukan keadaan UI.
 *
 * Perbendaharaan status di sini SENGAJA berbeza daripada StatusLaporan::KITARAN
 * ('Belum Bermula' / 'Dalam Proses' / 'Siap'), yang milik modul Status Tiga
 * Laporan. Peringkat berakhir dengan 'Selesai'; hanya keseluruhan entiti
 * berakhir dengan 'Siap' (lihat KemajuanAnalisisService::keseluruhan()).
 */
class WorkflowStageStatus extends Model
{
    use FiltersByEntityAccess, HasFactory;

    protected $table = 'workflow_stage_status';

    public const BELUM_MULA = 'Belum Mula';

    public const DALAM_PROSES = 'Dalam Proses';

    public const SELESAI = 'Selesai';

    /**
     * Kitaran status bagi satu peringkat.
     */
    public const STATUSES = [
        self::BELUM_MULA,
        self::DALAM_PROSES,
        self::SELESAI,
    ];

    protected $fillable = [
        'agency_code',
        'agency_name',
        'sector_code',
        'sector_name',
        'stage',
        'status',
        'started_at',
        'completed_at',
        'updated_by_user_id',
        'notes',
    ];

    protected $casts = [
        'stage' => 'integer',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by_user_id');
    }

    public static function isValidStatus(?string $status): bool
    {
        return in_array($status, self::STATUSES, true);
    }

    public function isSelesai(): bool
    {
        return $this->status === self::SELESAI;
    }

    public function isBelumMula(): bool
    {
        return $this->status === self::BELUM_MULA;
    }

    /**
     * Nama peringkat — sumbernya kekal WorkflowStatus supaya kedua-dua modul
     * tidak boleh terpesong antara satu sama lain.
     */
    public function stageName(): string
    {
        return WorkflowStatus::getStageName($this->stage);
    }

    /**
     * Kelas badge selaras dengan modul pemantauan yang lain.
     */
    public function statusBadgeClass(): string
    {
        return [
            self::SELESAI => 'status-rendah',
            self::DALAM_PROSES => 'status-sederhana',
        ][$this->status] ?? 'status-tinggi';
    }

    public function scopeForAgency($query, string $agencyCode)
    {
        return $query->where('agency_code', $agencyCode);
    }

    public function scopeAtStage($query, int $stage)
    {
        return $query->where('stage', $stage);
    }

    public function scopeSelesai($query)
    {
        return $query->where('status', self::SELESAI);
    }
}
