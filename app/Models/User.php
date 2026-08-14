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
     * FASA 9 — peranan tambahan mengikut spesifikasi bahagian 25.
     */
    public const ROLE_KETUA_BAHAGIAN = 'head_of_division';

    public const ROLE_DOCUMENT_CONTROLLER = 'document_controller';

    public const ROLE_REKOD_ANALISIS = 'analysis_records_officer';

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
            self::ROLE_KETUA_BAHAGIAN => 'Ketua Bahagian',
            self::ROLE_DOCUMENT_CONTROLLER => 'Document Controller',
            self::ROLE_REKOD_ANALISIS => 'Pegawai Rekod Analisis',
        ];
    }

    /**
     * Senarai kunci peranan yang sah.
     *
     * @return array<int, string>
     */
    public static function roles(): array
    {
        return array_keys(self::roleLabels());
    }

    public function roleLabel(): string
    {
        return self::roleLabels()[$this->role] ?? $this->role;
    }

    /**
     * @param  array<int, string>  $roles
     */
    public function hasAnyRole(array $roles): bool
    {
        return in_array($this->role, $roles, true);
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

    public function isKetuaBahagian(): bool
    {
        return $this->role === self::ROLE_KETUA_BAHAGIAN;
    }

    public function isDocumentController(): bool
    {
        return $this->role === self::ROLE_DOCUMENT_CONTROLLER;
    }

    public function isPegawaiRekodAnalisis(): bool
    {
        return $this->role === self::ROLE_REKOD_ANALISIS;
    }

    /**
     * Peranan yang boleh melihat SEMUA entiti tanpa penapisan penugasan.
     *
     * Spesifikasi bahagian 26, baris "Lihat semua entiti":
     * Pentadbir ✓, Pegawai Penyelaras Analisis ✓, Ketua Bahagian ✓,
     * Pegawai Analisis ✗.
     */
    public function hasFullEntityVisibility(): bool
    {
        return $this->hasAnyRole([
            self::ROLE_ADMINISTRATOR,
            self::ROLE_COORDINATOR,
            self::ROLE_KETUA_BAHAGIAN,
        ]);
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
     * - Pentadbir / Penyelaras / Ketua Bahagian : semua entiti (null = tiada penapis)
     * - Pegawai Analisis                        : hanya entiti yang ditugaskan
     * - Peranan lain                            : tiada akses sehingga kebenaran disahkan
     */
    public function getAccessibleEntities()
    {
        if ($this->hasFullEntityVisibility()) {
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

        // Document Controller dan Pegawai Rekod Analisis: kebenaran belum
        // ditetapkan dalam matriks (spesifikasi bahagian 26), jadi lalai
        // adalah tiada akses — bukan akses penuh.
        return []; // No access
    }
}
