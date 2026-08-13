<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Model untuk melacak penugasan entiti kepada Pegawai Analisis.
 *
 * Setiap penugasan menghubungkan:
 * - agency_code (entiti)
 * - assigned_to_user_id (Pegawai Analisis)
 * - assigned_by_user_id (Koordinator yang membuat penugasan)
 * - assigned_at (tarikh penugasan)
 */
class EntitasAssignment extends Model
{
    use HasFactory;

    protected $table = 'entiti_assignment';

    protected $fillable = [
        'agency_code',
        'agency_name',
        'sector_code',
        'sector_name',
        'assigned_to_user_id',
        'assigned_by_user_id',
        'assigned_at',
        'status',
        'notes',
    ];

    protected $casts = [
        'assigned_at' => 'datetime',
    ];

    /**
     * Pegawai yang ditugaskan untuk entiti ini.
     */
    public function assignedTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to_user_id');
    }

    /**
     * Pegawai yang membuat penugasan.
     */
    public function assignedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by_user_id');
    }

    /**
     * Scope untuk penugasan yang aktif.
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    /**
     * Scope untuk menyeleksi penugasan berdasarkan pegawai.
     */
    public function scopeForUser($query, $userId)
    {
        return $query->where('assigned_to_user_id', $userId);
    }

    /**
     * Scope untuk menyeleksi penugasan berdasarkan entiti.
     */
    public function scopeForAgency($query, $agencyCode)
    {
        return $query->where('agency_code', $agencyCode);
    }
}
