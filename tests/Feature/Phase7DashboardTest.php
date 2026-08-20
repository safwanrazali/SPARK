<?php

namespace Tests\Feature;

use App\Models\AnalisisInventori;
use App\Models\StatusLaporan;
use App\Models\User;
use App\Models\WorkflowStatus;
use App\Services\DashboardStatistikService;
use App\Services\EntityAssignmentService;
use App\Support\SektorDirectory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * FASA 7 — papan pemuka pemantauan pengurusan.
 *
 * Semua angka diuji terhadap rekod contoh yang diketahui, memastikan
 * statistik dikira daripada pangkalan data dan bukan nilai tetap.
 */
class Phase7DashboardTest extends TestCase
{
    use RefreshDatabase;

    /** Entiti sektor 001 (Kerajaan). */
    private const ALPHA = 'A010101';

    private const BETA = 'A010102';

    private const GAMMA = 'A010103';

    /** Entiti sektor 010 (Sains, Teknologi dan Inovasi). */
    private const DELTA = 'A100101';

    private User $admin;

    private User $coordinator;

    private User $analyst;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['role' => User::ROLE_ADMINISTRATOR]);
        $this->coordinator = User::factory()->create(['role' => User::ROLE_COORDINATOR]);
        $this->analyst = User::factory()->create(['role' => User::ROLE_ANALYST]);
    }

    /**
     * Cipta rekod workflow bagi satu entiti pada peringkat tertentu.
     */
    private function workflow(string $agencyCode, int $peringkat, ?string $tarikh = null): WorkflowStatus
    {
        return WorkflowStatus::factory()
            ->onStage($peringkat)
            ->create(SektorDirectory::cariEntiti($agencyCode) + [
                'updated_by_user_id' => $this->coordinator->id,
                'status_since' => $tarikh ? Carbon::parse($tarikh) : now(),
            ]);
    }

    /**
     * Entiti yang telah menamatkan kesemua peringkat — berbeza daripada
     * sekadar berada pada peringkat 7.
     */
    private function workflowSiap(string $agencyCode, ?string $tarikh = null): WorkflowStatus
    {
        return WorkflowStatus::factory()
            ->siap()
            ->create(SektorDirectory::cariEntiti($agencyCode) + [
                'updated_by_user_id' => $this->coordinator->id,
                'status_since' => $tarikh ? Carbon::parse($tarikh) : now(),
            ]);
    }

    private function statusLaporan(string $agencyCode, string $jenis, string $status): StatusLaporan
    {
        return StatusLaporan::create(SektorDirectory::cariEntiti($agencyCode) + [
            'jenis' => $jenis,
            'status' => $status,
            'user_id' => $this->coordinator->id,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function kira(?string $sektor = null, ?string $dari = null, ?string $hingga = null): array
    {
        return app(DashboardStatistikService::class)
            ->kira($this->coordinator, $sektor, $dari, $hingga);
    }

    /*
    |--------------------------------------------------------------------------
    | Kebenaran peranan
    |--------------------------------------------------------------------------
    */

    public function test_penyelaras_dan_pentadbir_boleh_melihat_papan_pemuka(): void
    {
        $this->actingAs($this->coordinator)->get(route('dashboard'))->assertOk();
        $this->actingAs($this->admin)->get(route('dashboard'))->assertOk();
    }

    public function test_pegawai_analisis_tidak_menerima_papan_pemuka(): void
    {
        $this->actingAs($this->analyst)
            ->get(route('dashboard'))
            ->assertRedirect(route('analisis.index'));
    }

    public function test_pautan_papan_pemuka_disembunyikan_daripada_pegawai_analisis(): void
    {
        app(EntityAssignmentService::class)->assign(
            SektorDirectory::cariEntiti(self::ALPHA),
            $this->analyst,
            $this->coordinator,
        );

        $this->actingAs($this->analyst)
            ->get(route('analisis.index'))
            ->assertOk()
            ->assertDontSee('Papan Pemuka');

        $this->actingAs($this->coordinator)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Papan Pemuka');
    }

    public function test_tetamu_tidak_boleh_melihat_papan_pemuka(): void
    {
        $this->get(route('dashboard'))->assertRedirect(route('login'));
    }

    /*
    |--------------------------------------------------------------------------
    | Kiraan terhadap rekod contoh yang diketahui
    |--------------------------------------------------------------------------
    */

    public function test_kiraan_entiti_dalam_proses_dan_selesai(): void
    {
        // 3 entiti: peringkat 2, peringkat 6, peringkat 7.
        $this->workflow(self::ALPHA, 2);
        $this->workflow(self::BETA, 6);
        $this->workflowSiap(self::GAMMA);

        $statistik = $this->kira();

        $this->assertSame(3, $statistik['jumlahEntiti']);
        $this->assertSame(2, $statistik['dalamProses']); // peringkat 1–6
        $this->assertSame(1, $statistik['selesai']);     // peringkat 7
        $this->assertSame(0, $statistik['belumDidaftar']);
    }

    public function test_entiti_tanpa_rekod_workflow_dikira_belum_didaftar(): void
    {
        $this->workflow(self::ALPHA, 3);

        // Entiti ini dipantau melalui status laporan sahaja.
        $this->statusLaporan(self::BETA, 'inventori', 'Dalam Proses');

        $statistik = $this->kira();

        $this->assertSame(2, $statistik['jumlahEntiti']);
        $this->assertSame(1, $statistik['dalamProses']);
        $this->assertSame(0, $statistik['selesai']);
        $this->assertSame(1, $statistik['belumDidaftar']);
    }

    public function test_taburan_workflow_merentas_tujuh_peringkat(): void
    {
        // Dua entiti pada peringkat 1, satu pada peringkat 4.
        $this->workflow(self::ALPHA, 1);
        $this->workflow(self::BETA, 1);
        $this->workflow(self::GAMMA, 4);

        $taburan = collect($this->kira()['taburanWorkflow'])->keyBy('peringkat');

        $this->assertCount(7, $taburan);

        $this->assertSame(2, $taburan[1]['bilangan']);
        $this->assertSame(67, $taburan[1]['peratus']); // 2/3
        $this->assertSame(1, $taburan[4]['bilangan']);
        $this->assertSame(33, $taburan[4]['peratus']); // 1/3
        $this->assertSame(0, $taburan[7]['bilangan']);

        // Nama peringkat mengikut spesifikasi.
        $this->assertSame('Penerimaan & Pendaftaran Data', $taburan[1]['nama']);
        $this->assertSame('Penyerahan & Penutupan', $taburan[7]['nama']);
    }

    public function test_kemajuan_keseluruhan_dikira_daripada_peringkat_dicapai(): void
    {
        // Peringkat 7 + 7 = 14 daripada maksimum 2 × 7 = 14 → 100%.
        $this->workflowSiap(self::ALPHA);
        $this->workflowSiap(self::BETA);

        $this->assertSame(100, $this->kira()['kemajuan']);
    }

    public function test_kemajuan_keseluruhan_separa(): void
    {
        // Peringkat 1 + 6 = 7 daripada 2 × 7 = 14 → 50%.
        $this->workflow(self::ALPHA, 1);
        $this->workflow(self::BETA, 6);

        $this->assertSame(50, $this->kira()['kemajuan']);
    }

    public function test_kiraan_laporan_mengikut_tiga_jenis_setiap_entiti(): void
    {
        $this->workflow(self::ALPHA, 3);
        $this->workflow(self::BETA, 3);

        $this->statusLaporan(self::ALPHA, 'inventori', 'Siap');
        $this->statusLaporan(self::ALPHA, 'risiko', 'Dalam Proses');
        $this->statusLaporan(self::BETA, 'inventori', 'Siap');

        $statistik = $this->kira();

        // 2 entiti × 3 jenis laporan = 6 rekod dijangka.
        $this->assertSame(6, $statistik['jumlahLaporan']);
        $this->assertSame(2, $statistik['laporanSiap']);
        $this->assertSame(1, $statistik['laporanDalamProses']);
        $this->assertSame(3, $statistik['laporanBelum']);
    }

    public function test_jumlah_sektor_dikira_daripada_senarai_induk(): void
    {
        $this->assertSame(count(config('sektor')), $this->kira()['jumlahSektor']);
    }

    public function test_analisis_selesai_dikira_daripada_rekod_sebenar(): void
    {
        $this->workflow(self::ALPHA, 4);
        $this->workflow(self::BETA, 4);

        AnalisisInventori::factory()->create(
            SektorDirectory::cariEntiti(self::ALPHA) + ['selesai' => true, 'user_id' => $this->analyst->id]
        );
        AnalisisInventori::factory()->create(
            SektorDirectory::cariEntiti(self::BETA) + ['selesai' => false, 'user_id' => $this->analyst->id]
        );

        $this->assertSame(1, $this->kira()['analisisSelesai']);
    }

    public function test_papan_pemuka_kosong_tidak_membahagi_dengan_sifar(): void
    {
        $statistik = $this->kira();

        $this->assertSame(0, $statistik['jumlahEntiti']);
        $this->assertSame(0, $statistik['kemajuan']);
        $this->assertSame(0, $statistik['jumlahLaporan']);
        $this->assertSame(0, collect($statistik['taburanWorkflow'])->sum('peratus'));
    }

    /*
    |--------------------------------------------------------------------------
    | Penapis
    |--------------------------------------------------------------------------
    */

    public function test_penapis_sektor_menghadkan_semua_kiraan(): void
    {
        $this->workflowSiap(self::ALPHA);   // sektor 001
        $this->workflow(self::BETA, 2);     // sektor 001
        $this->workflowSiap(self::DELTA);   // sektor 010

        $semua = $this->kira();
        $this->assertSame(3, $semua['jumlahEntiti']);
        $this->assertSame(2, $semua['selesai']);

        $sektor010 = $this->kira('010');
        $this->assertSame(1, $sektor010['jumlahEntiti']);
        $this->assertSame(1, $sektor010['selesai']);
        $this->assertSame(0, $sektor010['dalamProses']);
        $this->assertSame(1, $sektor010['jumlahSektor']);
        $this->assertSame('Sains, Teknologi dan Inovasi', $sektor010['penapis']['sector_name']);
    }

    public function test_penapis_sektor_tidak_sah_diabaikan(): void
    {
        $this->workflow(self::ALPHA, 3);

        $statistik = $this->kira('SEKTOR-TIDAK-WUJUD');

        $this->assertSame(1, $statistik['jumlahEntiti']);
        $this->assertNull($statistik['penapis']['sector_code']);
    }

    public function test_penapis_tarikh_menghadkan_entiti_mengikut_tarikh_status(): void
    {
        $this->workflow(self::ALPHA, 3, '2026-08-01 09:00:00');
        $this->workflow(self::BETA, 5, '2026-08-20 09:00:00');

        $julat = $this->kira(null, '2026-08-15', '2026-08-31');

        // Hanya entiti BETA (peringkat 5) berada dalam julat tarikh.
        $this->assertSame(1, $julat['jumlahEntiti']);
        $this->assertSame(1, collect($julat['taburanWorkflow'])->firstWhere('peringkat', 5)['bilangan']);
        $this->assertSame(0, collect($julat['taburanWorkflow'])->firstWhere('peringkat', 3)['bilangan']);
    }

    public function test_penapis_tarikh_meliputi_sempadan_hari_penuh(): void
    {
        $this->workflow(self::ALPHA, 2, '2026-08-15 23:30:00');

        $this->assertSame(1, $this->kira(null, '2026-08-15', '2026-08-15')['jumlahEntiti']);
    }

    public function test_julat_tarikh_terbalik_dibetulkan(): void
    {
        $this->workflow(self::ALPHA, 2, '2026-08-10 12:00:00');

        // Dari dan hingga ditukar tempat.
        $this->assertSame(1, $this->kira(null, '2026-08-20', '2026-08-01')['jumlahEntiti']);
    }

    public function test_penapis_sektor_dan_tarikh_boleh_digabungkan(): void
    {
        $this->workflow(self::ALPHA, 4, '2026-08-10 12:00:00');  // sektor 001, dalam julat
        $this->workflow(self::BETA, 4, '2026-09-10 12:00:00');   // sektor 001, luar julat
        $this->workflow(self::DELTA, 4, '2026-08-11 12:00:00');  // sektor 010, dalam julat

        $statistik = $this->kira('001', '2026-08-01', '2026-08-31');

        $this->assertSame(1, $statistik['jumlahEntiti']);
        $this->assertTrue($statistik['penapis']['aktif']);
    }

    /*
    |--------------------------------------------------------------------------
    | Paparan
    |--------------------------------------------------------------------------
    */

    public function test_papan_pemuka_memaparkan_kesemua_konsep_yang_diperlukan(): void
    {
        $this->workflow(self::ALPHA, 2);
        $this->workflow(self::BETA, 7);

        $response = $this->actingAs($this->coordinator)->get(route('dashboard'))->assertOk();

        $response->assertSee('Jumlah Sektor')
            ->assertSee('Jumlah Entiti')
            ->assertSee('Dalam Proses')
            ->assertSee('Entiti Selesai')
            ->assertSee('Jumlah Laporan')
            ->assertSee('Laporan Siap')
            ->assertSee('Kemajuan Keseluruhan')
            ->assertSee('Taburan Workflow 7 Peringkat');

        // Ketujuh-tujuh peringkat disenaraikan.
        foreach (WorkflowStatus::WORKFLOW_STAGES as $nama) {
            $response->assertSee($nama);
        }
    }

    public function test_papan_pemuka_memaparkan_penapis(): void
    {
        $this->actingAs($this->coordinator)
            ->get(route('dashboard', ['sector_code' => '010']))
            ->assertOk()
            ->assertSee('Penapis aktif')
            ->assertSee('010')
            ->assertViewHas('jumlahSektor', 1);
    }

    public function test_papan_pemuka_memaparkan_nilai_dikira_bukan_nilai_tetap(): void
    {
        $this->workflowSiap(self::ALPHA);
        $this->workflowSiap(self::BETA);

        $this->actingAs($this->coordinator)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertViewHas('jumlahEntiti', 2)
            ->assertViewHas('selesai', 2)
            ->assertViewHas('kemajuan', 100);

        // Rekod ketiga pada peringkat awal menurunkan kemajuan secara automatik.
        $this->workflow(self::GAMMA, 0 + 1);

        $this->actingAs($this->coordinator)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertViewHas('jumlahEntiti', 3)
            ->assertViewHas('kemajuan', 71); // (7+7+1)/21
    }

    public function test_aktiviti_terkini_dipaparkan_daripada_log(): void
    {
        app(EntityAssignmentService::class)->assign(
            SektorDirectory::cariEntiti(self::ALPHA),
            $this->analyst,
            $this->coordinator,
        );

        $this->actingAs($this->coordinator)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Aktiviti Terkini')
            ->assertSee('Penugasan Dibuat');
    }
}
