<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Model untuk jejak audit - mencatat semua perubahan penting.
 *
 * Termasuk:
 * - Perubahan status workflow
 * - Perubahan penugasan
 * - Perubahan status laporan
 * - Perubahan analisis
 * - Kelulusan/semakan
 */
class ActivityLog extends Model
{
    use HasFactory;

    protected $table = 'activity_log';

    protected $fillable = [
        'agency_code',
        'agency_name',
        'action',
        'old_value',
        'new_value',
        'changed_by_user_id',
        'changed_at',
        'metadata',
    ];

    protected $casts = [
        'changed_at' => 'datetime',
        'metadata' => 'array',
    ];

    /**
     * Jenis-jenis tindakan yang boleh dicatat.
     */
    public const ACTIONS = [
        'workflow_stage_changed' => 'Peringkat Workflow Berubah',
        'assignment_created' => 'Penugasan Dibuat',
        'assignment_updated' => 'Penugasan Diemas kini',
        'analysis_saved' => 'Analisis Disimpan',
        'draft_created' => 'Draf Dibuat',
        'draft_updated' => 'Draf Diemas kini',
        'report_generated' => 'Laporan Dijana',
        'report_submitted' => 'Laporan Diserahkan untuk Semakan',
        'report_approved' => 'Laporan Diluluskan',
        'report_rejected' => 'Laporan Ditolak',
        'approval_added' => 'Kelulusan Ditambah',
    ];

    /**
     * Pengguna yang membuat perubahan.
     */
    public function changedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by_user_id');
    }

    /**
     * Dapatkan label tindakan untuk paparan.
     */
    public function getActionLabel()
    {
        return self::ACTIONS[$this->action] ?? $this->action;
    }

    /**
     * Scope untuk menyeleksi berdasarkan entiti.
     */
    public function scopeForAgency($query, $agencyCode)
    {
        return $query->where('agency_code', $agencyCode);
    }

    /**
     * Scope untuk menyeleksi berdasarkan jenis tindakan.
     */
    public function scopeByAction($query, $action)
    {
        return $query->where('action', $action);
    }

    /**
     * Scope untuk menyeleksi berdasarkan pengguna.
     */
    public function scopeByUser($query, $userId)
    {
        return $query->where('changed_by_user_id', $userId);
    }

    /**
     * Scope untuk menyeleksi dalam rangkaian tarikh.
     */
    public function scopeBetweenDates($query, $startDate, $endDate)
    {
        return $query->whereBetween('changed_at', [$startDate, $endDate]);
    }

    /**
     * Scope untuk urutan berdasarkan terbaru.
     */
    public function scopeLatest($query)
    {
        return $query->orderByDesc('changed_at');
    }
}
