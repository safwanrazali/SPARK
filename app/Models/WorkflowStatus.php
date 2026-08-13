<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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
 */
class WorkflowStatus extends Model
{
    use HasFactory;

    protected $table = 'workflow_status';

    protected $fillable = [
        'agency_code',
        'agency_name',
        'sector_code',
        'sector_name',
        'current_stage',
        'stage_name',
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

    /**
     * Pegawai yang mengemas kini peringkat terakhir.
     */
    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by_user_id');
    }

    /**
     * Dapatkan nama peringkat untuk stage yang diberikan.
     */
    public static function getStageName($stage)
    {
        return self::WORKFLOW_STAGES[$stage] ?? 'Unknown Stage';
    }

    /**
     * Dapatkan peringkat seterusnya.
     */
    public function getNextStage()
    {
        $nextStage = $this->current_stage + 1;

        return $nextStage <= 7 ? $nextStage : null;
    }

    /**
     * Adakah entiti telah selesai semua peringkat?
     */
    public function isComplete()
    {
        return $this->current_stage >= 7;
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
