<?php

namespace Tests\Feature;

use App\Models\AnalisisInventori;
use App\Models\LaporanSemakan;
use App\Models\User;
use App\Models\WorkflowStatus;
use App\Services\EntityAssignmentService;
use App\Services\KemajuanAnalisisService;
use App\Services\LaporanSemakanService;
use App\Support\SektorDirectory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Pengesahan menyeluruh matriks kebenaran — setiap peranan terhadap setiap
 * modul dan setiap tindakan.
 *
 * Prinsip yang diuji: menyembunyikan menu BUKAN kebenaran. Setiap semakan di
 * sini memukul route sebenar, bukan sekadar gate, supaya capaian melalui URL
 * langsung dan permintaan JSON turut terbukti ditolak.
 */
class RbacMatriksTest extends TestCase
{
    use RefreshDatabase;

    private const ALPHA = 'A010101';

    private const BETA = 'A010102';

    /** @var array<string, User> */
    private array $pengguna = [];

    protected function setUp(): void
    {
        parent::setUp();

        foreach (User::roles() as $role) {
            $this->pengguna[$role] = User::factory()->create(['role' => $role]);
        }
    }

    private function sebagai(string $role): User
    {
        return $this->pengguna[$role]->fresh();
    }

    /**
     * Entiti ALPHA didaftarkan dan ditugaskan kepada Pegawai Analisis.
     */
    private function sediakanEntiti(): void
    {
        app(KemajuanAnalisisService::class)->lengkapkanPendaftaran(
            SektorDirectory::cariEntiti(self::ALPHA),
            $this->pengguna[User::ROLE_PENYELARAS_REKOD],
        );

        app(EntityAssignmentService::class)->assign(
            SektorDirectory::cariEntiti(self::ALPHA),
            $this->pengguna[User::ROLE_ANALYST],
            $this->pengguna[User::ROLE_COORDINATOR],
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Akses modul — satu baris matriks setiap kaedah
    |--------------------------------------------------------------------------
    */

    /**
     * @return array<string, array{0: string, 1: array<int, string>}>
     */
    public static function aksesModul(): array
    {
        $semua = [
            User::ROLE_ADMINISTRATOR,
            User::ROLE_TIMBALAN_PENGARAH_II,
            User::ROLE_KETUA_BAHAGIAN,
            User::ROLE_PENYELARAS_REKOD,
            User::ROLE_PEGAWAI_KAWALAN_DOKUMEN,
            User::ROLE_COORDINATOR,
            User::ROLE_ANALYST,
        ];

        $tanpa = fn (array $keluar) => array_values(array_diff($semua, $keluar));

        return [
            // Papan Pemuka: semua kecuali PA.
            'Papan Pemuka' => ['dashboard', $tanpa([User::ROLE_ANALYST])],

            // Penetapan Entiti: tiga peranan tindakan sahaja.
            'Penetapan Entiti' => ['penugasan.index', [
                User::ROLE_KETUA_BAHAGIAN,
                User::ROLE_PENYELARAS_REKOD,
                User::ROLE_COORDINATOR,
            ]],

            // Kemajuan Analisis Entiti: semua peranan boleh melihat.
            'Kemajuan Analisis Entiti' => ['workflow.index', $semua],

            // Analisis Inventori Kriptografi: semua peranan boleh melihat.
            'Analisis Inventori Kriptografi' => ['analisis.index', $semua],

            // Status 3 Laporan: semua KECUALI Pentadbir Sistem.
            'Status 3 Laporan' => ['status.index', $tanpa([User::ROLE_ADMINISTRATOR])],

            // Log Audit: semua peranan.
            'Log Audit' => ['audit.index', $semua],

            // Pengguna: Pentadbir Sistem sahaja.
            'Pengguna' => ['administration.users.index', [User::ROLE_ADMINISTRATOR]],

            // Profil Saya: semua peranan.
            'Profil Saya' => ['profil.edit', $semua],
        ];
    }

    /**
     * @param  array<int, string>  $dibenarkan
     */
    #[DataProvider('aksesModul')]
    public function test_akses_modul_mengikut_matriks(string $route, array $dibenarkan): void
    {
        foreach (User::roles() as $role) {
            $respons = $this->actingAs($this->sebagai($role))->get(route($route));

            if (in_array($role, $dibenarkan, true)) {
                $respons->assertOk();
            } else {
                $respons->assertForbidden();
            }
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Tindakan Penetapan Entiti — satu tindakan, satu peranan
    |--------------------------------------------------------------------------
    */

    public function test_tandakan_pendaftaran_hanya_ppr(): void
    {
        foreach (User::roles() as $role) {
            $respons = $this->actingAs($this->sebagai($role))
                ->post(route('penugasan.pendaftaran.kemas-kini'), ['agency_codes' => [self::BETA]]);

            if ($role === User::ROLE_PENYELARAS_REKOD) {
                $respons->assertSessionHasNoErrors();
            } else {
                $respons->assertForbidden();
            }
        }
    }

    public function test_set_semula_hanya_kb(): void
    {
        $this->sediakanEntiti();

        foreach (User::roles() as $role) {
            if ($role === User::ROLE_KETUA_BAHAGIAN) {
                continue;
            }

            $this->actingAs($this->sebagai($role))
                ->post(route('penugasan.pendaftaran.set-semula', self::ALPHA), ['reason' => 'Cuba.'])
                ->assertForbidden();
        }

        // Peringkat 1 kekal Selesai selepas setiap percubaan yang ditolak.
        $this->assertTrue(app(KemajuanAnalisisService::class)->pendaftaranSelesai(self::ALPHA));

        $this->actingAs($this->sebagai(User::ROLE_KETUA_BAHAGIAN))
            ->post(route('penugasan.pendaftaran.set-semula', self::ALPHA), ['reason' => 'Data tidak lengkap.'])
            ->assertSessionHasNoErrors();

        $this->assertFalse(app(KemajuanAnalisisService::class)->pendaftaranSelesai(self::ALPHA));
    }

    public function test_tugaskan_pa_hanya_ppa(): void
    {
        $this->sediakanEntiti();

        $pa = $this->pengguna[User::ROLE_ANALYST];

        foreach (User::roles() as $role) {
            if ($role === User::ROLE_COORDINATOR) {
                continue;
            }

            $this->actingAs($this->sebagai($role))
                ->post(route('penugasan.simpan', self::ALPHA), ['assigned_to_user_id' => $pa->id])
                ->assertForbidden();
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Tindakan Kemajuan Analisis Entiti
    |--------------------------------------------------------------------------
    */

    public function test_kemas_kini_peringkat_hanya_pa(): void
    {
        $this->sediakanEntiti();

        foreach (User::roles() as $role) {
            if ($role === User::ROLE_ANALYST) {
                continue;
            }

            $this->actingAs($this->sebagai($role))
                ->post(route('kemajuan.selesai', [self::ALPHA, WorkflowStatus::STAGE_SEMAKAN_AWAL]))
                ->assertForbidden();
        }

        // Peringkat kekal Belum Mula walaupun enam peranan telah mencuba.
        $this->assertSame(
            'Belum Mula',
            app(KemajuanAnalisisService::class)->peringkat(self::ALPHA)[WorkflowStatus::STAGE_SEMAKAN_AWAL]->status,
        );

        $this->actingAs($this->sebagai(User::ROLE_ANALYST))
            ->post(route('kemajuan.selesai', [self::ALPHA, WorkflowStatus::STAGE_SEMAKAN_AWAL]))
            ->assertSessionHasNoErrors();
    }

    public function test_semak_laporan_hanya_ppa_dan_kb(): void
    {
        $this->bawaLaporanKepadaPPA();

        foreach (User::roles() as $role) {
            if (in_array($role, [User::ROLE_COORDINATOR, User::ROLE_KETUA_BAHAGIAN], true)) {
                continue;
            }

            $this->actingAs($this->sebagai($role))
                ->post(route('kemajuan.semak', self::ALPHA))
                ->assertForbidden();
        }

        $this->assertDatabaseHas('laporan_semakan', [
            'agency_code' => self::ALPHA,
            'status' => LaporanSemakan::MENUNGGU_PPA,
        ]);
    }

    public function test_sahkan_laporan_hanya_kb(): void
    {
        $this->bawaLaporanKepadaPPA();

        $this->actingAs($this->sebagai(User::ROLE_COORDINATOR))
            ->post(route('kemajuan.semak', self::ALPHA));

        foreach (User::roles() as $role) {
            if ($role === User::ROLE_KETUA_BAHAGIAN) {
                continue;
            }

            $this->actingAs($this->sebagai($role))
                ->post(route('kemajuan.sahkan', self::ALPHA))
                ->assertForbidden();
        }

        $this->assertDatabaseHas('laporan_semakan', [
            'agency_code' => self::ALPHA,
            'status' => LaporanSemakan::MENUNGGU_KB,
        ]);

        $this->actingAs($this->sebagai(User::ROLE_KETUA_BAHAGIAN))
            ->post(route('kemajuan.sahkan', self::ALPHA))
            ->assertSessionHasNoErrors();
    }

    public function test_hantar_nacsa_hanya_kb(): void
    {
        $this->bawaLaporanKepadaPPA();

        $this->actingAs($this->sebagai(User::ROLE_COORDINATOR))->post(route('kemajuan.semak', self::ALPHA));
        $this->actingAs($this->sebagai(User::ROLE_KETUA_BAHAGIAN))->post(route('kemajuan.sahkan', self::ALPHA));

        foreach (User::roles() as $role) {
            if ($role === User::ROLE_KETUA_BAHAGIAN) {
                continue;
            }

            $this->actingAs($this->sebagai($role))
                ->post(route('kemajuan.serah', self::ALPHA))
                ->assertForbidden();
        }

        $this->assertNotSame(
            KemajuanAnalisisService::KESELURUHAN_SIAP,
            app(KemajuanAnalisisService::class)->keseluruhan(self::ALPHA),
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Tindakan Analisis Inventori Kriptografi
    |--------------------------------------------------------------------------
    */

    public function test_borang_input_analisis_hanya_pa(): void
    {
        $this->sediakanEntiti();

        $url = route('analisis.borang', ['sector_code' => '001', 'agency_code' => self::ALPHA]);

        foreach (User::roles() as $role) {
            $respons = $this->actingAs($this->sebagai($role))->get($url);

            $role === User::ROLE_ANALYST
                ? $respons->assertOk()
                : $respons->assertForbidden();
        }
    }

    public function test_simpan_draf_dan_dapatan_hanya_pa(): void
    {
        $this->sediakanEntiti();

        $muatan = ['sector_code' => '001', 'agency_code' => self::ALPHA];

        foreach (User::roles() as $role) {
            if ($role === User::ROLE_ANALYST) {
                continue;
            }

            $this->actingAs($this->sebagai($role))
                ->post(route('analisis.draf'), $muatan)
                ->assertForbidden();

            $this->actingAs($this->sebagai($role))
                ->post(route('analisis.simpan'), $muatan + [
                    'status_laporan' => 'Muktamad',
                    'ringkasan_data' => 'lengkap',
                ])
                ->assertForbidden();
        }

        $this->assertDatabaseCount('analisis_inventori', 0);
    }

    public function test_jana_laporan_hanya_pa(): void
    {
        $this->sediakanEntiti();
        $this->bawaAnalisisSelesai();

        foreach (User::roles() as $role) {
            if ($role === User::ROLE_ANALYST) {
                continue;
            }

            $this->actingAs($this->sebagai($role))
                ->post(route('kemajuan.jana-laporan', self::ALPHA))
                ->assertForbidden();
        }

        $this->assertDatabaseCount('laporan_semakan', 0);
    }

    public function test_muat_turun_hanya_selepas_laporan_disahkan(): void
    {
        $this->bawaLaporanKepadaPPA();

        $analisis = AnalisisInventori::where('agency_code', self::ALPHA)->firstOrFail();

        // Belum disahkan — ditolak walaupun bagi peranan yang boleh melihat.
        foreach ([User::ROLE_KETUA_BAHAGIAN, User::ROLE_COORDINATOR, User::ROLE_ANALYST] as $role) {
            $this->actingAs($this->sebagai($role))
                ->get(route('laporan.unduh', $analisis))
                ->assertForbidden();
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Pentadbir Sistem — baca sahaja pada Kemajuan Analisis Entiti
    |--------------------------------------------------------------------------
    */

    public function test_ps_boleh_melihat_kemajuan_setiap_entiti(): void
    {
        $this->sediakanEntiti();

        $ps = $this->sebagai(User::ROLE_ADMINISTRATOR);

        // Senarai dan halaman setiap entiti terbuka — termasuk entiti yang
        // ditugaskan kepada pegawai analisis lain.
        $this->actingAs($ps)->get(route('workflow.index'))->assertOk();
        $this->actingAs($ps)->get(route('workflow.show', self::ALPHA))->assertOk();
        $this->actingAs($ps)->get(route('workflow.show', self::BETA))->assertOk();
    }

    public function test_ps_tidak_boleh_melakukan_sebarang_tindakan_kemajuan(): void
    {
        $this->bawaLaporanKepadaPPA();

        $ps = $this->sebagai(User::ROLE_ADMINISTRATOR);

        // Setiap tindakan yang menggerakkan Kemajuan Analisis Entiti ditolak.
        $this->actingAs($ps)
            ->post(route('kemajuan.selesai', [self::ALPHA, WorkflowStatus::STAGE_JANA_LAPORAN]))
            ->assertForbidden();

        $this->actingAs($ps)->post(route('kemajuan.jana-laporan', self::ALPHA))->assertForbidden();
        $this->actingAs($ps)->post(route('kemajuan.hantar', self::ALPHA))->assertForbidden();
        $this->actingAs($ps)->post(route('kemajuan.semak', self::ALPHA))->assertForbidden();
        $this->actingAs($ps)->post(route('kemajuan.kembalikan', self::ALPHA), ['catatan' => 'Cuba.'])->assertForbidden();
        $this->actingAs($ps)->post(route('kemajuan.sahkan', self::ALPHA))->assertForbidden();
        $this->actingAs($ps)->post(route('kemajuan.serah', self::ALPHA))->assertForbidden();

        // Kedudukan entiti tidak berubah walau satu pun.
        $this->assertDatabaseHas('laporan_semakan', [
            'agency_code' => self::ALPHA,
            'status' => LaporanSemakan::MENUNGGU_PPA,
        ]);

        $this->assertNotSame(
            KemajuanAnalisisService::KESELURUHAN_SIAP,
            app(KemajuanAnalisisService::class)->keseluruhan(self::ALPHA),
        );
    }

    public function test_tiada_route_kemas_kini_peringkat_secara_manual(): void
    {
        // Kawalan penyeliaan manual telah dibuang: tiada laluan yang
        // membenarkan mana-mana peranan melompat peringkat di luar aliran.
        foreach (['workflow.mula', 'workflow.peringkat', 'workflow.status'] as $nama) {
            $this->assertNull(
                app('router')->getRoutes()->getByName($nama),
                "Route {$nama} sepatutnya telah dibuang.",
            );
        }
    }

    public function test_halaman_kemajuan_tidak_memaparkan_tindakan_kepada_ps(): void
    {
        $this->sediakanEntiti();

        $this->actingAs($this->sebagai(User::ROLE_ADMINISTRATOR))
            ->get(route('workflow.show', self::ALPHA))
            ->assertOk()
            ->assertSee('Peringkat Kemajuan')
            ->assertDontSee('Kawalan Penyeliaan')
            ->assertDontSee('Majukan Peringkat')
            ->assertDontSee('Kembalikan Peringkat')
            ->assertDontSee(route('kemajuan.jana-laporan', self::ALPHA));
    }

    /*
    |--------------------------------------------------------------------------
    | Kebenaran data — Pegawai Analisis dan entiti pegawai lain
    |--------------------------------------------------------------------------
    */

    public function test_pa_tidak_boleh_menyentuh_entiti_pa_lain(): void
    {
        $this->sediakanEntiti();

        // BETA didaftarkan dan ditugaskan kepada pegawai analisis KEDUA.
        $paLain = User::factory()->create(['role' => User::ROLE_ANALYST]);

        app(KemajuanAnalisisService::class)->lengkapkanPendaftaran(
            SektorDirectory::cariEntiti(self::BETA),
            $this->pengguna[User::ROLE_PENYELARAS_REKOD],
        );

        app(EntityAssignmentService::class)->assign(
            SektorDirectory::cariEntiti(self::BETA),
            $paLain,
            $this->pengguna[User::ROLE_COORDINATOR],
        );

        $pa = $this->sebagai(User::ROLE_ANALYST);

        // Melihat entiti pegawai lain.
        $this->actingAs($pa)->get(route('workflow.show', self::BETA))->assertForbidden();
        $this->actingAs($pa)->get(route('entiti.show', self::BETA))->assertForbidden();

        // Membuka borang analisis entiti pegawai lain.
        $this->actingAs($pa)
            ->get(route('analisis.borang', ['sector_code' => '001', 'agency_code' => self::BETA]))
            ->assertForbidden();

        // Menulis dapatan entiti pegawai lain — termasuk dengan menukar
        // parameter permintaan secara langsung.
        $this->actingAs($pa)
            ->post(route('analisis.simpan'), [
                'sector_code' => '001',
                'agency_code' => self::BETA,
                'status_laporan' => 'Muktamad',
                'ringkasan_data' => 'lengkap',
            ])
            ->assertForbidden();

        // Memajukan peringkat entiti pegawai lain.
        $this->actingAs($pa)
            ->post(route('kemajuan.selesai', [self::BETA, WorkflowStatus::STAGE_SEMAKAN_AWAL]))
            ->assertForbidden();

        $this->assertDatabaseMissing('analisis_inventori', ['agency_code' => self::BETA]);

        $this->assertSame(
            'Belum Mula',
            app(KemajuanAnalisisService::class)->peringkat(self::BETA)[WorkflowStatus::STAGE_SEMAKAN_AWAL]->status,
        );
    }

    public function test_permintaan_json_tertakluk_kepada_kebenaran_yang_sama(): void
    {
        $this->sediakanEntiti();

        $pa = $this->sebagai(User::ROLE_ANALYST);

        // Memanggil API secara manual tidak memintas apa-apa.
        $this->actingAs($pa)->getJson(route('dashboard'))->assertForbidden();
        $this->actingAs($pa)->getJson(route('penugasan.index'))->assertForbidden();
        $this->actingAs($pa)->getJson(route('administration.users.index'))->assertForbidden();
        $this->actingAs($pa)->postJson(route('kemajuan.sahkan', self::ALPHA))->assertForbidden();
        $this->actingAs($pa)->postJson(route('kemajuan.serah', self::ALPHA))->assertForbidden();

        $ps = $this->sebagai(User::ROLE_ADMINISTRATOR);
        $this->actingAs($ps)->getJson(route('status.index'))->assertForbidden();
    }

    /*
    |--------------------------------------------------------------------------
    | Profil sendiri sahaja
    |--------------------------------------------------------------------------
    */

    public function test_profil_hanya_menyentuh_akaun_sendiri(): void
    {
        $pa = $this->sebagai(User::ROLE_ANALYST);
        $lain = $this->pengguna[User::ROLE_COORDINATOR];
        $namaAsal = $lain->name;

        // Tiada parameter pengguna pada route profil; cubaan menyeludupkan id
        // orang lain melalui borang tidak boleh mengubah akaun mereka.
        $this->actingAs($pa)
            ->put(route('profil.update'), [
                'id' => $lain->id,
                'user_id' => $lain->id,
                'name' => 'Nama Diubah',
                'username' => $pa->username,
                'email' => $pa->email,
            ])
            ->assertRedirect(route('profil.edit'));

        $this->assertSame($namaAsal, $lain->fresh()->name);
        $this->assertSame('Nama Diubah', $pa->fresh()->name);
    }

    public function test_peranan_tidak_boleh_dinaikkan_melalui_profil(): void
    {
        $pa = $this->sebagai(User::ROLE_ANALYST);

        $this->actingAs($pa)->put(route('profil.update'), [
            'name' => $pa->name,
            'username' => $pa->username,
            'email' => $pa->email,
            'roles' => [User::ROLE_ADMINISTRATOR],
            'role' => User::ROLE_ADMINISTRATOR,
        ]);

        $this->assertSame([User::ROLE_ANALYST], $pa->fresh()->assignedRoles());
    }

    /*
    |--------------------------------------------------------------------------
    | Jejak audit merekod pelaku dan perananya
    |--------------------------------------------------------------------------
    */

    public function test_tindakan_sensitif_merekod_peranan_pelaku(): void
    {
        $this->sediakanEntiti();

        $log = \App\Models\ActivityLog::where('agency_code', self::ALPHA)
            ->where('action', KemajuanAnalisisService::ACTION_REGISTRATION_COMPLETED)
            ->firstOrFail();

        $this->assertSame(
            $this->pengguna[User::ROLE_PENYELARAS_REKOD]->id,
            $log->changed_by_user_id,
        );

        $this->assertSame([User::ROLE_PENYELARAS_REKOD], $log->metadata['peranan']);
        $this->assertSame('Pegawai Penyelaras Rekod', $log->metadata['peranan_label']);
        $this->assertNotNull($log->changed_at);
    }

    /*
    |--------------------------------------------------------------------------
    | Pembantu
    |--------------------------------------------------------------------------
    */

    private function bawaAnalisisSelesai(): void
    {
        $pa = $this->sebagai(User::ROLE_ANALYST);

        $this->actingAs($pa);
        $this->post(route('kemajuan.selesai', [self::ALPHA, WorkflowStatus::STAGE_SEMAKAN_AWAL]));
        $this->post(route('kemajuan.selesai', [self::ALPHA, WorkflowStatus::STAGE_PENYEDIAAN]));
        $this->post(route('analisis.simpan'), [
            'sector_code' => '001',
            'agency_code' => self::ALPHA,
            'status_laporan' => 'Muktamad',
            'ringkasan_data' => 'lengkap',
            'selesai' => '1',
        ]);
        $this->post(route('kemajuan.selesai', [self::ALPHA, WorkflowStatus::STAGE_ANALISIS]));
    }

    private function bawaLaporanKepadaPPA(): void
    {
        $this->sediakanEntiti();
        $this->bawaAnalisisSelesai();

        $this->actingAs($this->sebagai(User::ROLE_ANALYST));
        $this->post(route('kemajuan.jana-laporan', self::ALPHA));
        $this->post(route('kemajuan.hantar', self::ALPHA));

        $this->assertNotNull(app(LaporanSemakanService::class)->untuk(self::ALPHA));
    }
}
