<?php

namespace Tests\Feature;

use App\Models\AnalisisInventori;
use App\Models\ApprovalLog;
use App\Models\User;
use App\Models\WorkflowStatus;
use App\Services\EntityAssignmentService;
use App\Support\SektorDirectory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * FASA 12 — matriks kebenaran merentas SETIAP peranan dan SETIAP modul.
 *
 * Ujian fasa terdahulu menguji modul masing-masing secara berasingan.
 * Ujian ini menyemak keseluruhan permukaan capaian sekali gus supaya
 * tiada route terlepas apabila modul digabungkan:
 *
 * - kebenaran peranan (spesifikasi bahagian 26)
 * - akses "assigned-only" Pegawai Analisis (bahagian 9)
 * - capaian URL langsung dan permintaan JSON/API
 */
class Phase12AuthorizationMatrixTest extends TestCase
{
    use RefreshDatabase;

    /** Entiti yang ditugaskan kepada Pegawai Analisis A. */
    private const ALPHA = 'A010101';

    /** Entiti yang ditugaskan kepada Pegawai Analisis B. */
    private const BETA = 'A010102';

    /** Dibenarkan: 200 bagi GET, pengalihan bukan-403 bagi POST. */
    private const BENAR = 'benar';

    /** Ditolak dengan 403. */
    private const TOLAK = 'tolak';

    /** Dialihkan ke modul lain (bukan penolakan). */
    private const ALIH = 'alih';

    /** @var array<string, User> */
    private array $pengguna = [];

    protected function setUp(): void
    {
        parent::setUp();

        foreach (User::roles() as $peranan) {
            $this->pengguna[$peranan] = User::factory()->create(['role' => $peranan]);
        }

        $analystB = User::factory()->create(['role' => User::ROLE_ANALYST]);

        $assignments = app(EntityAssignmentService::class);
        $assignments->assign(
            SektorDirectory::cariEntiti(self::ALPHA),
            $this->pengguna[User::ROLE_ANALYST],
            $this->pengguna[User::ROLE_COORDINATOR],
        );
        $assignments->assign(
            SektorDirectory::cariEntiti(self::BETA),
            $analystB,
            $this->pengguna[User::ROLE_COORDINATOR],
        );
    }

    /**
     * Semak satu route terhadap setiap peranan dalam sistem.
     *
     * @param  array<string, string>  $jangkaan  peranan => BENAR|TOLAK|ALIH
     * @param  array<string, mixed>  $data
     */
    private function semakMatriks(string $kaedah, string $url, array $jangkaan, array $data = []): void
    {
        foreach (User::roles() as $peranan) {
            $respons = $this->actingAs($this->pengguna[$peranan])
                ->call($kaedah, $url, $data);

            $sepatutnya = $jangkaan[$peranan] ?? self::TOLAK;
            $status = $respons->getStatusCode();
            $mesej = sprintf(
                '[%s] %s %s — dijangka %s, diterima status %d.',
                $peranan,
                strtoupper($kaedah),
                $url,
                $sepatutnya,
                $status,
            );

            match ($sepatutnya) {
                self::BENAR => $kaedah === 'GET'
                    ? $this->assertSame(200, $status, $mesej)
                    : $this->assertNotSame(403, $status, $mesej),
                self::TOLAK => $this->assertSame(403, $status, $mesej),
                self::ALIH => $this->assertSame(302, $status, $mesej),
            };
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Papan pemuka dan modul pemantauan
    |--------------------------------------------------------------------------
    */

    public function test_papan_pemuka_terbuka_kepada_semua_kecuali_pegawai_analisis(): void
    {
        // Pegawai Analisis bekerja daripada senarai entiti yang ditugaskan;
        // capaian terus ke papan pemuka DITOLAK, bukan dialihkan.
        $this->semakMatriks('GET', route('dashboard'), [
            User::ROLE_ADMINISTRATOR => self::BENAR,
            User::ROLE_COORDINATOR => self::BENAR,
            User::ROLE_KETUA_BAHAGIAN => self::BENAR,
            User::ROLE_TIMBALAN_PENGARAH_II => self::BENAR,
            User::ROLE_PEGAWAI_KAWALAN_DOKUMEN => self::BENAR,
            User::ROLE_PENYELARAS_REKOD => self::BENAR,
        ]);
    }

    public function test_senarai_pemantauan_terbuka_kepada_semua_peranan_tetapi_ditapis(): void
    {
        foreach ([
            route('workflow.index'),
            route('analisis.index'),
            route('laporan.index'),
            route('muat-naik.history'),
        ] as $url) {
            $this->semakMatriks('GET', $url, array_fill_keys(User::roles(), self::BENAR));
        }
    }

    public function test_status_tiga_laporan_terbuka_kepada_semua_kecuali_pentadbir_sistem(): void
    {
        // Sekatan khas: modul operasi ini bukan untuk Pentadbir Sistem.
        $this->semakMatriks('GET', route('status.index'), [
            User::ROLE_TIMBALAN_PENGARAH_II => self::BENAR,
            User::ROLE_KETUA_BAHAGIAN => self::BENAR,
            User::ROLE_PENYELARAS_REKOD => self::BENAR,
            User::ROLE_PEGAWAI_KAWALAN_DOKUMEN => self::BENAR,
            User::ROLE_COORDINATOR => self::BENAR,
            User::ROLE_ANALYST => self::BENAR,
        ]);
    }

    public function test_penetapan_entiti_dikongsi_mengikut_panel_peranan(): void
    {
        // Skrin "Penetapan Entiti" kini memegang dua panel: pendaftaran
        // (peringkat 1) milik Pegawai Penyelaras Rekod, dibuka semula oleh
        // Ketua Bahagian; penugasan milik Pegawai Penyelaras Analisis.
        // Setiap panel disediakan hanya untuk peranan yang berhak.
        $this->semakMatriks('GET', route('penugasan.index'), [
            User::ROLE_COORDINATOR => self::BENAR,
            User::ROLE_PENYELARAS_REKOD => self::BENAR,
            User::ROLE_KETUA_BAHAGIAN => self::BENAR,
        ]);

        // Sejarah penugasan satu entiti kekal milik modul penugasan sahaja.
        $this->semakMatriks('GET', route('penugasan.show', self::ALPHA), [
            User::ROLE_COORDINATOR => self::BENAR,
        ]);
    }

    public function test_status_laporan_hanya_boleh_dikitar_oleh_penyelaras(): void
    {
        $this->semakMatriks('POST', route('status.kitar'), [
            User::ROLE_COORDINATOR => self::BENAR,
        ], SektorDirectory::cariEntiti(self::ALPHA) + ['jenis' => 'inventori']);
    }

    public function test_jejak_audit_berpusat_terbuka_kepada_semua_peranan(): void
    {
        // Kandungan tetap ditapis mengikut entiti yang boleh diakses, dan
        // rekod audit kekal tidak boleh diubah oleh sesiapa.
        $this->semakMatriks('GET', route('audit.index'), array_fill_keys(User::roles(), self::BENAR));
    }

    public function test_modul_muat_naik_dan_pentadbiran_kekal_terhad(): void
    {
        $this->semakMatriks('GET', route('muat-naik.index'), [
            User::ROLE_ADMINISTRATOR => self::BENAR,
            User::ROLE_COORDINATOR => self::BENAR,
        ]);


        $this->semakMatriks('GET', route('administration.users.index'), [
            User::ROLE_ADMINISTRATOR => self::BENAR,
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Input analisis dan draf — spesifikasi bahagian 26
    |--------------------------------------------------------------------------
    */

    public function test_input_analisis_dan_draf_hanya_untuk_pegawai_analisis(): void
    {
        $dibenarkan = [
            User::ROLE_ANALYST => self::BENAR,
        ];

        $entiti = ['sector_code' => '001', 'agency_code' => self::ALPHA];

        $this->semakMatriks('GET', route('analisis.borang', $entiti), $dibenarkan);
        $this->semakMatriks('POST', route('analisis.draf'), $dibenarkan, $entiti);
        $this->semakMatriks('POST', route('analisis.simpan'), $dibenarkan, $entiti + [
            'status_laporan' => 'Muktamad',
            'ringkasan_data' => 'lengkap',
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Pusat maklumat entiti — akses mengikut penugasan
    |--------------------------------------------------------------------------
    */

    public function test_entiti_ditugaskan_boleh_dilihat_oleh_pegawai_yang_berkenaan(): void
    {
        // Semua peranan boleh MELIHAT; Pegawai Analisis hanya bagi entiti
        // yang ditugaskan kepadanya (ALPHA ialah entiti tugasannya).
        $semua = array_fill_keys(User::roles(), self::BENAR);

        $this->semakMatriks('GET', route('entiti.show', self::ALPHA), $semua);
        $this->semakMatriks('GET', route('workflow.show', self::ALPHA), $semua);
    }

    public function test_entiti_pegawai_lain_tidak_boleh_dilihat_oleh_pegawai_analisis(): void
    {
        // BETA bukan tugasan pegawai analisis dalam ujian ini — hanya dia
        // yang ditolak; peranan lain melihat semua entiti.
        $kecualiPegawaiAnalisis = array_fill_keys(User::roles(), self::BENAR);
        unset($kecualiPegawaiAnalisis[User::ROLE_ANALYST]);

        $this->semakMatriks('GET', route('entiti.show', self::BETA), $kecualiPegawaiAnalisis);
        $this->semakMatriks('GET', route('workflow.show', self::BETA), $kecualiPegawaiAnalisis);
    }

    /**
     * Peranan baca-sahaja (PKD dan PPR) boleh MELIHAT mana-mana entiti,
     * tetapi tiada satu pun tindakan menulis terbuka kepada mereka.
     *
     * Melihat dan bertindak ialah dua kebenaran berasingan; ujian ini
     * menegaskan kedua-duanya sekali gus.
     */
    public function test_peranan_baca_sahaja_melihat_entiti_tanpa_kuasa_menulis(): void
    {
        foreach ([User::ROLE_PEGAWAI_KAWALAN_DOKUMEN, User::ROLE_PENYELARAS_REKOD] as $peranan) {
            $pengguna = $this->pengguna[$peranan];

            foreach ([self::ALPHA, self::BETA] as $kod) {
                $this->actingAs($pengguna)->get(route('entiti.show', $kod))->assertOk();
                $this->actingAs($pengguna)->get(route('workflow.show', $kod))->assertOk();
            }

            // Tiada kuasa memajukan peringkat, menyemak atau meluluskan.
            foreach (['advance-analysis-stage', 'review-report', 'approve-report', 'submit-to-nacsa'] as $gate) {
                $this->assertFalse(
                    $pengguna->can($gate),
                    "Peranan {$peranan} sepatutnya tidak memiliki {$gate}.",
                );
            }
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Capaian URL langsung — setiap route entiti
    |--------------------------------------------------------------------------
    */

    public function test_setiap_route_entiti_menolak_capaian_url_langsung_pegawai_analisis(): void
    {
        $beta = AnalisisInventori::factory()->create(
            SektorDirectory::cariEntiti(self::BETA) + ['user_id' => $this->pengguna[User::ROLE_ADMINISTRATOR]->id]
        );

        WorkflowStatus::factory()->create(SektorDirectory::cariEntiti(self::BETA));

        $analyst = $this->pengguna[User::ROLE_ANALYST];

        $capaian = [
            ['GET', route('entiti.show', self::BETA), []],
            ['GET', route('workflow.show', self::BETA), []],
            ['GET', route('laporan.inventori', $beta), []],
            ['GET', route('laporan.unduh', $beta), []],
            ['GET', route('analisis.borang', ['sector_code' => '001', 'agency_code' => self::BETA]), []],
            ['GET', route('audit.index', ['agency_code' => self::BETA]), []],
            ['POST', route('analisis.draf'), ['sector_code' => '001', 'agency_code' => self::BETA]],
            ['POST', route('analisis.simpan'), [
                'sector_code' => '001',
                'agency_code' => self::BETA,
                'status_laporan' => 'Muktamad',
                'ringkasan_data' => 'lengkap',
            ]],
            ['POST', route('kemajuan.selesai', [self::BETA, 2]), []],
            ['POST', route('kemajuan.jana-laporan', self::BETA), []],
            ['POST', route('penugasan.simpan', self::BETA), ['assigned_to_user_id' => $analyst->id]],
            ['POST', route('penugasan.tarik', self::BETA), []],
        ];

        foreach ($capaian as [$kaedah, $url, $data]) {
            $respons = $this->actingAs($analyst)->call($kaedah, $url, $data);

            $this->assertSame(
                403,
                $respons->getStatusCode(),
                sprintf('%s %s sepatutnya ditolak.', $kaedah, $url),
            );
        }

        // Tiada kesan sampingan pada rekod entiti yang tidak ditugaskan.
        $this->assertSame(1, WorkflowStatus::where('agency_code', self::BETA)->count());
        $this->assertSame(
            WorkflowStatus::FIRST_STAGE,
            WorkflowStatus::where('agency_code', self::BETA)->first()->current_stage,
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Capaian JSON / API
    |--------------------------------------------------------------------------
    */

    public function test_permintaan_json_tertakluk_kepada_kebenaran_yang_sama(): void
    {
        $analyst = $this->pengguna[User::ROLE_ANALYST];

        $ditolak = [
            ['getJson', route('workflow.show', self::BETA), null],
            ['getJson', route('entiti.show', self::BETA), null],
            ['getJson', route('penugasan.index'), null],
            ['postJson', route('analisis.draf'), ['sector_code' => '001', 'agency_code' => self::BETA]],
            ['postJson', route('kemajuan.selesai', [self::BETA, 2]), []],
        ];

        foreach ($ditolak as [$kaedah, $url, $data]) {
            $respons = $data === null
                ? $this->actingAs($analyst)->{$kaedah}($url)
                : $this->actingAs($analyst)->{$kaedah}($url, $data);

            $respons->assertForbidden();
        }
    }

    /**
     * Permintaan JSON tanpa sesi tidak dilayan. Aplikasi ini ialah aplikasi
     * sesi web tanpa routes/api.php, jadi permintaan tanpa pengesahan
     * dialihkan ke halaman log masuk — yang penting, tiada data entiti
     * dikembalikan dan tiada penulisan berlaku.
     */
    public function test_permintaan_json_tanpa_sesi_tidak_dilayan(): void
    {
        $this->getJson(route('workflow.show', self::ALPHA))
            ->assertRedirect(route('login'));

        $this->postJson(route('analisis.draf'), ['sector_code' => '001', 'agency_code' => self::ALPHA])
            ->assertRedirect(route('login'));

        $this->assertDatabaseMissing('analisis_inventori', ['agency_code' => self::ALPHA]);
    }

    public function test_aplikasi_tidak_mendedahkan_permukaan_api_tanpa_kebenaran(): void
    {
        $this->assertFalse(
            file_exists(base_path('routes/api.php')),
            'routes/api.php wujud tanpa kawalan kebenaran yang diuji.',
        );

        foreach (app('router')->getRoutes() as $route) {
            if (! str_starts_with($route->getActionName(), 'App\\Http\\Controllers\\')) {
                continue;
            }

            $middleware = $route->gatherMiddleware();

            if (in_array('guest', $middleware, true)) {
                continue;
            }

            $this->assertContains(
                'auth',
                $middleware,
                "Route [{$route->uri()}] tidak dilindungi middleware auth.",
            );
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Semakan & Kelulusan — belum dilaksanakan (Fasa 10)
    |--------------------------------------------------------------------------
    */

    /**
     * Aliran semakan dan kelulusan laporan ialah skop Fasa 10 dan belum
     * dibina. Yang WAJIB dipastikan sekarang ialah tiada laluan kelulusan
     * tersembunyi yang boleh dipintas: tiada route, dan pemetaan kebenaran
     * kekal seperti matriks yang disahkan.
     */
    public function test_tiada_laluan_semakan_atau_kelulusan_laporan_yang_terdedah(): void
    {
        foreach (app('router')->getRoutes() as $route) {
            $this->assertDoesNotMatchRegularExpression(
                '/(approve|approval|semakan|kelulusan)/i',
                $route->uri(),
                "Route [{$route->uri()}] mendedahkan tindakan kelulusan yang belum disahkan.",
            );
        }

        $this->assertSame(0, ApprovalLog::count());
    }

    public function test_pemetaan_kebenaran_semakan_dan_kelulusan_mengikut_matriks(): void
    {
        $semak = [
            User::ROLE_COORDINATOR => true,
            User::ROLE_KETUA_BAHAGIAN => true,
            User::ROLE_ADMINISTRATOR => false,
            User::ROLE_ANALYST => false,
            User::ROLE_PEGAWAI_KAWALAN_DOKUMEN => false,
            User::ROLE_PENYELARAS_REKOD => false,
            User::ROLE_TIMBALAN_PENGARAH_II => false,
        ];

        $lulus = [
            User::ROLE_ADMINISTRATOR => false,
            User::ROLE_KETUA_BAHAGIAN => true,
            User::ROLE_COORDINATOR => false,
            User::ROLE_ANALYST => false,
            User::ROLE_PEGAWAI_KAWALAN_DOKUMEN => false,
            User::ROLE_PENYELARAS_REKOD => false,
            User::ROLE_TIMBALAN_PENGARAH_II => false,
        ];

        foreach (User::roles() as $peranan) {
            $this->assertSame(
                $semak[$peranan],
                $this->pengguna[$peranan]->can('review-report'),
                "Kebenaran review-report bagi {$peranan} tidak mengikut matriks.",
            );

            $this->assertSame(
                $lulus[$peranan],
                $this->pengguna[$peranan]->can('approve-report'),
                "Kebenaran approve-report bagi {$peranan} tidak mengikut matriks.",
            );
        }
    }
}
