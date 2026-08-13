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
 *
 * FASA 3 — satu entiti hanya boleh mempunyai SATU penugasan aktif pada satu
 * masa. Penugasan lama tidak dibuang; ia ditandakan 'reassigned' atau
 * 'unassigned' supaya sejarah penugasan kekal. Perubahan sebenar dilakukan
 * melalui App\Services\EntityAssignmentService.
 */
class EntitasAssignment extends Model
{
    use HasFactory;

    protected $table = 'entiti_assignment';

    /**
     * Penugasan semasa bagi entiti.
     */
    public const STATUS_ACTIVE = 'active';

    /**
     * Penugasan lama yang telah digantikan oleh penugasan baharu.
     */
    public const STATUS_REASSIGNED = 'reassigned';

    /**
     * Penugasan yang ditarik balik tanpa gantian.
     */
    public const STATUS_UNASSIGNED = 'unassigned';

    /**
     * Label paparan bagi setiap status penugasan.
     *
     * @var array<string, string>
     */
    public const STATUSES = [
        self::STATUS_ACTIVE => 'Aktif',
        self::STATUS_REASSIGNED => 'Ditukar Ganti',
        self::STATUS_UNASSIGNED => 'Ditarik Balik',
    ];

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
     * `active_flag` ialah penanda teknikal untuk kekangan unik
     * (agency_code, active_flag) — 1 apabila aktif, NULL apabila tidak.
     * Ia diselenggara di sini supaya setiap laluan simpan kekal konsisten
     * dan tidak boleh terpesong daripada nilai `status`.
     */
    protected static function booted(): void
    {
        static::saving(function (self $assignment) {
            $assignment->active_flag = $assignment->status === self::STATUS_ACTIVE ? 1 : null;
        });
    }

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
     * Adakah penugasan ini yang sedang berkuat kuasa?
     */
    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    /**
     * Label paparan bagi status penugasan.
     */
    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }

    /**
     * Kelas badge selaras dengan modul pemantauan yang lain.
     */
    public function statusBadgeClass(): string
    {
        return [
            self::STATUS_ACTIVE => 'status-rendah',
            self::STATUS_REASSIGNED => 'status-sederhana',
        ][$this->status] ?? 'status-tinggi';
    }

    /**
     * Scope untuk penugasan yang aktif.
     */
    public function scopeActive($query)
    {
        return $query->where('status', self::STATUS_ACTIVE);
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

    /**
     * Scope untuk sejarah penugasan — terbaharu di atas.
     */
    public function scopeTerkini($query)
    {
        return $query->orderByDesc('assigned_at')->orderByDesc('id');
    }
}
