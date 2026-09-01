<?php

namespace Tests\Feature;

use App\Models\AnalisisInventori;
use App\Models\User;
use App\Services\EntityAssignmentService;
use App\Services\KemajuanAnalisisService;
use App\Support\AliranKerja;
use App\Support\SektorDirectory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\MelaluiAliranKerja;
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
    use MelaluiAliranKerja, RefreshDatabase;

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
     * Entiti ALPHA memasuki aliran kerja dan ditugaskan kepada Pegawai Analisis.
     *
     * Peringkat 1.1 dan 1.2 (milik KB/PPA) ditandakan Selesai supaya peringkat
     * 1.3 — peringkat PA yang pertama — benar-benar terbuka untuk diuji.
     */
    private function sediakanEntiti(): void
    {
        $this->lengkapkanHingga(
            self::ALPHA,
            AliranKerja::SEMAKAN_AWAL_DATA,
            $this->pengguna[User::ROLE_COORDINATOR],
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

            // Penetapan Entiti: peringkat 1.1 (KB/PPA) dan penugasan (PPA).
            // PPR tiada tindakan di sini sejak restruktur — tanggungjawabnya
            // ialah No. Rujukan Borang pada halaman Kemajuan Analisis Entiti.
            'Penetapan Entiti' => ['penugasan.index', [
                User::ROLE_KETUA_BAHAGIAN,
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

    public function test_tandakan_penerimaan_data_hanya_kb_dan_ppa(): void
    {
        $pemilik = [User::ROLE_KETUA_BAHAGIAN, User::ROLE_COORDINATOR];

        foreach (User::roles() as $role) {
            if (in_array($role, $pemilik, true)) {
                continue;
            }

            $this->actingAs($this->sebagai($role))
                ->post(route('penugasan.pendaftaran.kemas-kini'), ['agency_codes' => [self::BETA]])
                ->assertForbidden();
        }

        // Enam peranan telah mencuba; peringkat 1.1 kekal belum ditandakan.
        $this->assertFalse(app(KemajuanAnalisisService::class)->penerimaanSelesai(self::BETA));

        $this->actingAs($this->sebagai(User::ROLE_COORDINATOR))
            ->post(route('penugasan.pendaftaran.kemas-kini'), ['agency_codes' => [self::BETA]])
            ->assertSessionHasNoErrors();

        $this->assertTrue(app(KemajuanAnalisisService::class)->penerimaanSelesai(self::BETA));
    }

    /**
     * Peringkat 1.2 Pendaftaran Data ialah milik PPA SAHAJA — bukan KB, yang
     * berkongsi peringkat 1.1 dengannya.
     */
    public function test_peringkat_pendaftaran_data_hanya_ppa(): void
    {
        $this->masukkanKeAliran(self::ALPHA, $this->pengguna[User::ROLE_COORDINATOR]);

        foreach (User::roles() as $role) {
            if ($role === User::ROLE_COORDINATOR) {
                continue;
            }

            $this->actingAs($this->sebagai($role))
                ->post(route('kemajuan.selesai', [self::ALPHA, AliranKerja::PENDAFTARAN_DATA]))
                ->assertForbidden();
        }

        $this->actingAs($this->sebagai(User::ROLE_COORDINATOR))
            ->post(route('kemajuan.selesai', [self::ALPHA, AliranKerja::PENDAFTARAN_DATA]))
            ->assertSessionHasNoErrors();
    }

    /**
     * No. Rujukan Borang ialah milik PPR SAHAJA, walaupun peringkatnya bukan
     * miliknya. Inilah pemisahan yang paling mudah hilang.
     */
    public function test_no_rujukan_borang_hanya_ppr(): void
    {
        $this->sediakanEntiti();

        foreach (User::roles() as $role) {
            if ($role === User::ROLE_PENYELARAS_REKOD) {
                continue;
            }

            $this->actingAs($this->sebagai($role))
                ->post(route('kemajuan.rujukan', [self::ALPHA, AliranKerja::PENERIMAAN_DATA]), [
                    'no_rujukan' => 'CUBAAN/2026/001',
                ])
                ->assertForbidden();
        }

        $this->actingAs($this->sebagai(User::ROLE_PENYELARAS_REKOD))
            ->post(route('kemajuan.rujukan', [self::ALPHA, AliranKerja::PENERIMAAN_DATA]), [
                'no_rujukan' => 'BPD/2026/001',
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame(
            'BPD/2026/001',
            app(KemajuanAnalisisService::class)
                ->peringkat(self::ALPHA)[AliranKerja::PENERIMAAN_DATA]->no_rujukan,
        );
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
        $this->assertTrue(app(KemajuanAnalisisService::class)->penerimaanSelesai(self::ALPHA));

        $this->actingAs($this->sebagai(User::ROLE_KETUA_BAHAGIAN))
            ->post(route('penugasan.pendaftaran.set-semula', self::ALPHA), ['reason' => 'Data tidak lengkap.'])
            ->assertSessionHasNoErrors();

        $this->assertFalse(app(KemajuanAnalisisService::class)->penerimaanSelesai(self::ALPHA));
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
                ->post(route('kemajuan.selesai', [self::ALPHA, AliranKerja::SEMAKAN_AWAL_DATA]))
                ->assertForbidden();
        }

        // Peringkat kekal Belum Mula walaupun enam peranan telah mencuba.
        $this->assertSame(
            'Belum Mula',
            app(KemajuanAnalisisService::class)->peringkat(self::ALPHA)[AliranKerja::SEMAKAN_AWAL_DATA]->status,
        );

        $this->actingAs($this->sebagai(User::ROLE_ANALYST))
            ->post(route('kemajuan.selesai', [self::ALPHA, AliranKerja::SEMAKAN_AWAL_DATA]))
            ->assertSessionHasNoErrors();
    }

    /**
     * Kitaran semakan, kelulusan dan penyerahan laporan milik peringkat 4 dan
     * 5, yang prosesnya BELUM DITENTUKAN. Tiada route mutasinya wujud, jadi
     * tiada peranan — termasuk yang memegang gate-nya — boleh mencapainya.
     */
    public function test_tiada_route_kitaran_laporan_dalam_fasa_ini(): void
    {
        foreach (['kemajuan.hantar', 'kemajuan.semak', 'kemajuan.kembalikan', 'kemajuan.sahkan', 'kemajuan.serah'] as $nama) {
            $this->assertNull(
                app('router')->getRoutes()->getByName($nama),
                "Route {$nama} sepatutnya tiada dalam fasa ini.",
            );
        }
    }

    /**
     * Peringkat fasa akan datang tidak menerima tindakan daripada MANA-MANA
     * peranan — bukan sekadar disembunyikan daripada antara muka.
     */
    public function test_peringkat_fasa_akan_datang_ditolak_bagi_setiap_peranan(): void
    {
        $this->sediakanEntiti();

        foreach (AliranKerja::akanDatang() as $kunci) {
            foreach (User::roles() as $role) {
                $this->actingAs($this->sebagai($role))
                    ->post(route('kemajuan.selesai', [self::ALPHA, $kunci]))
                    ->assertNotFound();
            }
        }
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
                    'status_laporan' => 'Selesai',
                ])
                ->assertForbidden();
        }

        $this->assertDatabaseCount('analisis_inventori', 0);
    }

    /**
     * Muat turun laporan bergantung pada peringkat 3.1 Selesai — bukan pada
     * kelulusan Ketua Bahagian, yang milik peringkat 5 dan belum dibina.
     */
    public function test_muat_turun_ditolak_sebelum_peringkat_analisis_selesai(): void
    {
        $this->sediakanEntiti();
        $this->bawaAnalisisSelesai();

        $analisis = AnalisisInventori::where('agency_code', self::ALPHA)->firstOrFail();

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
        $this->sediakanEntiti();

        $ps = $this->sebagai(User::ROLE_ADMINISTRATOR);

        // Setiap tindakan yang menggerakkan Kemajuan Analisis Entiti ditolak,
        // pada setiap peringkat fasa semasa.
        foreach (AliranKerja::semasa() as $kunci) {
            $this->actingAs($ps)
                ->post(route('kemajuan.selesai', [self::ALPHA, $kunci]))
                ->assertForbidden();

            $this->actingAs($ps)
                ->post(route('kemajuan.simpan', [self::ALPHA, $kunci]))
                ->assertForbidden();
        }

        // No. Rujukan Borang turut tertutup kepadanya.
        $this->actingAs($ps)
            ->post(route('kemajuan.rujukan', [self::ALPHA, AliranKerja::PENERIMAAN_DATA]), [
                'no_rujukan' => 'CUBAAN/2026/001',
            ])
            ->assertForbidden();

        // Kedudukan entiti tidak berubah walau satu pun.
        $this->assertNotSame(
            KemajuanAnalisisService::KESELURUHAN_SIAP,
            app(KemajuanAnalisisService::class)->keseluruhan(self::ALPHA),
        );
    }

    public function test_tiada_route_kemas_kini_peringkat_secara_manual(): void
    {
        // Kawalan penyeliaan manual telah dibuang: tiada laluan yang
        // membenarkan mana-mana peranan melompat peringkat di luar aliran.
        foreach (['workflow.mula', 'workflow.peringkat', 'workflow.status', 'kemajuan.jana-laporan'] as $nama) {
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
            ->assertDontSee(route('kemajuan.selesai', [self::ALPHA, AliranKerja::PENDAFTARAN_DATA]), false)
            ->assertDontSee(route('kemajuan.rujukan', [self::ALPHA, AliranKerja::PENERIMAAN_DATA]), false);
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

        app(KemajuanAnalisisService::class)->lengkapkanPenerimaan(
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
                'status_laporan' => 'Selesai',
            ])
            ->assertForbidden();

        // Memajukan peringkat entiti pegawai lain.
        $this->actingAs($pa)
            ->post(route('kemajuan.selesai', [self::BETA, AliranKerja::SEMAKAN_AWAL_DATA]))
            ->assertForbidden();

        $this->assertDatabaseMissing('analisis_inventori', ['agency_code' => self::BETA]);

        $this->assertSame(
            'Belum Mula',
            app(KemajuanAnalisisService::class)->peringkat(self::BETA)[AliranKerja::SEMAKAN_AWAL_DATA]->status,
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
        $this->actingAs($pa)
            ->postJson(route('kemajuan.selesai', [self::ALPHA, AliranKerja::PENDAFTARAN_DATA]))
            ->assertForbidden();
        $this->actingAs($pa)
            ->postJson(route('kemajuan.rujukan', [self::ALPHA, AliranKerja::PENERIMAAN_DATA]), ['no_rujukan' => 'X'])
            ->assertForbidden();

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

        // Peringkat 1.1 kini milik KB/PPA; fikstur menggunakan PPA.
        $this->assertSame(
            $this->pengguna[User::ROLE_COORDINATOR]->id,
            $log->changed_by_user_id,
        );

        $this->assertSame([User::ROLE_COORDINATOR], $log->metadata['peranan']);
        $this->assertSame('Pegawai Penyelaras Analisis', $log->metadata['peranan_label']);
        $this->assertNotNull($log->changed_at);
    }

    /*
    |--------------------------------------------------------------------------
    | Pembantu
    |--------------------------------------------------------------------------
    */

    /**
     * Bawa entiti sehingga borang Analisis Inventori disimpan sebagai
     * Lengkap, tetapi peringkat 3.1 BELUM ditandakan Selesai.
     */
    private function bawaAnalisisSelesai(): void
    {
        $pa = $this->sebagai(User::ROLE_ANALYST);

        $this->actingAs($pa);
        $this->post(route('kemajuan.selesai', [self::ALPHA, AliranKerja::SEMAKAN_AWAL_DATA]));
        $this->post(route('kemajuan.selesai', [self::ALPHA, AliranKerja::PENYEDIAAN_DATA]));
        $this->post(route('analisis.simpan'), [
            'sector_code' => '001',
            'agency_code' => self::ALPHA,
            'status_laporan' => 'Selesai',
        ]);
    }
}
