<?php

namespace Tests\Feature;

use App\Models\AnalisisInventori;
use App\Models\User;
use App\Services\EntityAccessService;
use App\Services\EntityAssignmentService;
use App\Support\SektorDirectory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * FASA 9 — peranan dan kebenaran lengkap.
 *
 * Setiap peranan diuji terhadap: papan pemuka, sektor, entiti, penugasan,
 * laporan, semakan/kelulusan dan fungsi pentadbiran.
 *
 * Sumber kebenaran ialah matriks spesifikasi bahagian 26. Sel "ikut
 * permission" dilayan sebagai TIDAK dibenarkan kerana kebenaran sebenar
 * belum disahkan.
 */
class Phase9RolesPermissionsTest extends TestCase
{
    use RefreshDatabase;

    private const ALPHA = 'A010101';

    private const BETA = 'A010102';

    private function pengguna(string $role): User
    {
        return User::factory()->create(['role' => $role]);
    }

    /**
     * Entiti ALPHA ditugaskan kepada pengguna yang diberikan (jika analis).
     */
    private function tugaskan(User $analyst, string $agencyCode = self::ALPHA): void
    {
        app(EntityAssignmentService::class)->assign(
            SektorDirectory::cariEntiti($agencyCode),
            $analyst,
            $this->pengguna(User::ROLE_COORDINATOR),
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Pendaftaran peranan
    |--------------------------------------------------------------------------
    */

    public function test_kesemua_peranan_sasaran_wujud(): void
    {
        $this->assertSame([
            'administrator' => 'Pentadbir Sistem',
            'analyst' => 'Pegawai Analisis',
            'coordinator' => 'Pegawai Penyelaras Analisis',
            'analysis_records_officer' => 'Pegawai Penyelaras Rekod',
            'document_controller' => 'Pegawai Kawalan Dokumen',
            'head_of_division' => 'Ketua Bahagian',
            'deputy_director_ii' => 'Timbalan Pengarah II',
        ], User::roleLabels());

        $this->assertCount(7, User::roles());
    }

    public function test_pentadbir_boleh_mencipta_pengguna_dengan_peranan_baharu(): void
    {
        $this->actingAs($this->pengguna(User::ROLE_ADMINISTRATOR))
            ->post(route('administration.users.store'), [
                'name' => 'Ketua Satu',
                'username' => 'ketua1',
                'email' => 'ketua1@example.my',
                'roles' => [User::ROLE_KETUA_BAHAGIAN],
                'password' => 'KataLaluan#2026x',
                'password_confirmation' => 'KataLaluan#2026x',
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame(
            [User::ROLE_KETUA_BAHAGIAN],
            User::where('username', 'ketua1')->sole()->assignedRoles(),
        );
    }

    public function test_peranan_tidak_sah_ditolak(): void
    {
        $this->actingAs($this->pengguna(User::ROLE_ADMINISTRATOR))
            ->post(route('administration.users.store'), [
                'name' => 'Penyusup',
                'username' => 'penyusup',
                'email' => 'penyusup@example.my',
                'roles' => ['superuser'],
                'password' => 'KataLaluan#2026x',
                'password_confirmation' => 'KataLaluan#2026x',
            ])
            ->assertSessionHasErrors('roles.0');
    }

    /*
    |--------------------------------------------------------------------------
    | Matriks kebenaran — gate demi gate
    |--------------------------------------------------------------------------
    */

    /**
     * @return array<string, array{0: string, 1: array<string, bool>}>
     */
    public static function matriksPeranan(): array
    {
        return [
            'Pentadbir Sistem' => [User::ROLE_ADMINISTRATOR, [
                'view-dashboard' => true,
                'view-all-entities' => true,
                'manage-assignment' => true,
                'manage-analysis' => true,
                'manage-workflow' => true,
                'manage-status' => true,
                'review-report' => true,
                'approve-report' => true,
                'view-audit-trail' => true,
                'access-administration' => true,
            ]],
            'Pegawai Penyelaras Analisis' => [User::ROLE_COORDINATOR, [
                'view-dashboard' => true,
                'view-all-entities' => true,
                'manage-assignment' => true,
                'manage-analysis' => false,   // "ikut permission" → tidak diberikan
                'manage-workflow' => true,
                'manage-status' => true,
                'review-report' => true,
                'approve-report' => false,    // "ikut permission" → tidak diberikan
                'view-audit-trail' => true,
                'access-administration' => false,
            ]],
            'Pegawai Analisis' => [User::ROLE_ANALYST, [
                'view-dashboard' => false,
                'view-all-entities' => false,
                'manage-assignment' => false,
                'manage-analysis' => true,
                'manage-workflow' => false,
                'manage-status' => false,
                'review-report' => false,     // "ikut permission" → tidak diberikan
                'approve-report' => false,
                'view-audit-trail' => false,  // "ikut permission" → tidak diberikan
                'access-administration' => false,
            ]],
            'Ketua Bahagian' => [User::ROLE_KETUA_BAHAGIAN, [
                'view-dashboard' => true,
                'view-all-entities' => true,
                'manage-assignment' => false,
                'manage-analysis' => false,
                'manage-workflow' => false,
                'manage-status' => false,
                'review-report' => true,
                'approve-report' => true,
                'view-audit-trail' => true,
                'access-administration' => false,
            ]],
            // Tiada baris dalam matriks — lalai tolak sehingga disahkan.
            'Pegawai Kawalan Dokumen' => [User::ROLE_PEGAWAI_KAWALAN_DOKUMEN, [
                'view-dashboard' => false,
                'view-all-entities' => false,
                'manage-assignment' => false,
                'manage-analysis' => false,
                'manage-workflow' => false,
                'manage-status' => false,
                'review-report' => false,
                'approve-report' => false,
                'view-audit-trail' => false,
                'access-administration' => false,
            ]],
            'Pegawai Penyelaras Rekod' => [User::ROLE_PENYELARAS_REKOD, [
                'view-dashboard' => false,
                'view-all-entities' => false,
                'manage-assignment' => false,
                'manage-analysis' => false,
                'manage-workflow' => false,
                'manage-status' => false,
                'review-report' => false,
                'approve-report' => false,
                'view-audit-trail' => false,
                'access-administration' => false,
            ]],
            // NEEDS CONFIRMATION — tiada baris dalam matriks. Buat sementara
            // baca sahaja: papan pemuka dan keterlihatan entiti, tiada lagi.
            'Timbalan Pengarah II' => [User::ROLE_TIMBALAN_PENGARAH_II, [
                'view-dashboard' => true,
                'view-all-entities' => true,
                'manage-assignment' => false,
                'manage-analysis' => false,
                'manage-workflow' => false,
                'manage-status' => false,
                'review-report' => false,
                'approve-report' => false,
                'view-audit-trail' => false,
                'access-administration' => false,
            ]],
        ];
    }

    /**
     * @param  array<string, bool>  $dijangka
     */
    #[DataProvider('matriksPeranan')]
    public function test_kebenaran_peranan_mengikut_matriks(string $role, array $dijangka): void
    {
        $pengguna = $this->pengguna($role);

        foreach ($dijangka as $gate => $sepatutnya) {
            $this->assertSame(
                $sepatutnya,
                Gate::forUser($pengguna)->allows($gate),
                "Gate [{$gate}] bagi peranan [{$role}] tidak seperti dijangka.",
            );
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Papan pemuka
    |--------------------------------------------------------------------------
    */

    /**
     * @return array<string, array{0: string, 1: bool}>
     */
    public static function aksesPapanPemuka(): array
    {
        return [
            'Pentadbir' => [User::ROLE_ADMINISTRATOR, true],
            'Penyelaras' => [User::ROLE_COORDINATOR, true],
            'Ketua Bahagian' => [User::ROLE_KETUA_BAHAGIAN, true],
            'Pegawai Analisis' => [User::ROLE_ANALYST, false],
            'Pegawai Kawalan Dokumen' => [User::ROLE_PEGAWAI_KAWALAN_DOKUMEN, false],
            'Pegawai Penyelaras Rekod' => [User::ROLE_PENYELARAS_REKOD, false],
            'Timbalan Pengarah II' => [User::ROLE_TIMBALAN_PENGARAH_II, true],
        ];
    }

    #[DataProvider('aksesPapanPemuka')]
    public function test_akses_papan_pemuka_mengikut_peranan(string $role, bool $dibenarkan): void
    {
        $response = $this->actingAs($this->pengguna($role))->get(route('dashboard'));

        if ($dibenarkan) {
            $response->assertOk()->assertSee('Taburan Workflow 7 Peringkat');
        } else {
            $response->assertRedirect(route('analisis.index'));
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Sektor & entiti
    |--------------------------------------------------------------------------
    */

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function keterlihatanEntiti(): array
    {
        return [
            'Pentadbir' => [User::ROLE_ADMINISTRATOR, 'semua'],
            'Penyelaras' => [User::ROLE_COORDINATOR, 'semua'],
            'Ketua Bahagian' => [User::ROLE_KETUA_BAHAGIAN, 'semua'],
            'Pegawai Analisis' => [User::ROLE_ANALYST, 'ditugaskan'],
            'Pegawai Kawalan Dokumen' => [User::ROLE_PEGAWAI_KAWALAN_DOKUMEN, 'tiada'],
            'Pegawai Penyelaras Rekod' => [User::ROLE_PENYELARAS_REKOD, 'tiada'],
            'Timbalan Pengarah II' => [User::ROLE_TIMBALAN_PENGARAH_II, 'semua'],
        ];
    }

    #[DataProvider('keterlihatanEntiti')]
    public function test_keterlihatan_sektor_dan_entiti_mengikut_peranan(string $role, string $skop): void
    {
        $pengguna = $this->pengguna($role);
        $access = app(EntityAccessService::class);

        if ($role === User::ROLE_ANALYST) {
            $this->tugaskan($pengguna);
            $pengguna->refresh();
        }

        match ($skop) {
            'semua' => $this->assertNull($access->accessibleCodes($pengguna)),
            'ditugaskan' => $this->assertSame([self::ALPHA], $access->accessibleCodes($pengguna)),
            'tiada' => $this->assertSame([], $access->accessibleCodes($pengguna)),
        };

        // Senarai sektor yang ditawarkan mengikut skop yang sama.
        $sektor = $access->sektorFor($pengguna);

        match ($skop) {
            'semua' => $this->assertSame(count(config('sektor')), count($sektor)),
            'ditugaskan' => $this->assertSame(1, count($sektor)),
            'tiada' => $this->assertSame(0, count($sektor)),
        };
    }

    #[DataProvider('keterlihatanEntiti')]
    public function test_akses_halaman_entiti_mengikut_peranan(string $role, string $skop): void
    {
        $pengguna = $this->pengguna($role);

        if ($role === User::ROLE_ANALYST) {
            $this->tugaskan($pengguna);
        }

        $response = $this->actingAs($pengguna)->get(route('entiti.show', self::ALPHA));

        $skop === 'tiada'
            ? $response->assertForbidden()
            : $response->assertOk();

        // Entiti yang tidak ditugaskan kekal terlarang bagi Pegawai Analisis.
        if ($skop === 'ditugaskan') {
            $this->actingAs($pengguna)
                ->get(route('entiti.show', self::BETA))
                ->assertForbidden();
        }
    }

    public function test_pegawai_analisis_kekal_terhad_kepada_entiti_ditugaskan(): void
    {
        $analyst = $this->pengguna(User::ROLE_ANALYST);
        $this->tugaskan($analyst);

        $this->actingAs($analyst)->get(route('entiti.show', self::ALPHA))->assertOk();
        $this->actingAs($analyst)->get(route('entiti.show', self::BETA))->assertForbidden();
        $this->actingAs($analyst)->get(route('workflow.show', self::BETA))->assertForbidden();
    }

    /*
    |--------------------------------------------------------------------------
    | Penugasan
    |--------------------------------------------------------------------------
    */

    /**
     * @return array<string, array{0: string, 1: bool}>
     */
    public static function aksesPenugasan(): array
    {
        return [
            'Pentadbir' => [User::ROLE_ADMINISTRATOR, true],
            'Penyelaras' => [User::ROLE_COORDINATOR, true],
            'Ketua Bahagian' => [User::ROLE_KETUA_BAHAGIAN, false],
            'Pegawai Analisis' => [User::ROLE_ANALYST, false],
            'Pegawai Kawalan Dokumen' => [User::ROLE_PEGAWAI_KAWALAN_DOKUMEN, false],
            'Pegawai Penyelaras Rekod' => [User::ROLE_PENYELARAS_REKOD, false],
            'Timbalan Pengarah II' => [User::ROLE_TIMBALAN_PENGARAH_II, false],
        ];
    }

    #[DataProvider('aksesPenugasan')]
    public function test_akses_modul_penugasan_mengikut_peranan(string $role, bool $dibenarkan): void
    {
        $response = $this->actingAs($this->pengguna($role))->get(route('penugasan.index'));

        $dibenarkan ? $response->assertOk() : $response->assertForbidden();
    }

    #[DataProvider('aksesPenugasan')]
    public function test_membuat_penugasan_mengikut_peranan(string $role, bool $dibenarkan): void
    {
        $analyst = $this->pengguna(User::ROLE_ANALYST);

        $response = $this->actingAs($this->pengguna($role))
            ->post(route('penugasan.simpan', self::ALPHA), [
                'assigned_to_user_id' => $analyst->id,
            ]);

        if ($dibenarkan) {
            $response->assertSessionHasNoErrors();
            $this->assertDatabaseHas('entiti_assignment', ['agency_code' => self::ALPHA]);
        } else {
            $response->assertForbidden();
            $this->assertDatabaseCount('entiti_assignment', 0);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Laporan & input analisis
    |--------------------------------------------------------------------------
    */

    /**
     * @return array<string, array{0: string, 1: bool}>
     */
    public static function aksesInputAnalisis(): array
    {
        return [
            'Pentadbir' => [User::ROLE_ADMINISTRATOR, true],
            'Pegawai Analisis' => [User::ROLE_ANALYST, true],
            'Penyelaras' => [User::ROLE_COORDINATOR, false],
            'Ketua Bahagian' => [User::ROLE_KETUA_BAHAGIAN, false],
            'Pegawai Kawalan Dokumen' => [User::ROLE_PEGAWAI_KAWALAN_DOKUMEN, false],
            'Pegawai Penyelaras Rekod' => [User::ROLE_PENYELARAS_REKOD, false],
            'Timbalan Pengarah II' => [User::ROLE_TIMBALAN_PENGARAH_II, false],
        ];
    }

    #[DataProvider('aksesInputAnalisis')]
    public function test_input_dapatan_analisis_mengikut_peranan(string $role, bool $dibenarkan): void
    {
        $pengguna = $this->pengguna($role);

        if ($role === User::ROLE_ANALYST) {
            $this->tugaskan($pengguna);
        }

        $response = $this->actingAs($pengguna)->get(route('analisis.borang', [
            'sector_code' => '001',
            'agency_code' => self::ALPHA,
        ]));

        $dibenarkan ? $response->assertOk() : $response->assertForbidden();
    }

    #[DataProvider('aksesInputAnalisis')]
    public function test_simpan_draf_mengikut_peranan(string $role, bool $dibenarkan): void
    {
        $pengguna = $this->pengguna($role);

        if ($role === User::ROLE_ANALYST) {
            $this->tugaskan($pengguna);
        }

        $response = $this->actingAs($pengguna)->post(route('analisis.draf'), [
            'sector_code' => '001',
            'agency_code' => self::ALPHA,
            'kod_rujukan' => 'REF-001',
        ]);

        $dibenarkan
            ? $response->assertSessionHasNoErrors()
            : $response->assertForbidden();
    }

    /**
     * Membaca laporan yang telah dimuktamadkan ialah operasi bacaan dan
     * mengikut kebenaran "Lihat entiti", bukan "Generate report".
     *
     * NEEDS CONFIRMATION: jika muat turun PDF perlu dianggap sebagai
     * "Generate report" (Ketua ✗), pemetaan policy perlu dipisahkan.
     */
    #[DataProvider('keterlihatanEntiti')]
    public function test_membaca_laporan_mengikut_keterlihatan_entiti(string $role, string $skop): void
    {
        $pengguna = $this->pengguna($role);

        if ($role === User::ROLE_ANALYST) {
            $this->tugaskan($pengguna);
        }

        $analisis = AnalisisInventori::factory()->create(
            SektorDirectory::cariEntiti(self::ALPHA) + ['user_id' => $pengguna->id]
        );

        $response = $this->actingAs($pengguna)->get(route('laporan.inventori', $analisis));

        $skop === 'tiada'
            ? $response->assertForbidden()
            : $response->assertOk();
    }

    /*
    |--------------------------------------------------------------------------
    | Semakan & kelulusan (pemetaan kebenaran sahaja — Fasa 10)
    |--------------------------------------------------------------------------
    */

    public function test_kuasa_semakan_dan_kelulusan_mengikut_matriks(): void
    {
        $boleh = fn (string $role, string $gate) => Gate::forUser($this->pengguna($role))->allows($gate);

        // Review: Pentadbir ✓, Penyelaras ✓, Ketua ✓, Analisis ✗.
        $this->assertTrue($boleh(User::ROLE_ADMINISTRATOR, 'review-report'));
        $this->assertTrue($boleh(User::ROLE_COORDINATOR, 'review-report'));
        $this->assertTrue($boleh(User::ROLE_KETUA_BAHAGIAN, 'review-report'));
        $this->assertFalse($boleh(User::ROLE_ANALYST, 'review-report'));

        // Approve: Pentadbir ✓, Ketua ✓ sahaja. Penyelaras "ikut permission"
        // tidak diberikan; tiada kuasa kelulusan direka sendiri.
        $this->assertTrue($boleh(User::ROLE_ADMINISTRATOR, 'approve-report'));
        $this->assertTrue($boleh(User::ROLE_KETUA_BAHAGIAN, 'approve-report'));
        $this->assertFalse($boleh(User::ROLE_COORDINATOR, 'approve-report'));
        $this->assertFalse($boleh(User::ROLE_ANALYST, 'approve-report'));
    }

    public function test_tiada_aliran_kelulusan_dilaksanakan_lagi(): void
    {
        // Fasa 10 belum dilaksanakan — tiada route semakan/kelulusan wujud.
        $laluan = collect(app('router')->getRoutes())
            ->map(fn ($route) => $route->uri())
            ->filter(fn ($uri) => str_contains($uri, 'kelulusan') || str_contains($uri, 'semakan'));

        $this->assertTrue($laluan->isEmpty());
    }

    /*
    |--------------------------------------------------------------------------
    | Fungsi pentadbiran
    |--------------------------------------------------------------------------
    */

    /**
     * @return array<string, array{0: string}>
     */
    public static function perananBukanPentadbir(): array
    {
        return [
            'Penyelaras' => [User::ROLE_COORDINATOR],
            'Pegawai Analisis' => [User::ROLE_ANALYST],
            'Ketua Bahagian' => [User::ROLE_KETUA_BAHAGIAN],
            'Pegawai Kawalan Dokumen' => [User::ROLE_PEGAWAI_KAWALAN_DOKUMEN],
            'Pegawai Penyelaras Rekod' => [User::ROLE_PENYELARAS_REKOD],
        ];
    }

    #[DataProvider('perananBukanPentadbir')]
    public function test_akses_pentadbiran_tidak_diberikan_secara_tidak_sengaja(string $role): void
    {
        $pengguna = $this->pengguna($role);

        $this->actingAs($pengguna)->get(route('administration.users.index'))->assertForbidden();
        $this->actingAs($pengguna)->get(route('administration.users.create'))->assertForbidden();

        $this->actingAs($pengguna)
            ->post(route('administration.users.store'), [
                'name' => 'Pengguna Baharu',
                'username' => 'baharu',
                'email' => 'baharu@example.my',
                'role' => User::ROLE_ADMINISTRATOR,
                'password' => 'KataLaluan#2026x',
                'password_confirmation' => 'KataLaluan#2026x',
            ])
            ->assertForbidden();

        $this->assertDatabaseMissing('users', ['username' => 'baharu']);
    }

    public function test_pentadbir_boleh_mengakses_fungsi_pentadbiran(): void
    {
        $this->actingAs($this->pengguna(User::ROLE_ADMINISTRATOR))
            ->get(route('administration.users.index'))
            ->assertOk();
    }

    /*
    |--------------------------------------------------------------------------
    | Keterlihatan UI
    |--------------------------------------------------------------------------
    */

    public function test_menu_sisi_hanya_memaparkan_modul_yang_dibenarkan(): void
    {
        $ketua = $this->pengguna(User::ROLE_KETUA_BAHAGIAN);

        $this->actingAs($ketua)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Papan Pemuka')
            ->assertSee('Jejak Audit')
            ->assertDontSee('Penugasan Entiti')
            ->assertDontSee('Pengguna');
    }

    public function test_pegawai_tanpa_kebenaran_tidak_melihat_modul_terhad(): void
    {
        $dc = $this->pengguna(User::ROLE_PEGAWAI_KAWALAN_DOKUMEN);

        $this->actingAs($dc)
            ->get(route('analisis.index'))
            ->assertOk()
            ->assertDontSee('Papan Pemuka')
            ->assertDontSee('Jejak Audit')
            ->assertDontSee('Penugasan Entiti')
            ->assertDontSee('Muat Naik MasterTable');
    }
}
