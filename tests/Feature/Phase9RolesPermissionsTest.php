<?php

namespace Tests\Feature;

use App\Models\AnalisisInventori;
use App\Models\User;
use App\Services\EntityAccessService;
use App\Services\EntityAssignmentService;
use App\Services\KemajuanAnalisisService;
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
        // Setiap peranan disenaraikan terhadap SETIAP gate, supaya kebenaran
        // yang tersilap diberikan kepada peranan lain turut gagal — bukan
        // hanya kebenaran yang hilang daripada peranan yang sepatutnya.
        return [
            'Pentadbir Sistem' => [User::ROLE_ADMINISTRATOR, [
                'view-dashboard' => true,
                'view-all-entities' => true,
                'register-entity-data' => false,
                'reset-entity-registration' => false,
                'manage-assignment' => false,
                'manage-stage-penerimaan' => false,
                'manage-stage-pendaftaran' => false,
                'record-stage-reference' => false,
                'advance-analysis-stage' => false,
                'manage-analysis' => false,
                'review-report' => false,
                'approve-report' => false,
                'submit-to-nacsa' => false,
                'access-status-reports' => false,   // sekatan khas PS
                'view-audit-trail' => true,
                'access-administration' => true,
            ]],
            'Timbalan Pengarah II' => [User::ROLE_TIMBALAN_PENGARAH_II, [
                'view-dashboard' => true,
                'view-all-entities' => true,
                'register-entity-data' => false,
                'reset-entity-registration' => false,
                'manage-assignment' => false,
                'manage-stage-penerimaan' => false,
                'manage-stage-pendaftaran' => false,
                'record-stage-reference' => false,
                'advance-analysis-stage' => false,
                'manage-analysis' => false,
                'review-report' => false,
                'approve-report' => false,
                'submit-to-nacsa' => false,
                'access-status-reports' => true,
                'view-audit-trail' => true,
                'access-administration' => false,
            ]],
            'Ketua Bahagian' => [User::ROLE_KETUA_BAHAGIAN, [
                'view-dashboard' => true,
                'view-all-entities' => true,
                // Peringkat 1.1 Penerimaan Data — KB atau PPA.
                'register-entity-data' => true,
                'reset-entity-registration' => true,
                'manage-assignment' => false,
                'manage-stage-penerimaan' => true,
                'manage-stage-pendaftaran' => false,
                'record-stage-reference' => false,
                'advance-analysis-stage' => false,
                'manage-analysis' => false,
                'review-report' => true,
                'approve-report' => true,
                'submit-to-nacsa' => true,
                'access-status-reports' => true,
                'view-audit-trail' => true,
                'access-administration' => false,
            ]],
            'Pegawai Penyelaras Rekod' => [User::ROLE_PENYELARAS_REKOD, [
                'view-dashboard' => true,
                // PPR tidak melaksanakan sebarang peringkat. Keseluruhan
                // tanggungjawabnya — dan satu-satunya kuasa menulisnya —
                // ialah memasukkan SETIAP No. Rujukan. Keterlihatannya pun
                // terhad kepada entiti yang telah memulakan Penerimaan Data.
                'view-all-entities' => false,
                'register-entity-data' => false,
                'reset-entity-registration' => false,
                'manage-assignment' => false,
                'manage-stage-penerimaan' => false,
                'manage-stage-pendaftaran' => false,
                'record-stage-reference' => true,
                'advance-analysis-stage' => false,
                'manage-analysis' => false,
                'review-report' => false,
                'approve-report' => false,
                'submit-to-nacsa' => false,
                'access-status-reports' => true,
                'view-audit-trail' => true,
                'access-administration' => false,
            ]],
            'Pegawai Kawalan Dokumen' => [User::ROLE_PEGAWAI_KAWALAN_DOKUMEN, [
                'view-dashboard' => true,
                'view-all-entities' => true,
                'register-entity-data' => false,
                'reset-entity-registration' => false,
                'manage-assignment' => false,
                'manage-stage-penerimaan' => false,
                'manage-stage-pendaftaran' => false,
                'record-stage-reference' => false,
                'advance-analysis-stage' => false,
                'manage-analysis' => false,
                'review-report' => false,
                'approve-report' => false,
                'submit-to-nacsa' => false,
                'access-status-reports' => true,
                'view-audit-trail' => true,
                'access-administration' => false,
            ]],
            'Pegawai Penyelaras Analisis' => [User::ROLE_COORDINATOR, [
                'view-dashboard' => true,
                'view-all-entities' => true,
                // Peringkat 1.1 Penerimaan Data — KB atau PPA.
                'register-entity-data' => true,
                'reset-entity-registration' => false,
                'manage-assignment' => true,
                'manage-stage-penerimaan' => true,
                'manage-stage-pendaftaran' => true,
                'record-stage-reference' => false,
                'advance-analysis-stage' => false,
                'manage-analysis' => false,
                'review-report' => true,
                'approve-report' => false,
                'submit-to-nacsa' => false,
                'access-status-reports' => true,
                'view-audit-trail' => true,
                'access-administration' => false,
            ]],
            'Pegawai Analisis' => [User::ROLE_ANALYST, [
                'view-dashboard' => false,
                'view-all-entities' => false,   // entiti ditugaskan sahaja
                'register-entity-data' => false,
                'reset-entity-registration' => false,
                'manage-assignment' => false,
                'manage-stage-penerimaan' => false,
                'manage-stage-pendaftaran' => false,
                'record-stage-reference' => false,
                'advance-analysis-stage' => true,
                'manage-analysis' => true,
                'review-report' => false,
                'approve-report' => false,
                'submit-to-nacsa' => false,
                'access-status-reports' => true,
                'view-audit-trail' => true,
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
            'Pegawai Kawalan Dokumen' => [User::ROLE_PEGAWAI_KAWALAN_DOKUMEN, true],
            'Pegawai Penyelaras Rekod' => [User::ROLE_PENYELARAS_REKOD, true],
            'Timbalan Pengarah II' => [User::ROLE_TIMBALAN_PENGARAH_II, true],
            'Pegawai Analisis' => [User::ROLE_ANALYST, false],
        ];
    }

    #[DataProvider('aksesPapanPemuka')]
    public function test_akses_papan_pemuka_mengikut_peranan(string $role, bool $dibenarkan): void
    {
        $response = $this->actingAs($this->pengguna($role))->get(route('dashboard'));

        if ($dibenarkan) {
            $response->assertOk()->assertSee('Kemajuan Keseluruhan');
        } else {
            // Ditolak, bukan dialihkan: menyembunyikan pautan bukan kebenaran.
            $response->assertForbidden();
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
            // Peranan baca-sahaja melihat semua entiti tetapi tidak boleh
            // mengubah apa-apa padanya (lihat matriksPeranan()).
            'Pegawai Kawalan Dokumen' => [User::ROLE_PEGAWAI_KAWALAN_DOKUMEN, 'semua'],
            // PPR melihat entiti yang telah MEMULAKAN Penerimaan Data sahaja:
            // kerjanya ialah nombor rujukan borang yang sudah wujud.
            'Pegawai Penyelaras Rekod' => [User::ROLE_PENYELARAS_REKOD, 'bermula'],
            'Timbalan Pengarah II' => [User::ROLE_TIMBALAN_PENGARAH_II, 'semua'],
        ];
    }

    /**
     * Mulakan peringkat 1.1 bagi satu entiti — itulah yang menjadikannya
     * kelihatan kepada Pegawai Penyelaras Rekod.
     */
    private function mulakanPenerimaan(string $agencyCode = self::ALPHA): void
    {
        app(KemajuanAnalisisService::class)->lengkapkanPenerimaan(
            SektorDirectory::cariEntiti($agencyCode),
            null,
            ['tarikh_terima' => '2026-08-14', 'status_borang' => 'Selesai'],
        );
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

        if ($skop === 'bermula') {
            $this->mulakanPenerimaan();
            $pengguna->refresh();
        }

        match ($skop) {
            'semua' => $this->assertNull($access->accessibleCodes($pengguna)),
            'ditugaskan' => $this->assertSame([self::ALPHA], $access->accessibleCodes($pengguna)),
            'bermula' => $this->assertSame([self::ALPHA], $access->accessibleCodes($pengguna)),
            'tiada' => $this->assertSame([], $access->accessibleCodes($pengguna)),
        };

        // Senarai sektor yang ditawarkan mengikut skop yang sama.
        $sektor = $access->sektorFor($pengguna);

        match ($skop) {
            'semua' => $this->assertSame(count(config('sektor')), count($sektor)),
            'ditugaskan', 'bermula' => $this->assertSame(1, count($sektor)),
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

        if ($skop === 'bermula') {
            $this->mulakanPenerimaan();
        }

        $response = $this->actingAs($pengguna->fresh())->get(route('entiti.show', self::ALPHA));

        $skop === 'tiada'
            ? $response->assertForbidden()
            : $response->assertOk();

        // Entiti yang BELUM memulakan Penerimaan Data kekal terlarang bagi PPR.
        if ($skop === 'bermula') {
            $this->actingAs($pengguna->fresh())
                ->get(route('entiti.show', self::BETA))
                ->assertForbidden();
        }

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
            'Penyelaras' => [User::ROLE_COORDINATOR, true],
            'Pentadbir' => [User::ROLE_ADMINISTRATOR, false],
            'Ketua Bahagian' => [User::ROLE_KETUA_BAHAGIAN, false],
            'Pegawai Analisis' => [User::ROLE_ANALYST, false],
            'Pegawai Kawalan Dokumen' => [User::ROLE_PEGAWAI_KAWALAN_DOKUMEN, false],
            'Pegawai Penyelaras Rekod' => [User::ROLE_PENYELARAS_REKOD, false],
            'Timbalan Pengarah II' => [User::ROLE_TIMBALAN_PENGARAH_II, false],
        ];
    }

    /**
     * Peranan yang boleh MEMBUKA skrin Penetapan Entiti.
     *
     * Skrin ini memegang tiga tindakan: KB/PPA menanda peringkat 1.1,
     * KB menetapkan semula, PPA menugaskan. Peranan yang tidak memiliki
     * satu pun daripadanya ditolak — termasuk Pentadbir Sistem dan, sejak
     * restruktur, PPR: tanggungjawabnya kini ialah No. Rujukan Borang, yang
     * dimasukkan pada halaman Kemajuan Analisis Entiti.
     *
     * @return array<string, array{0: string, 1: bool}>
     */
    public static function aksesPenetapanEntiti(): array
    {
        return [
            'Penyelaras' => [User::ROLE_COORDINATOR, true],
            'Ketua Bahagian' => [User::ROLE_KETUA_BAHAGIAN, true],
            'Pegawai Penyelaras Rekod' => [User::ROLE_PENYELARAS_REKOD, false],
            'Pentadbir' => [User::ROLE_ADMINISTRATOR, false],
            'Pegawai Analisis' => [User::ROLE_ANALYST, false],
            'Pegawai Kawalan Dokumen' => [User::ROLE_PEGAWAI_KAWALAN_DOKUMEN, false],
            'Timbalan Pengarah II' => [User::ROLE_TIMBALAN_PENGARAH_II, false],
        ];
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
            'Pegawai Analisis' => [User::ROLE_ANALYST, true],
            'Pentadbir' => [User::ROLE_ADMINISTRATOR, false],
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

        if ($skop === 'bermula') {
            $this->mulakanPenerimaan();
        }

        $analisis = AnalisisInventori::factory()->create(
            SektorDirectory::cariEntiti(self::ALPHA) + ['user_id' => $pengguna->id]
        );

        $response = $this->actingAs($pengguna->fresh())->get(route('laporan.inventori', $analisis));

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

        // Semak: PPA dan Ketua Bahagian sahaja.
        $this->assertTrue($boleh(User::ROLE_COORDINATOR, 'review-report'));
        $this->assertTrue($boleh(User::ROLE_KETUA_BAHAGIAN, 'review-report'));
        $this->assertFalse($boleh(User::ROLE_ADMINISTRATOR, 'review-report'));
        $this->assertFalse($boleh(User::ROLE_ANALYST, 'review-report'));

        // Sahkan: Ketua Bahagian sahaja.
        $this->assertTrue($boleh(User::ROLE_KETUA_BAHAGIAN, 'approve-report'));
        $this->assertFalse($boleh(User::ROLE_ADMINISTRATOR, 'approve-report'));
        $this->assertFalse($boleh(User::ROLE_COORDINATOR, 'approve-report'));
        $this->assertFalse($boleh(User::ROLE_ANALYST, 'approve-report'));

        // Hantar kepada NACSA: Ketua Bahagian sahaja.
        $this->assertTrue($boleh(User::ROLE_KETUA_BAHAGIAN, 'submit-to-nacsa'));
        $this->assertFalse($boleh(User::ROLE_ADMINISTRATOR, 'submit-to-nacsa'));
        $this->assertFalse($boleh(User::ROLE_COORDINATOR, 'submit-to-nacsa'));
        $this->assertFalse($boleh(User::ROLE_TIMBALAN_PENGARAH_II, 'submit-to-nacsa'));
        $this->assertFalse($boleh(User::ROLE_ANALYST, 'submit-to-nacsa'));
    }

    /**
     * Peringkat 4 dan 5 (Penjanaan Laporan; Semakan, Kelulusan & Penyerahan
     * Laporan) ialah fasa akan datang: prosesnya belum ditentukan, jadi TIADA
     * route mutasinya wujud.
     *
     * Gate-nya sengaja DIKEKALKAN supaya tanggungjawab yang telah dipersetujui
     * tidak hilang — tetapi tiada route boleh menggunakannya lagi, dan ujian
     * ini yang menghalangnya kembali secara senyap.
     */
    public function test_tiada_route_bagi_peringkat_fasa_akan_datang(): void
    {
        foreach (['kemajuan.hantar', 'kemajuan.semak', 'kemajuan.kembalikan', 'kemajuan.sahkan', 'kemajuan.serah'] as $nama) {
            $this->assertNull(
                app('router')->getRoutes()->getByName($nama),
                "Route {$nama} sepatutnya tiada dalam fasa ini.",
            );
        }

        $this->assertFalse(
            Gate::forUser($this->pengguna(User::ROLE_ANALYST))->allows('approve-report'),
        );
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

    public function test_menu_sisi_ketua_bahagian_mengikut_kebenaran(): void
    {
        // Menu sisi tidak lagi mengandungi Penetapan Entiti: modul itu telah
        // dibuang. Yang kekal ialah modul pemantauan dan laporan.
        $this->actingAs($this->pengguna(User::ROLE_KETUA_BAHAGIAN))
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Papan Pemuka')
            ->assertSee('Log Audit')
            ->assertSee(route('status.index'))
            ->assertDontSee('Pengguna');
    }

    public function test_menu_sisi_pegawai_kawalan_dokumen_baca_sahaja(): void
    {
        // PKD melihat modul baca-sahaja, tetapi tiada satu pun skrin
        // tindakan: Pengguna tidak dipaparkan.
        $this->actingAs($this->pengguna(User::ROLE_PEGAWAI_KAWALAN_DOKUMEN))
            ->get(route('analisis.index'))
            ->assertOk()
            ->assertSee('Papan Pemuka')
            ->assertSee('Log Audit')
            ->assertSee(route('status.index'))
            ->assertDontSee('Pengguna');
    }

    public function test_menu_sisi_pentadbir_sistem_tanpa_status_tiga_laporan(): void
    {
        // Sekatan khas: PS mentadbir sistem tetapi tidak melihat modul
        // operasi Status 3 Laporan.
        $this->actingAs($this->pengguna(User::ROLE_ADMINISTRATOR))
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Papan Pemuka')
            ->assertSee('Pengguna')
            ->assertDontSee(route('status.index'));
    }

    public function test_menu_sisi_pegawai_analisis_tanpa_papan_pemuka(): void
    {
        $pa = $this->pengguna(User::ROLE_ANALYST);
        $this->tugaskan($pa);

        $this->actingAs($pa->fresh())
            ->get(route('analisis.index'))
            ->assertOk()
            ->assertSee('Kemajuan Analisis Entiti')
            ->assertSee(route('status.index'))
            ->assertSee('Log Audit')
            ->assertDontSee('Papan Pemuka')
            ->assertDontSee('Pengguna');
    }
}
