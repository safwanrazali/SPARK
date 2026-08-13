<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Model untuk melacak kelulusan dan semakan laporan.
 *
 * Menyimpan:
 * - Siapa yang meluluskan
 * - Status sebelum/sesudah
 * - Masa kelulusan
 * - Komen/catatan semakan
 */
class ApprovalLog extends Model
{
    use HasFactory;

    protected $table = 'approval_logs';

    protected $fillable = [
        'agency_code',
        'agency_name',
        'report_type',
        'status_before',
        'status_after',
        'approved_by_user_id',
        'approved_at',
        'comments',
    ];

    protected $casts = [
        'approved_at' => 'datetime',
    ];

    /**
     * Jenis laporan yang boleh diluluskan.
     */
    public const REPORT_TYPES = [
        'inventori' => 'Laporan Inventori Kriptografi',
        'risiko' => 'Laporan Analisis Risiko Migrasi PQC',
        'kesiapsiagaan' => 'Laporan Penilaian Kesiapsiagaan Migrasi PQC',
    ];

    /**
     * Pegawai yang meluluskan.
     */
    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by_user_id');
    }

    /**
     * Dapatkan label jenis laporan.
     */
    public function getReportTypeLabel()
    {
        return self::REPORT_TYPES[$this->report_type] ?? $this->report_type;
    }

    /**
     * Scope untuk menyeleksi kelulusan berdasarkan entiti.
     */
    public function scopeForAgency($query, $agencyCode)
    {
        return $query->where('agency_code', $agencyCode);
    }

    /**
     * Scope untuk menyeleksi kelulusan berdasarkan jenis laporan.
     */
    public function scopeForReportType($query, $reportType)
    {
        return $query->where('report_type', $reportType);
    }

    /**
     * Scope untuk menyeleksi kelulusan berdasarkan pemelulusan.
     */
    public function scopeByApprover($query, $userId)
    {
        return $query->where('approved_by_user_id', $userId);
    }

    /**
     * Scope untuk urutan berdasarkan terbaru.
     */
    public function scopeLatest($query)
    {
        return $query->orderByDesc('approved_at');
    }
}
