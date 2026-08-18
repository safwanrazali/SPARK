<?php

namespace Tests\Feature;

use App\Models\AnalisisInventori;
use App\Models\StatusLaporan;
use App\Models\User;
use App\Models\WorkflowStatus;
use App\Services\EntityAssignmentService;
use App\Services\WorkflowTransitionService;
use App\Support\SektorDirectory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * FASA 5 — pusat maklumat entiti (spesifikasi bahagian 13).
 *
 * Halaman ini diuji di bawah setiap peranan: Pentadbir, Pegawai Penyelaras
 * Analisis, Pegawai Analisis (ditugaskan dan tidak ditugaskan) serta tetamu.
 */
class Phase5EntityDetailTest extends TestCase
{
    use RefreshDatabase;

    /** Entiti yang ditugaskan kepada Pegawai A. */
    private const ALPHA = 'A010101';

    /** Entiti yang TIDAK ditugaskan kepada Pegawai A. */
    private const BETA = 'A010102';

    private User $admin;

    private User $coordinator;

    private User $analystA;

    private User $analystB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['role' => User::ROLE_ADMINISTRATOR, 'name' => 'Pentadbir']);
        $this->coordinator = User::factory()->create(['role' => User::ROLE_COORDINATOR, 'name' => 'Penyelaras Satu']);
        $this->analystA = User::factory()->create(['role' => User::ROLE_ANALYST, 'name' => 'Pegawai A']);
        $this->analystB = User::factory()->create(['role' => User::ROLE_ANALYST, 'name' => 'Pegawai B']);

        $assignments = app(EntityAssignmentService::class);
        $assignments->assign(SektorDirectory::cariEntiti(self::ALPHA), $this->analystA, $this->coordinator);
        $assignments->assign(SektorDirectory::cariEntiti(self::BETA), $this->analystB, $this->coordinator);
    }

    /**
     * Bina rekod lengkap bagi satu entiti: workflow, analisis dan status laporan.
     */
    private function buatRekodLengkap(string $agencyCode = self::ALPHA): void
    {
        $entiti = SektorDirectory::cariEntiti($agencyCode);

        $workflow = WorkflowStatus::factory()->create($entiti + [
            'updated_by_user_id' => $this->coordinator->id,
        ]);

        // 1 → 2 → 3 supaya stepper mempunyai peringkat selesai dan semasa.
        $transitions = app(WorkflowTransitionService::class);
        $transitions->advance($workflow, $this->coordinator);
        $transitions->advance($workflow, $this->coordinator, 'Dalam Proses');

        AnalisisInventori::factory()->create($entiti + [
            'user_id' => $this->analystA->id,
            'kod_rujukan' => 'REF-ALPHA-001',
            'selesai' => true,
        ]);

        StatusLaporan::create($entiti + [
            'jenis' => 'inventori',
            'status' => 'Dalam Proses',
            'user_id' => $this->coordinator->id,
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Kandungan halaman
    |--------------------------------------------------------------------------
    */

    public function test_halaman_memaparkan_maklumat_entiti_dan_sektor(): void
    {
        $this->actingAs($this->coordinator)
            ->get(route('entiti.show', self::ALPHA))
            ->assertOk()
            ->assertSee('A010101')
            ->assertSee('Sektor 001')
            ->assertSee(self::ALPHA);
    }

    public function test_halaman_memaparkan_kesemua_seksyen_yang_ditetapkan(): void
    {
        $this->buatRekodLengkap();

        $this->actingAs($this->coordinator)
            ->get(route('entiti.show', self::ALPHA))
            ->assertOk()
            ->assertSee('Kedudukan Semasa')
            ->assertSee('Kemajuan Workflow')
            ->assertSee('Penugasan')
            ->assertSee('Dapatan Analisis')
            ->assertSee('Laporan')
            ->assertSee('Sejarah');
    }

    public function test_halaman_memaparkan_penugasan_semasa(): void
    {
        $this->actingAs($this->coordinator)
            ->get(route('entiti.show', self::ALPHA))
            ->assertOk()
            ->assertSee('Pegawai A')
            ->assertSee('Penyelaras Satu')
            ->assertSee('Aktif');
    }

    public function test_halaman_memaparkan_peringkat_status_dan_tarikh_status(): void
    {
        Carbon::setTestNow('2026-08-20 14:30:00');
        $this->buatRekodLengkap();
        Carbon::setTestNow();

        $this->actingAs($this->coordinator)
            ->get(route('entiti.show', self::ALPHA))
            ->assertOk()
            ->assertSee('Penyediaan &amp; Pengesahan Data', false)
            ->assertSee('Dalam Proses')
            ->assertSee('20/08/2026 14:30');
    }

    public function test_halaman_memaparkan_stepper_workflow_tujuh_peringkat(): void
    {
        $this->buatRekodLengkap();

        $response = $this->actingAs($this->coordinator)
            ->get(route('entiti.show', self::ALPHA))
            ->assertOk();

        foreach (WorkflowStatus::WORKFLOW_STAGES as $nama) {
            $response->assertSee($nama);
        }

        // Peringkat 3 daripada 7 — ada peringkat selesai dan peringkat semasa.
        $response->assertSee('workflow-step--selesai', false)
            ->assertSee('workflow-step--semasa', false)
            ->assertSee('Peringkat 3 daripada 7');
    }

    public function test_halaman_memaparkan_status_ketiga_tiga_laporan(): void
    {
        $this->buatRekodLengkap();

        $response = $this->actingAs($this->coordinator)
            ->get(route('entiti.show', self::ALPHA))
            ->assertOk();

        foreach (StatusLaporan::JENIS as $nama) {
            $response->assertSee($nama);
        }

        $response->assertSee('Belum Bermula');
    }

    public function test_halaman_memaparkan_dapatan_analisis(): void
    {
        $this->buatRekodLengkap();

        $this->actingAs($this->coordinator)
            ->get(route('entiti.show', self::ALPHA))
            ->assertOk()
            ->assertSee('REF-ALPHA-001')
            ->assertSee('Selesai');
    }

    public function test_halaman_memaparkan_sejarah_workflow_dan_penugasan(): void
    {
        $this->buatRekodLengkap();

        $this->actingAs($this->coordinator)
            ->get(route('entiti.show', self::ALPHA))
            ->assertOk()
            ->assertSee('Peringkat Workflow Berubah')
            ->assertSee('Penugasan Dibuat');
    }

    public function test_halaman_mengendalikan_entiti_tanpa_sebarang_rekod(): void
    {
        $kosong = 'A010103';

        app(EntityAssignmentService::class)->assign(
            SektorDirectory::cariEntiti($kosong),
            $this->analystA,
            $this->coordinator,
        );

        $this->actingAs($this->coordinator)
            ->get(route('entiti.show', $kosong))
            ->assertOk()
            ->assertSee('Belum Didaftarkan')
            ->assertSee('Tiada dapatan analisis direkodkan untuk entiti ini.');
    }

    public function test_entiti_di_luar_senarai_induk_menghasilkan_404(): void
    {
        $this->actingAs($this->admin)
            ->get(route('entiti.show', 'ZZZ9999'))
            ->assertNotFound();
    }

    /*
    |--------------------------------------------------------------------------
    | Ujian mengikut peranan
    |--------------------------------------------------------------------------
    */

    public function test_tetamu_dialihkan_ke_log_masuk(): void
    {
        $this->get(route('entiti.show', self::ALPHA))->assertRedirect(route('login'));
    }

    public function test_pentadbir_boleh_membuka_mana_mana_entiti(): void
    {
        $this->actingAs($this->admin)->get(route('entiti.show', self::ALPHA))->assertOk();
        $this->actingAs($this->admin)->get(route('entiti.show', self::BETA))->assertOk();
    }

    public function test_penyelaras_boleh_membuka_mana_mana_entiti(): void
    {
        $this->actingAs($this->coordinator)->get(route('entiti.show', self::ALPHA))->assertOk();
        $this->actingAs($this->coordinator)->get(route('entiti.show', self::BETA))->assertOk();
    }

    public function test_pegawai_analisis_boleh_membuka_entiti_yang_ditugaskan(): void
    {
        $this->buatRekodLengkap();

        $this->actingAs($this->analystA)
            ->get(route('entiti.show', self::ALPHA))
            ->assertOk()
            ->assertSee('A010101');
    }

    public function test_pegawai_analisis_tidak_boleh_membuka_entiti_yang_tidak_ditugaskan(): void
    {
        $this->actingAs($this->analystA)
            ->get(route('entiti.show', self::BETA))
            ->assertForbidden();
    }

    public function test_capaian_json_ke_entiti_tidak_ditugaskan_ditolak(): void
    {
        $this->actingAs($this->analystA)
            ->getJson(route('entiti.show', self::BETA))
            ->assertForbidden();
    }

    public function test_akses_ditarik_selepas_penugasan_ditukar_ganti(): void
    {
        app(EntityAssignmentService::class)->reassign(
            SektorDirectory::cariEntiti(self::ALPHA),
            $this->analystB,
            $this->coordinator,
        );

        $this->actingAs($this->analystA)
            ->get(route('entiti.show', self::ALPHA))
            ->assertForbidden();

        $this->actingAs($this->analystB)
            ->get(route('entiti.show', self::ALPHA))
            ->assertOk();
    }

    /*
    |--------------------------------------------------------------------------
    | Tindakan hanya dipaparkan mengikut kebenaran peranan
    |--------------------------------------------------------------------------
    */

    public function test_pegawai_analisis_tidak_melihat_tindakan_penugasan(): void
    {
        $this->actingAs($this->analystA)
            ->get(route('entiti.show', self::ALPHA))
            ->assertOk()
            ->assertDontSee(route('penugasan.show', self::ALPHA));
    }

    public function test_penyelaras_melihat_tindakan_penugasan(): void
    {
        $this->actingAs($this->coordinator)
            ->get(route('entiti.show', self::ALPHA))
            ->assertOk()
            ->assertSee(route('penugasan.show', self::ALPHA));
    }

    public function test_penyelaras_tidak_melihat_tindakan_borang_analisis(): void
    {
        // Gate manage-analysis sedia ada: Pentadbir + Pegawai Analisis sahaja.
        $this->actingAs($this->coordinator)
            ->get(route('entiti.show', self::ALPHA))
            ->assertOk()
            ->assertDontSee('Borang Analisis');
    }

    public function test_pegawai_analisis_melihat_tindakan_borang_analisis(): void
    {
        $this->actingAs($this->analystA)
            ->get(route('entiti.show', self::ALPHA))
            ->assertOk()
            ->assertSee('Borang Analisis');
    }

    public function test_pautan_laporan_hanya_muncul_apabila_analisis_wujud(): void
    {
        $this->actingAs($this->coordinator)
            ->get(route('entiti.show', self::ALPHA))
            ->assertOk()
            ->assertDontSee('bi-file-earmark-text');

        $this->buatRekodLengkap();

        $analisis = AnalisisInventori::where('agency_code', self::ALPHA)->firstOrFail();

        $this->actingAs($this->coordinator)
            ->get(route('entiti.show', self::ALPHA))
            ->assertOk()
            ->assertSee(route('laporan.inventori', $analisis));
    }

    /*
    |--------------------------------------------------------------------------
    | Pautan masuk daripada senarai sedia ada
    |--------------------------------------------------------------------------
    */

    public function test_senarai_workflow_memaut_ke_pusat_maklumat_entiti(): void
    {
        $this->buatRekodLengkap();

        $this->actingAs($this->coordinator)
            ->get(route('workflow.index'))
            ->assertOk()
            ->assertSee(route('entiti.show', self::ALPHA));
    }

    public function test_pautan_entiti_tidak_ditugaskan_tidak_dipaparkan_kepada_pegawai_analisis(): void
    {
        $this->buatRekodLengkap(self::BETA);

        $this->actingAs($this->analystA)
            ->get(route('workflow.index'))
            ->assertOk()
            ->assertDontSee(route('entiti.show', self::BETA));
    }
}
