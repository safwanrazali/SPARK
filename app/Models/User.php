<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'username', 'email', 'password', 'roles', 'role', 'must_change_password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * Lalai peringkat model, bukan hanya peringkat pangkalan data.
     *
     * `create()` tidak membaca semula lalai lajur, jadi tanpa ini instance
     * yang baharu dicipta mempunyai `null` dan bukan `false`.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'must_change_password' => false,
    ];

    public const ROLE_ADMINISTRATOR = 'administrator';

    public const ROLE_COORDINATOR = 'coordinator';

    public const ROLE_ANALYST = 'analyst';

    /**
     * FASA 9 — peranan tambahan mengikut spesifikasi bahagian 25.
     */
    public const ROLE_KETUA_BAHAGIAN = 'head_of_division';

    public const ROLE_PEGAWAI_KAWALAN_DOKUMEN = 'document_controller';

    public const ROLE_PENYELARAS_REKOD = 'analysis_records_officer';

    /**
     * Timbalan Pengarah II — peranan pemantauan peringkat pengurusan.
     *
     * NEEDS CONFIRMATION: pihak pengurusan belum memuktamadkan kebenaran
     * penuh peranan ini. Buat sementara ia diberi akses BACA sahaja
     * (papan pemuka dan keterlihatan entiti); tiada kuasa menulis,
     * menyemak, meluluskan atau mentadbir sehingga matriks disahkan.
     */
    public const ROLE_TIMBALAN_PENGARAH_II = 'deputy_director_ii';

    /**
     * Takrif setiap peranan: nama penuh dan singkatannya.
     *
     * Satu-satunya sumber kebenaran bagi kedua-dua bentuk nama, supaya label
     * dan singkatan tidak boleh terpesong antara satu sama lain.
     *
     * Susunan di sini ialah susunan paparan: borang pengguna, senarai dan
     * dokumentasi semuanya mengikutnya. Susun mengikut turutan organisasi
     * yang dikehendaki, bukan mengikut abjad.
     *
     * @return array<string, array{label: string, singkatan: string}>
     */
    public static function roleDefinitions(): array
    {
        return [
            self::ROLE_ADMINISTRATOR => ['label' => 'Pentadbir Sistem', 'singkatan' => 'PS'],
            self::ROLE_ANALYST => ['label' => 'Pegawai Analisis', 'singkatan' => 'PA'],
            self::ROLE_COORDINATOR => ['label' => 'Pegawai Penyelaras Analisis', 'singkatan' => 'PPA'],
            self::ROLE_PENYELARAS_REKOD => ['label' => 'Pegawai Penyelaras Rekod', 'singkatan' => 'PPR'],
            self::ROLE_PEGAWAI_KAWALAN_DOKUMEN => ['label' => 'Pegawai Kawalan Dokumen', 'singkatan' => 'PKD'],
            self::ROLE_KETUA_BAHAGIAN => ['label' => 'Ketua Bahagian', 'singkatan' => 'KB'],
            self::ROLE_TIMBALAN_PENGARAH_II => ['label' => 'Timbalan Pengarah II', 'singkatan' => 'TPII'],
        ];
    }

    /**
     * Malay display labels for each role.
     *
     * @return array<string, string>
     */
    public static function roleLabels(): array
    {
        return array_map(
            fn (array $takrif): string => $takrif['label'],
            self::roleDefinitions(),
        );
    }

    /**
     * Singkatan bagi setiap peranan (contoh: Pentadbir Sistem => PS).
     *
     * @return array<string, string>
     */
    public static function roleShortLabels(): array
    {
        return array_map(
            fn (array $takrif): string => $takrif['singkatan'],
            self::roleDefinitions(),
        );
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

    /**
     * Peranan yang dipegang pengguna ini, sentiasa sebagai senarai.
     *
     * @return array<int, string>
     */
    public function assignedRoles(): array
    {
        return $this->roles ?? [];
    }

    /**
     * Label bagi setiap peranan yang dipegang — untuk dipapar sebagai lencana.
     *
     * @return array<int, string>
     */
    public function assignedRoleLabels(): array
    {
        $labels = self::roleLabels();

        return array_map(
            fn (string $role): string => $labels[$role] ?? $role,
            $this->assignedRoles(),
        );
    }

    /**
     * Singkatan bagi setiap peranan yang dipegang.
     *
     * @return array<int, string>
     */
    public function assignedRoleShortLabels(): array
    {
        $singkatan = self::roleShortLabels();

        return array_map(
            fn (string $role): string => $singkatan[$role] ?? $role,
            $this->assignedRoles(),
        );
    }

    /**
     * Label ringkas untuk satu baris teks; digabungkan jika lebih daripada
     * satu peranan dipegang.
     */
    public function roleLabel(): string
    {
        return implode(', ', $this->assignedRoleLabels()) ?: '-';
    }

    /**
     * Akaun yang memegang peranan Pentadbir Sistem.
     *
     * Sistem mesti sentiasa mempunyai sekurang-kurangnya satu; hanya peranan
     * ini boleh menambah pengguna.
     *
     * @param  Builder<User>  $query
     * @return Builder<User>
     */
    public function scopeAdministrators(Builder $query): Builder
    {
        return $query->whereJsonContains('roles', self::ROLE_ADMINISTRATOR);
    }

    public function hasRole(string $role): bool
    {
        return in_array($role, $this->assignedRoles(), true);
    }

    /**
     * Benar jika pengguna memegang SEBARANG peranan dalam senarai diberi.
     *
     * Kebenaran berbilang peranan diselesaikan secara paling longgar: satu
     * peranan yang layak sudah memadai, jadi menambah peranan tidak sekali-kali
     * mengurangkan akses sedia ada pengguna.
     *
     * @param  array<int, string>  $roles
     */
    public function hasAnyRole(array $roles): bool
    {
        return array_intersect($roles, $this->assignedRoles()) !== [];
    }

    /**
     * Peranan tunggal — alias keserasian.
     *
     * Sumber kebenaran ialah `roles`. Alias ini dikekalkan supaya kod, seeder
     * dan ujian yang menulis `['role' => X]` atau membaca `$user->role` terus
     * berfungsi; ia memetakan kepada peranan pertama dalam senarai.
     */
    protected function role(): Attribute
    {
        return Attribute::make(
            get: fn (): ?string => $this->assignedRoles()[0] ?? null,
            set: fn (string|array|null $value): array => [
                'roles' => json_encode(array_values(array_unique(array_filter((array) $value)))),
            ],
        );
    }

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'roles' => 'array',
            'must_change_password' => 'boolean',
        ];
    }

    public function isAdministrator(): bool
    {
        return $this->hasRole(self::ROLE_ADMINISTRATOR);
    }

    public function isCoordinator(): bool
    {
        return $this->hasRole(self::ROLE_COORDINATOR);
    }

    public function isAnalyst(): bool
    {
        return $this->hasRole(self::ROLE_ANALYST);
    }

    public function isKetuaBahagian(): bool
    {
        return $this->hasRole(self::ROLE_KETUA_BAHAGIAN);
    }

    public function isPegawaiKawalanDokumen(): bool
    {
        return $this->hasRole(self::ROLE_PEGAWAI_KAWALAN_DOKUMEN);
    }

    public function isPegawaiPenyelarasRekod(): bool
    {
        return $this->hasRole(self::ROLE_PENYELARAS_REKOD);
    }

    public function isTimbalanPengarahII(): bool
    {
        return $this->hasRole(self::ROLE_TIMBALAN_PENGARAH_II);
    }

    /**
     * Peranan yang boleh melihat SEMUA entiti tanpa penapisan penugasan.
     *
     * Spesifikasi bahagian 26, baris "Lihat semua entiti":
     * Pentadbir ✓, Pegawai Penyelaras Analisis ✓, Ketua Bahagian ✓,
     * Pegawai Analisis ✗.
     *
     * Timbalan Pengarah II disertakan supaya angka papan pemuka bermakna:
     * setiap statistik ditapis melalui getAccessibleEntities(), jadi tanpa
     * keterlihatan ini papan pemukanya memaparkan sifar sepenuhnya.
     * NEEDS CONFIRMATION — keluarkan baris tersebut jika pengurusan
     * memutuskan TPII tidak boleh melihat rekod entiti.
     */
    public function hasFullEntityVisibility(): bool
    {
        return $this->hasAnyRole([
            self::ROLE_ADMINISTRATOR,
            self::ROLE_COORDINATOR,
            self::ROLE_KETUA_BAHAGIAN,
            self::ROLE_TIMBALAN_PENGARAH_II,
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
        return $this->hasMany(EntitiAssignment::class, 'assigned_to_user_id');
    }

    /**
     * Entiti yang ditugaskan oleh pengguna ini (untuk Koordinator).
     */
    public function assignmentsCreated(): HasMany
    {
        return $this->hasMany(EntitiAssignment::class, 'assigned_by_user_id');
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
     * Senarai entiti yang telah dikira bagi instance ini.
     *
     * Kawalan akses disemak berkali-kali dalam satu permintaan (middleware,
     * gate, policy dan setiap scope accessibleBy). Tanpa ingatan singkat ini,
     * setiap semakan mengeluarkan satu query penugasan — kos yang meningkat
     * seiring bilangan entiti yang dipapar.
     *
     * @var array<int, string>|null
     */
    private ?array $entitiBolehDiakses = null;

    private bool $entitiBolehDiaksesDikira = false;

    /**
     * Dapatkan entiti yang boleh dilihat oleh pengguna ini berdasarkan role.
     *
     * - Pentadbir / Penyelaras / Ketua Bahagian : semua entiti (null = tiada penapis)
     * - Pegawai Analisis                        : hanya entiti yang ditugaskan
     * - Peranan lain                            : tiada akses sehingga kebenaran disahkan
     */
    public function getAccessibleEntities()
    {
        if ($this->entitiBolehDiaksesDikira) {
            return $this->entitiBolehDiakses;
        }

        $this->entitiBolehDiaksesDikira = true;

        return $this->entitiBolehDiakses = $this->kiraEntitiBolehDiakses();
    }

    /**
     * Muat semula rekod pengguna turut membatalkan ingatan kawalan akses,
     * supaya perubahan penugasan tidak dilihat melalui nilai lama.
     */
    public function refresh()
    {
        $this->entitiBolehDiaksesDikira = false;
        $this->entitiBolehDiakses = null;

        return parent::refresh();
    }

    /**
     * @return array<int, string>|null
     */
    private function kiraEntitiBolehDiakses(): ?array
    {
        // Berbilang peranan diselesaikan secara paling longgar: pengguna yang
        // memegang peranan penyeliaan DAN Pegawai Analisis melihat semua
        // entiti, bukan hanya yang ditugaskan kepadanya.
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

        // Pegawai Kawalan Dokumen dan Pegawai Penyelaras Rekod: kebenaran belum
        // ditetapkan dalam matriks (spesifikasi bahagian 26), jadi lalai
        // adalah tiada akses — bukan akses penuh.
        return []; // No access
    }
}
