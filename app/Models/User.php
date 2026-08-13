<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'username', 'email', 'password', 'role'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    public const ROLE_ADMINISTRATOR = 'administrator';

    public const ROLE_COORDINATOR = 'coordinator';

    public const ROLE_ANALYST = 'analyst';

    /**
     * Malay display labels for each role.
     *
     * @return array<string, string>
     */
    public static function roleLabels(): array
    {
        return [
            self::ROLE_ADMINISTRATOR => 'Pentadbir Sistem',
            self::ROLE_COORDINATOR => 'Pegawai Penyelaras Analisis',
            self::ROLE_ANALYST => 'Pegawai Analisis',
        ];
    }

    public function roleLabel(): string
    {
        return self::roleLabels()[$this->role] ?? $this->role;
    }

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function isAdministrator(): bool
    {
        return $this->role === self::ROLE_ADMINISTRATOR;
    }

    public function isCoordinator(): bool
    {
        return $this->role === self::ROLE_COORDINATOR;
    }

    public function isAnalyst(): bool
    {
        return $this->role === self::ROLE_ANALYST;
    }

    /**
     * PHASE 1 — Relationships untuk workflow dan assignment system.
     */

    /**
     * Entiti yang ditugaskan kepada pengguna ini (untuk Pegawai Analisis).
     */
    public function assignedEntities(): HasMany
    {
        return $this->hasMany(EntitasAssignment::class, 'assigned_to_user_id');
    }

    /**
     * Entiti yang ditugaskan oleh pengguna ini (untuk Koordinator).
     */
    public function assignmentsCreated(): HasMany
    {
        return $this->hasMany(EntitasAssignment::class, 'assigned_by_user_id');
    }

    /**
     * Peringkat workflow yang diemas kini oleh pengguna ini.
     */
    public function workflowUpdates(): HasMany
    {
        return $this->hasMany(WorkflowStatus::class, 'updated_by_user_id');
    }

    /**
     * Aktivitas/perubahan yang dibuat oleh pengguna ini.
     */
    public function activityLogs(): HasMany
    {
        return $this->hasMany(ActivityLog::class, 'changed_by_user_id');
    }

    /**
     * Draf analisis yang disimpan oleh pengguna ini.
     */
    public function draftHistories(): HasMany
    {
        return $this->hasMany(AnalisDraftHistory::class, 'saved_by_user_id');
    }

    /**
     * Kelulusan laporan yang dilakukan oleh pengguna ini.
     */
    public function approvals(): HasMany
    {
        return $this->hasMany(ApprovalLog::class, 'approved_by_user_id');
    }

    /**
     * Dapatkan entiti yang boleh dilihat oleh pengguna ini berdasarkan role.
     *
     * - Admin: lihat semua
     * - Coordinator: lihat semua
     * - Analyst: lihat hanya yang ditugaskan
     */
    public function getAccessibleEntities()
    {
        if ($this->isAdministrator() || $this->isCoordinator()) {
            // Boleh melihat semua entiti
            return null; // Signal to caller: no filter needed
        }

        if ($this->isAnalyst()) {
            // Hanya entiti yang ditugaskan
            return $this->assignedEntities()
                ->where('status', 'active')
                ->pluck('agency_code')
                ->toArray();
        }

        return []; // No access
    }
}
