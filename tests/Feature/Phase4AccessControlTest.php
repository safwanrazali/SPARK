<?php

namespace Tests\Feature;

use App\Models\AnalisisInventori;
use App\Models\MuatNaik;
use App\Models\StatusLaporan;
use App\Models\User;
use App\Models\WorkflowStatus;
use App\Services\EntityAccessService;
use App\Services\EntityAssignmentService;
use App\Support\SektorDirectory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * FASA 4 — ujian keselamatan kawalan akses berasaskan peranan.
 *
 * Peraturan teras (spesifikasi bahagian 9):
 * Pegawai Analisis HANYA boleh mengakses entiti yang ditugaskan kepadanya.
 *
 * Penguatkuasaan mesti berlaku pada UI, route, controller, service, query
 * dan permintaan API — bukan sekadar menyembunyikan butang.
 */
class Phase4AccessControlTest extends TestCase
{
    use RefreshDatabase;

    /** Entiti yang ditugaskan kepada Pegawai A. */
    private const ALPHA = 'A010101';

    /** Entiti yang TIDAK ditugaskan kepada Pegawai A. */
    private const BETA = 'A010102';

    /** Entiti kedua yang ditugaskan kepada Pegawai A. */
    private const GAMMA = 'A010103';

    private User $analystA;

    private User $analystB;

    private User $coordinator;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->coordinator = User::factory()->create(['role' => User::ROLE_COORDINATOR, 'name' => 'Penyelaras']);
        $this->admin = User::factory()->create(['role' => User::ROLE_ADMINISTRATOR, 'name' => 'Pentadbir']);
        $this->analystA = User::factory()->create(['role' => User::ROLE_ANALYST, 'name' => 'Pegawai A']);
        $this->analystB = User::factory()->create(['role' => User::ROLE_ANALYST, 'name' => 'Pegawai B']);

        $assignments = app(EntityAssignmentService::class);

        // Pegawai A: Alpha ✓, Gamma ✓, Beta ✗ (Beta milik Pegawai B).
        $assignments->assign(SektorDirectory::cariEntiti(self::ALPHA), $this->analystA, $this->coordinator);
        $assignments->assign(SektorDirectory::cariEntiti(self::GAMMA), $this->analystA, $this->coordinator);
        $assignments->assign(SektorDirectory::cariEntiti(self::BETA), $this->analystB, $this->coordinator);
    }

    private function buatData(string $agencyCode): AnalisisInventori
    {
        $entiti = SektorDirectory::cariEntiti($agencyCode);

        MuatNaik::create([
            'nama_fail' => 'master-'.$agencyCode.'.xlsx',
            'lokasi_fail' => 'uploads/'.$agencyCode.'.xlsx',
            'status' => 'Berjaya',
            'tarikh_import' => now(),
        ] + $entiti);

        StatusLaporan::create($entiti + [
            'jenis' => 'inventori',
            'status' => 'Dalam Proses',
            'user_id' => $this->coordinator->id,
        ]);

        WorkflowStatus::factory()->create($entiti + [
            'updated_by_user_id' => $this->coordinator->id,
        ]);

        return AnalisisInventori::factory()->create($entiti + [
            'user_id' => $this->analystA->id,
            'selesai' => true,
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Peraturan teras: akses entiti
    |--------------------------------------------------------------------------
    */

    public function test_pegawai_analisis_hanya_mempunyai_entiti_yang_ditugaskan(): void
    {
        $access = app(EntityAccessService::class);

        $this->assertTrue($access->canAccess($this->analystA, self::ALPHA));
        $this->assertTrue($access->canAccess($this->analystA, self::GAMMA));
        $this->assertFalse($access->canAccess($this->analystA, self::BETA));

        // Penyelaras dan Pentadbir tidak tertakluk kepada penapisan.
        $this->assertFalse($access->isRestricted($this->coordinator));
        $this->assertFalse($access->isRestricted($this->admin));
        $this->assertTrue($access->isRestricted($this->analystA));

        $this->assertTrue($access->canAccess($this->coordinator, self::BETA));
        $this->assertTrue($access->canAccess($this->admin, self::BETA));
    }

    public function test_akses_ditarik_apabila_penugasan_ditukar_ganti(): void
    {
        $access = app(EntityAccessService::class);

        app(EntityAssignmentService::class)->reassign(
            SektorDirectory::cariEntiti(self::ALPHA),
            $this->analystB,
            $this->coordinator,
        );

        $this->assertFalse($access->canAccess($this->analystA->fresh(), self::ALPHA));
        $this->assertTrue($access->canAccess($this->analystB->fresh(), self::ALPHA));
    }

    public function test_pegawai_tanpa_penugasan_tidak_mempunyai_sebarang_akses(): void
    {
        $baharu = User::factory()->create(['role' => User::ROLE_ANALYST]);
        $access = app(EntityAccessService::class);

        $this->assertSame([], $access->accessibleCodes($baharu));
        $this->assertFalse($access->canAccess($baharu, self::ALPHA));
    }

    /*
    |--------------------------------------------------------------------------
    | Penapisan query — lapisan keselamatan sebenar
    |--------------------------------------------------------------------------
    */

    public function test_query_analisis_ditapis_mengikut_entiti_yang_ditugaskan(): void
    {
        $this->buatData(self::ALPHA);
        $this->buatData(self::BETA);

        $dilihatAnalyst = AnalisisInventori::query()->accessibleBy($this->analystA)->pluck('agency_code');
        $dilihatPenyelaras = AnalisisInventori::query()->accessibleBy($this->coordinator)->pluck('agency_code');

        $this->assertContains(self::ALPHA, $dilihatAnalyst);
        $this->assertNotContains(self::BETA, $dilihatAnalyst);
        $this->assertContains(self::BETA, $dilihatPenyelaras);
    }

    public function test_semua_model_entiti_menyokong_penapisan_query(): void
    {
        $this->buatData(self::ALPHA);
        $this->buatData(self::BETA);

        $this->assertSame([self::ALPHA], MuatNaik::query()
            ->accessibleBy($this->analystA)->pluck('agency_code')->all());

        $this->assertSame([self::ALPHA], StatusLaporan::query()
            ->accessibleBy($this->analystA)->pluck('agency_code')->all());

        $this->assertSame([self::ALPHA], WorkflowStatus::query()
            ->accessibleBy($this->analystA)->pluck('agency_code')->all());

        $this->assertCount(2, MuatNaik::query()->accessibleBy($this->coordinator)->get());
    }

    public function test_penapisan_query_menolak_semua_baris_tanpa_pengguna(): void
    {
        $this->buatData(self::ALPHA);

        $this->assertCount(0, AnalisisInventori::query()->accessibleBy(null)->get());
    }

    /*
    |--------------------------------------------------------------------------
    | Senarai (UI) — entiti tidak ditugaskan tidak boleh bocor
    |--------------------------------------------------------------------------
    */

    public function test_senarai_analisis_tidak_membocorkan_entiti_tidak_ditugaskan(): void
    {
        $this->buatData(self::ALPHA);
        $this->buatData(self::BETA);

        $this->actingAs($this->analystA)
            ->get(route('analisis.index'))
            ->assertOk()
            ->assertSee('Suruhanjaya Pilihan Raya (SPR)')
            ->assertDontSee('Suruhanjaya Pencegahan Rasuah Malaysia (SPRM)');
    }

    public function test_senarai_laporan_tidak_membocorkan_entiti_tidak_ditugaskan(): void
    {
        $this->buatData(self::ALPHA);
        $this->buatData(self::BETA);

        $this->actingAs($this->analystA)
            ->get(route('laporan.index'))
            ->assertOk()
            ->assertSee('Suruhanjaya Pilihan Raya (SPR)')
            ->assertDontSee('Suruhanjaya Pencegahan Rasuah Malaysia (SPRM)');
    }

    public function test_senarai_status_laporan_tidak_membocorkan_entiti_tidak_ditugaskan(): void
    {
        $this->buatData(self::ALPHA);
        $this->buatData(self::BETA);

        $this->actingAs($this->analystA)
            ->get(route('status.index'))
            ->assertOk()
            ->assertSee('Suruhanjaya Pilihan Raya (SPR)')
            ->assertDontSee('Suruhanjaya Pencegahan Rasuah Malaysia (SPRM)');
    }

    public function test_senarai_workflow_tidak_membocorkan_entiti_tidak_ditugaskan(): void
    {
        $this->buatData(self::ALPHA);
        $this->buatData(self::BETA);

        $this->actingAs($this->analystA)
            ->get(route('workflow.index'))
            ->assertOk()
            ->assertSee('Suruhanjaya Pilihan Raya (SPR)')
            ->assertDontSee('Suruhanjaya Pencegahan Rasuah Malaysia (SPRM)');
    }

    public function test_sejarah_muat_naik_tidak_membocorkan_entiti_tidak_ditugaskan(): void
    {
        $this->buatData(self::ALPHA);
        $this->buatData(self::BETA);

        $this->actingAs($this->analystA)
            ->get(route('muat-naik.history'))
            ->assertOk()
            ->assertDontSee('master-'.self::BETA.'.xlsx');
    }

    public function test_penapis_sektor_tidak_boleh_digunakan_untuk_mendedahkan_entiti_lain(): void
    {
        // Sektor 001 mengandungi Alpha, Beta dan Gamma. Pegawai A hanya
        // ditugaskan Alpha dan Gamma.
        $this->actingAs($this->analystA)
            ->get(route('workflow.index', ['sector_code' => '001']))
            ->assertOk()
            ->assertSee('Suruhanjaya Pilihan Raya (SPR)')
            ->assertDontSee('Suruhanjaya Pencegahan Rasuah Malaysia (SPRM)');
    }

    public function test_pemilih_entiti_borang_analisis_hanya_menawarkan_entiti_ditugaskan(): void
    {
        $this->actingAs($this->analystA)
            ->get(route('analisis.index'))
            ->assertOk()
            ->assertSee('A010101')
            ->assertDontSee('A010102');
    }

    public function test_dashboard_pegawai_analisis_tidak_memaparkan_angka_keseluruhan(): void
    {
        $this->buatData(self::ALPHA);
        $this->buatData(self::BETA);

        // Penyelaras melihat kedua-dua entiti; Pegawai A hanya satu.
        $this->actingAs($this->coordinator)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertViewHas('jumlahEntiti', 2);

        $this->actingAs($this->analystA)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertViewHas('jumlahEntiti', 1)
            ->assertViewHas('dashboardKeseluruhan', false);
    }

    /*
    |--------------------------------------------------------------------------
    | Capaian URL langsung — tidak boleh dipintas
    |--------------------------------------------------------------------------
    */

    public function test_capaian_url_langsung_ke_workflow_entiti_tidak_ditugaskan_ditolak(): void
    {
        $this->buatData(self::BETA);

        $this->actingAs($this->analystA)
            ->get(route('workflow.show', self::BETA))
            ->assertForbidden();
    }

    public function test_capaian_url_langsung_ke_workflow_entiti_ditugaskan_dibenarkan(): void
    {
        $this->buatData(self::ALPHA);

        $this->actingAs($this->analystA)
            ->get(route('workflow.show', self::ALPHA))
            ->assertOk();

        $this->actingAs($this->analystA)
            ->get(route('workflow.show', self::GAMMA))
            ->assertOk();
    }

    public function test_capaian_url_langsung_ke_borang_analisis_entiti_tidak_ditugaskan_ditolak(): void
    {
        $this->actingAs($this->analystA)
            ->get(route('analisis.borang', ['sector_code' => '001', 'agency_code' => self::BETA]))
            ->assertForbidden();
    }

    public function test_borang_analisis_entiti_ditugaskan_dibenarkan(): void
    {
        $this->actingAs($this->analystA)
            ->get(route('analisis.borang', ['sector_code' => '001', 'agency_code' => self::ALPHA]))
            ->assertOk();
    }

    public function test_penulisan_analisis_ke_entiti_tidak_ditugaskan_ditolak(): void
    {
        $this->actingAs($this->analystA)
            ->post(route('analisis.simpan'), [
                'sector_code' => '001',
                'agency_code' => self::BETA,
                'status_laporan' => 'Muktamad',
                'ringkasan_data' => 'lengkap',
            ])
            ->assertForbidden();

        $this->assertDatabaseMissing('analisis_inventori', ['agency_code' => self::BETA]);
    }

    public function test_capaian_ke_entiti_di_luar_penugasan_ditolak_walaupun_rekod_wujud(): void
    {
        $beta = $this->buatData(self::BETA);

        $this->actingAs($this->analystA)
            ->get(route('laporan.inventori', $beta))
            ->assertForbidden();
    }

    /*
    |--------------------------------------------------------------------------
    | Akses laporan
    |--------------------------------------------------------------------------
    */

    public function test_laporan_entiti_ditugaskan_boleh_dibuka(): void
    {
        $alpha = $this->buatData(self::ALPHA);

        $this->actingAs($this->analystA)
            ->get(route('laporan.inventori', $alpha))
            ->assertOk();
    }

    public function test_muat_turun_laporan_entiti_tidak_ditugaskan_ditolak(): void
    {
        $beta = $this->buatData(self::BETA);

        $this->actingAs($this->analystA)
            ->get(route('laporan.unduh', $beta))
            ->assertForbidden();
    }

    public function test_penyelaras_boleh_membuka_laporan_mana_mana_entiti(): void
    {
        $beta = $this->buatData(self::BETA);

        $this->actingAs($this->coordinator)
            ->get(route('laporan.inventori', $beta))
            ->assertOk();

        $this->actingAs($this->admin)
            ->get(route('laporan.inventori', $beta))
            ->assertOk();
    }

    /*
    |--------------------------------------------------------------------------
    | Capaian JSON / API — tiada pintasan
    |--------------------------------------------------------------------------
    */

    public function test_permintaan_json_ke_entiti_tidak_ditugaskan_ditolak(): void
    {
        $beta = $this->buatData(self::BETA);

        $this->actingAs($this->analystA)
            ->getJson(route('workflow.show', self::BETA))
            ->assertForbidden();

        $this->actingAs($this->analystA)
            ->getJson(route('laporan.inventori', $beta))
            ->assertForbidden();
    }

    public function test_permintaan_ajax_tidak_boleh_memintas_penapisan(): void
    {
        $this->buatData(self::BETA);

        $this->actingAs($this->analystA)
            ->withHeaders([
                'X-Requested-With' => 'XMLHttpRequest',
                'Accept' => 'application/json',
            ])
            ->get(route('workflow.show', self::BETA))
            ->assertForbidden();
    }

    public function test_penulisan_json_ke_entiti_tidak_ditugaskan_ditolak(): void
    {
        $this->actingAs($this->analystA)
            ->postJson(route('analisis.simpan'), [
                'sector_code' => '001',
                'agency_code' => self::BETA,
                'status_laporan' => 'Muktamad',
                'ringkasan_data' => 'lengkap',
            ])
            ->assertForbidden();

        $this->assertDatabaseMissing('analisis_inventori', ['agency_code' => self::BETA]);
    }

    public function test_setiap_route_aplikasi_dilindungi_middleware_auth(): void
    {
        // Aplikasi ini tidak mendedahkan routes/api.php.
        $this->assertFalse(file_exists(base_path('routes/api.php')));

        foreach (app('router')->getRoutes() as $route) {
            $tindakan = $route->getActionName();

            // Hanya route aplikasi disemak; route rangka kerja diuji berasingan.
            if (! str_starts_with($tindakan, 'App\\Http\\Controllers\\')) {
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

    public function test_fail_muat_naik_persendirian_tidak_boleh_dicapai_tanpa_tandatangan(): void
    {
        // Route storage/{path} rangka kerja menyajikan disk 'local'
        // (storage/app/private) tempat fail muat naik disimpan. Ia mesti
        // menolak capaian tanpa URL bertandatangan, bagi bacaan dan penulisan.
        Storage::disk('local')
            ->put('uploads/rahsia-entiti.xlsx', 'DATA RAHSIA');

        $this->get('/storage/uploads/rahsia-entiti.xlsx')->assertForbidden();

        $this->actingAs($this->analystA)
            ->get('/storage/uploads/rahsia-entiti.xlsx')
            ->assertForbidden();

        $this->put('/storage/uploads/disuntik.txt', ['x' => 1])->assertForbidden();

        $this->assertFalse(
            Storage::disk('local')->exists('uploads/disuntik.txt')
        );
    }

    public function test_tetamu_tidak_boleh_mengakses_mana_mana_route_entiti(): void
    {
        $alpha = $this->buatData(self::ALPHA);

        $this->get(route('workflow.show', self::ALPHA))->assertRedirect(route('login'));
        $this->get(route('laporan.inventori', $alpha))->assertRedirect(route('login'));
        $this->get(route('analisis.index'))->assertRedirect(route('login'));
        $this->get(route('status.index'))->assertRedirect(route('login'));
    }

    /*
    |--------------------------------------------------------------------------
    | Akses Penyelaras / Pentadbir mengikut kebenaran
    |--------------------------------------------------------------------------
    */

    public function test_penyelaras_boleh_mengakses_semua_entiti(): void
    {
        $this->buatData(self::BETA);

        $this->actingAs($this->coordinator)
            ->get(route('workflow.show', self::BETA))
            ->assertOk();

        $this->actingAs($this->coordinator)
            ->get(route('penugasan.show', self::BETA))
            ->assertOk();
    }

    public function test_pentadbir_boleh_mengakses_semua_entiti(): void
    {
        $this->buatData(self::BETA);

        $this->actingAs($this->admin)
            ->get(route('workflow.show', self::BETA))
            ->assertOk();
    }

    public function test_kebenaran_peranan_sedia_ada_tidak_berubah(): void
    {
        // Penyelaras tetap tiada kebenaran input analisis (gate manage-analysis).
        $this->actingAs($this->coordinator)
            ->get(route('analisis.borang', ['sector_code' => '001', 'agency_code' => self::ALPHA]))
            ->assertForbidden();

        // Pegawai Analisis tetap tiada kebenaran penugasan mahupun status laporan.
        $this->actingAs($this->analystA)
            ->get(route('penugasan.index'))
            ->assertForbidden();

        $this->actingAs($this->analystA)
            ->post(route('status.kitar'), [
                'sector_code' => '001',
                'sector_name' => 'Kerajaan',
                'agency_code' => self::ALPHA,
                'agency_name' => 'Suruhanjaya Pilihan Raya (SPR)',
                'jenis' => 'inventori',
            ])
            ->assertForbidden();
    }

    public function test_modul_muat_naik_dihadkan_kepada_peranan_yang_dibenarkan(): void
    {
        $this->actingAs($this->analystA)->get(route('muat-naik.index'))->assertForbidden();
        $this->actingAs($this->coordinator)->get(route('muat-naik.index'))->assertOk();
    }

    /*
    |--------------------------------------------------------------------------
    | Kekangan UI bukan satu-satunya lapisan
    |--------------------------------------------------------------------------
    */

    public function test_pautan_entiti_tidak_ditugaskan_tidak_dipaparkan_dan_tidak_boleh_dicapai(): void
    {
        $this->buatData(self::BETA);

        // 1. UI tidak memaparkannya.
        $this->actingAs($this->analystA)
            ->get(route('workflow.index'))
            ->assertDontSee(route('workflow.show', self::BETA));

        // 2. Capaian terus tetap ditolak (bukan sekadar butang tersembunyi).
        $this->actingAs($this->analystA)
            ->get(route('workflow.show', self::BETA))
            ->assertForbidden();
    }
}
