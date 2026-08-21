<?php

namespace Tests\Feature;

use App\Models\AnalisDraftHistory;
use App\Models\AnalisisInventori;
use App\Models\ApprovalLog;
use App\Models\LaporanSemakan;
use App\Models\User;
use App\Models\WorkflowStageStatus;
use App\Models\WorkflowStatus;
use App\Services\EntityAssignmentService;
use App\Services\KemajuanAnalisisService;
use App\Services\LaporanSemakanService;
use App\Support\SektorDirectory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Carta aliran Kemajuan Analisis Entiti — Senario C hingga L.
 *
 * C  PA menyelesaikan setiap peringkat mengikut turutan
 * D  Draf tidak menandakan analisis selesai; borang kekal boleh disunting
 * E  PA melengkapkan analisis lalu menghantar laporan kepada PPA
 * F  PPA mengembalikan tanpa Catatan — dihalang
 * G  PPA mengembalikan dengan Catatan — laporan kembali kepada PA
 * H  PPA menghantar — laporan sampai kepada KB
 * I  KB mengesahkan — Jana Laporan dan Semakan & Kelulusan menjadi Selesai
 * J  KB mengembalikan tanpa Catatan — dihalang
 * K  Penyerahan akhir — Penyerahan & Penutupan Selesai, keseluruhan Siap
 * L  Entiti berperingkat tidak lengkap tidak pernah menjadi Siap
 */
class KemajuanAnalisisAliranTest extends TestCase
{
    use RefreshDatabase;

    private const SEKTOR = '001';

    private const ALPHA = 'A010101';

    private User $ppr;

    private User $ppa;

    private User $pa;

    private User $kb;

    protected function setUp(): void
    {
        parent::setUp();

        $this->ppr = User::factory()->create(['role' => User::ROLE_PENYELARAS_REKOD, 'name' => 'Rekod Satu']);
        $this->ppa = User::factory()->create(['role' => User::ROLE_COORDINATOR, 'name' => 'Penyelaras Satu']);
        $this->pa = User::factory()->create(['role' => User::ROLE_ANALYST, 'name' => 'Pegawai A']);
        $this->kb = User::factory()->create(['role' => User::ROLE_KETUA_BAHAGIAN, 'name' => 'Ketua Satu']);
    }

    /**
     * Entiti didaftarkan (peringkat 1) dan ditugaskan kepada PA.
     */
    private function sediakanEntiti(): void
    {
        app(KemajuanAnalisisService::class)->lengkapkanPendaftaran(
            SektorDirectory::cariEntiti(self::ALPHA),
            $this->ppr,
        );

        app(EntityAssignmentService::class)->assign(
            SektorDirectory::cariEntiti(self::ALPHA),
            $this->pa,
            $this->ppa,
        );
    }

    private function statusPeringkat(int $stage): string
    {
        return app(KemajuanAnalisisService::class)
            ->peringkat(self::ALPHA)
            ->get($stage)?->status ?? 'tiada';
    }

    private function keseluruhan(): string
    {
        return app(KemajuanAnalisisService::class)->keseluruhan(self::ALPHA);
    }

    /**
     * Muatan minimum bagi "Simpan Dapatan".
     *
     * @return array<string, mixed>
     */
    private function dapatan(array $ubah = []): array
    {
        return array_replace([
            'sector_code' => self::SEKTOR,
            'agency_code' => self::ALPHA,
            'tarikh_laporan' => '2026-08-20',
            'kod_rujukan' => 'PTPKM/INV/2026/007',
            'status_laporan' => 'Muktamad',
            'ringkasan_data' => 'lengkap',
            'selesai' => '1',
        ], $ubah);
    }

    /**
     * Bawa entiti sehingga laporan berada di tangan PPA.
     */
    private function sehinggaLaporanDihantar(): void
    {
        $this->sediakanEntiti();

        $this->actingAs($this->pa->fresh());

        $this->post(route('kemajuan.selesai', [self::ALPHA, WorkflowStatus::STAGE_SEMAKAN_AWAL]));
        $this->post(route('kemajuan.selesai', [self::ALPHA, WorkflowStatus::STAGE_PENYEDIAAN]));
        $this->post(route('kemajuan.selesai', [self::ALPHA, WorkflowStatus::STAGE_ANALISIS]));
        $this->post(route('analisis.simpan'), $this->dapatan());
        $this->post(route('kemajuan.hantar', self::ALPHA));
    }

    /*
    |--------------------------------------------------------------------------
    | Paparan — satu kad sahaja membawa kedudukan dan tindakan
    |--------------------------------------------------------------------------
    */

    public function test_bar_tindakan_memaparkan_tindakan_peringkat_semasa(): void
    {
        $this->sediakanEntiti();

        $this->actingAs($this->pa->fresh())
            ->get(route('workflow.show', self::ALPHA))
            ->assertOk()
            ->assertSee('Peringkat Kemajuan')
            ->assertSee('peringkat-tindakan', false)
            ->assertSee(route('kemajuan.selesai', [self::ALPHA, WorkflowStatus::STAGE_SEMAKAN_AWAL]), false);
    }

    /**
     * Peringkat 05 hanya menjadi Selesai setelah KB mengesahkan, jadi 06
     * berjalan serentak dengannya. Bar tindakan mesti memaparkan tindakan
     * penyemak walaupun peringkat SEMASA masih 05 — inilah sebabnya ia tidak
     * boleh diringkaskan kepada satu peringkat sahaja.
     */
    public function test_bar_tindakan_memaparkan_peringkat_serentak_kepada_penyemak(): void
    {
        $this->sehinggaLaporanDihantar();

        $this->assertSame(
            WorkflowStatus::STAGE_JANA_LAPORAN,
            app(KemajuanAnalisisService::class)->peringkatSemasa(
                app(KemajuanAnalisisService::class)->peringkat(self::ALPHA)
            ),
        );

        $this->actingAs($this->ppa->fresh())
            ->get(route('workflow.show', self::ALPHA))
            ->assertOk()
            ->assertSee(route('kemajuan.semak', self::ALPHA), false)
            ->assertSee(route('kemajuan.kembalikan', self::ALPHA), false);
    }

    /**
     * Butang yang dipaparkan mesti sepadan dengan giliran sebenar
     * (aliran kerja bahagian 19).
     */
    public function test_butang_pa_mengikut_kedudukan_laporan(): void
    {
        $this->sediakanEntiti();

        $this->actingAs($this->pa->fresh());
        $this->post(route('kemajuan.selesai', [self::ALPHA, WorkflowStatus::STAGE_SEMAKAN_AWAL]));
        $this->post(route('kemajuan.selesai', [self::ALPHA, WorkflowStatus::STAGE_PENYEDIAAN]));
        $this->post(route('kemajuan.selesai', [self::ALPHA, WorkflowStatus::STAGE_ANALISIS]));

        // Borang belum lengkap — hanya "Lengkapkan Borang".
        $this->get(route('workflow.show', self::ALPHA))
            ->assertOk()
            ->assertSee('Lengkapkan Borang')
            ->assertDontSee('Hantar kepada PPA');

        $this->post(route('analisis.simpan'), $this->dapatan());

        // Borang lengkap — penghantaran terbuka.
        $this->get(route('workflow.show', self::ALPHA))
            ->assertOk()
            ->assertSee('Hantar kepada PPA')
            ->assertDontSee('Lengkapkan Borang');

        $this->post(route('kemajuan.hantar', self::ALPHA));

        // Sedang disemak — PA tiada butang sunting atau hantar.
        $this->get(route('workflow.show', self::ALPHA))
            ->assertOk()
            ->assertDontSee('Hantar kepada PPA')
            ->assertDontSee('Lengkapkan Borang')
            ->assertDontSee('Hantar Semula');

        $this->actingAs($this->ppa)->post(route('kemajuan.kembalikan', self::ALPHA), [
            'catatan' => 'Jadual 3 perlu disemak semula.',
        ]);

        // Dikembalikan — "Betulkan" dan "Hantar Semula".
        $this->actingAs($this->pa->fresh())
            ->get(route('workflow.show', self::ALPHA))
            ->assertOk()
            ->assertSee('Betulkan')
            ->assertSee('Hantar Semula')
            ->assertSee('Jadual 3 perlu disemak semula.');
    }

    public function test_butang_kb_dipaparkan_hanya_pada_gilirannya(): void
    {
        $this->sehinggaLaporanDihantar();

        // Laporan masih di tangan PPA — KB belum boleh mengesahkan.
        $this->actingAs($this->kb)
            ->get(route('workflow.show', self::ALPHA))
            ->assertOk()
            ->assertDontSee('Sahkan');

        $this->actingAs($this->ppa)->post(route('kemajuan.semak', self::ALPHA));

        $this->actingAs($this->kb)
            ->get(route('workflow.show', self::ALPHA))
            ->assertOk()
            ->assertSee('Sahkan')
            ->assertSee('Kembalikan');

        $this->actingAs($this->kb)->post(route('kemajuan.sahkan', self::ALPHA));

        // Selepas pengesahan: tiada butang semakan, dan penyerahan terbuka.
        $this->actingAs($this->kb)
            ->get(route('workflow.show', self::ALPHA))
            ->assertOk()
            ->assertSee(LaporanSemakan::PAPARAN_DISAHKAN)
            ->assertSee(route('kemajuan.serah', self::ALPHA), false);
    }

    public function test_bar_tindakan_tidak_wujud_apabila_tiada_tindakan(): void
    {
        $this->sediakanEntiti();

        // PPR tiada tindakan pada mana-mana peringkat selepas pendaftaran.
        $this->actingAs($this->ppr->fresh())
            ->get(route('workflow.show', self::ALPHA))
            ->assertOk()
            ->assertSee('Peringkat Kemajuan')
            ->assertDontSee('peringkat-tindakan', false);
    }

    /*
    |--------------------------------------------------------------------------
    | Sejarah Peringkat — mesti sampai ke kedudukan semasa
    |--------------------------------------------------------------------------
    */

    /**
     * Sejarah dahulunya ditapis dengan perbendaharaan workflow lama sahaja,
     * jadi hanya baris "Entiti Didaftarkan Dalam Workflow" muncul walaupun
     * entiti telah bergerak ke peringkat 04.
     */
    public function test_sejarah_peringkat_merangkumi_setiap_peringkat_yang_selesai(): void
    {
        $this->sediakanEntiti();

        $this->actingAs($this->pa->fresh());
        $this->post(route('kemajuan.selesai', [self::ALPHA, WorkflowStatus::STAGE_SEMAKAN_AWAL]));
        $this->post(route('kemajuan.selesai', [self::ALPHA, WorkflowStatus::STAGE_PENYEDIAAN]));

        $sejarah = app(KemajuanAnalisisService::class)->sejarah(self::ALPHA);

        $peringkatDirekod = $sejarah
            ->pluck('metadata.stage')
            ->filter()
            ->unique()
            ->sort()
            ->values()
            ->all();

        $this->assertSame(
            [
                WorkflowStatus::STAGE_PENDAFTARAN,
                WorkflowStatus::STAGE_SEMAKAN_AWAL,
                WorkflowStatus::STAGE_PENYEDIAAN,
            ],
            $peringkatDirekod,
        );

        $this->get(route('workflow.show', self::ALPHA))
            ->assertOk()
            ->assertSee('Status Peringkat Analisis Berubah')
            ->assertSee(WorkflowStatus::getStageName(WorkflowStatus::STAGE_SEMAKAN_AWAL))
            ->assertSee(WorkflowStatus::getStageName(WorkflowStatus::STAGE_PENYEDIAAN));
    }

    /**
     * Peringkat 05 hingga 07 digerakkan oleh kitaran laporan, jadi tindakan
     * report_* mesti turut muncul dalam sejarah peringkat.
     */
    public function test_sejarah_peringkat_merangkumi_kitaran_laporan(): void
    {
        $this->sehinggaLaporanDihantar();

        $tindakan = app(KemajuanAnalisisService::class)
            ->sejarah(self::ALPHA)
            ->pluck('action')
            ->all();

        $this->assertContains('report_submitted', $tindakan);

        $this->actingAs($this->ppa->fresh())
            ->get(route('workflow.show', self::ALPHA))
            ->assertOk()
            ->assertSee('Laporan Diserahkan untuk Semakan');
    }

    /*
    |--------------------------------------------------------------------------
    | Senario C — peringkat diselesaikan mengikut turutan
    |--------------------------------------------------------------------------
    */

    public function test_pa_menyelesaikan_setiap_peringkat_mengikut_turutan(): void
    {
        $this->sediakanEntiti();

        $this->assertSame(WorkflowStageStatus::SELESAI, $this->statusPeringkat(WorkflowStatus::STAGE_PENDAFTARAN));
        $this->assertSame(WorkflowStageStatus::BELUM_MULA, $this->statusPeringkat(WorkflowStatus::STAGE_SEMAKAN_AWAL));

        $this->actingAs($this->pa->fresh())
            ->post(route('kemajuan.selesai', [self::ALPHA, WorkflowStatus::STAGE_SEMAKAN_AWAL]))
            ->assertSessionHasNoErrors();

        $this->assertSame(WorkflowStageStatus::SELESAI, $this->statusPeringkat(WorkflowStatus::STAGE_SEMAKAN_AWAL));

        $this->actingAs($this->pa->fresh())
            ->post(route('kemajuan.selesai', [self::ALPHA, WorkflowStatus::STAGE_PENYEDIAAN]))
            ->assertSessionHasNoErrors();

        $this->assertSame(WorkflowStageStatus::SELESAI, $this->statusPeringkat(WorkflowStatus::STAGE_PENYEDIAAN));
    }

    public function test_peringkat_tidak_boleh_dilangkau(): void
    {
        $this->sediakanEntiti();

        // Peringkat 3 sebelum peringkat 2 Selesai.
        $this->actingAs($this->pa->fresh())
            ->post(route('kemajuan.selesai', [self::ALPHA, WorkflowStatus::STAGE_PENYEDIAAN]))
            ->assertSessionHasErrors('stage');

        $this->assertSame(WorkflowStageStatus::BELUM_MULA, $this->statusPeringkat(WorkflowStatus::STAGE_PENYEDIAAN));
    }

    public function test_pa_lain_tidak_boleh_menyentuh_entiti_yang_bukan_miliknya(): void
    {
        $this->sediakanEntiti();

        $paLain = User::factory()->create(['role' => User::ROLE_ANALYST, 'name' => 'Pegawai B']);

        $this->actingAs($paLain)
            ->post(route('kemajuan.selesai', [self::ALPHA, WorkflowStatus::STAGE_SEMAKAN_AWAL]))
            ->assertForbidden();

        $this->assertSame(WorkflowStageStatus::BELUM_MULA, $this->statusPeringkat(WorkflowStatus::STAGE_SEMAKAN_AWAL));
    }

    /*
    |--------------------------------------------------------------------------
    | Senario D — draf tidak menandakan analisis selesai
    |--------------------------------------------------------------------------
    */

    /**
     * Borang input kini milik peringkat 05, bukan 04. Membukanya tidak lagi
     * menggerakkan mana-mana peringkat — hanya butang "Selesai" dan
     * penghantaran laporan boleh berbuat demikian.
     */
    public function test_membuka_borang_analisis_tidak_mengubah_status_peringkat(): void
    {
        $this->sediakanEntiti();

        $this->actingAs($this->pa->fresh());
        $this->post(route('kemajuan.selesai', [self::ALPHA, WorkflowStatus::STAGE_SEMAKAN_AWAL]));
        $this->post(route('kemajuan.selesai', [self::ALPHA, WorkflowStatus::STAGE_PENYEDIAAN]));
        $this->post(route('kemajuan.selesai', [self::ALPHA, WorkflowStatus::STAGE_ANALISIS]));

        $this->assertSame(WorkflowStageStatus::BELUM_MULA, $this->statusPeringkat(WorkflowStatus::STAGE_JANA_LAPORAN));

        $this->get(route('analisis.borang', [
            'sector_code' => self::SEKTOR,
            'agency_code' => self::ALPHA,
        ]))->assertOk();

        $this->assertSame(WorkflowStageStatus::BELUM_MULA, $this->statusPeringkat(WorkflowStatus::STAGE_JANA_LAPORAN));
    }

    public function test_simpan_draf_tidak_melengkapkan_analisis_dan_borang_kekal_boleh_disunting(): void
    {
        $this->sediakanEntiti();

        $this->actingAs($this->pa->fresh());
        $this->post(route('kemajuan.selesai', [self::ALPHA, WorkflowStatus::STAGE_SEMAKAN_AWAL]));
        $this->post(route('kemajuan.selesai', [self::ALPHA, WorkflowStatus::STAGE_PENYEDIAAN]));

        $this->post(route('analisis.draf'), [
            'sector_code' => self::SEKTOR,
            'agency_code' => self::ALPHA,
            'seksyen' => 'maklumat',
            'kod_rujukan' => 'DRAF-SEPARA',
        ])->assertRedirect();

        $analisis = AnalisisInventori::where('agency_code', self::ALPHA)->firstOrFail();

        // Draf tidak menandakan dapatan sebagai Lengkap.
        $this->assertFalse((bool) $analisis->selesai);
        $this->assertTrue(
            AnalisDraftHistory::where('analisis_inventori_id', $analisis->id)->where('is_current', true)->exists(),
        );

        // Borang masih boleh disunting dan draf dipulihkan.
        $this->get(route('analisis.borang', [
            'sector_code' => self::SEKTOR,
            'agency_code' => self::ALPHA,
        ]))->assertOk()->assertSee('DRAF-SEPARA', false);

        // Peringkat 04 ialah pengesahan PA semata-mata, jadi ia ditutup
        // walaupun borang masih draf.
        $this->post(route('kemajuan.selesai', [self::ALPHA, WorkflowStatus::STAGE_ANALISIS]))
            ->assertSessionHasNoErrors();

        $this->assertSame(WorkflowStageStatus::SELESAI, $this->statusPeringkat(WorkflowStatus::STAGE_ANALISIS));

        // Laporan pula TIDAK boleh dihantar dengan draf sahaja.
        $this->post(route('kemajuan.hantar', self::ALPHA))
            ->assertSessionHasErrors('laporan');

        $this->assertDatabaseCount('laporan_semakan', 0);
    }

    /*
    |--------------------------------------------------------------------------
    | Senario E — analisis lengkap → laporan dihantar kepada PPA
    |--------------------------------------------------------------------------
    */

    public function test_pa_melengkapkan_analisis_lalu_menghantar_laporan(): void
    {
        $this->sehinggaLaporanDihantar();

        $this->assertSame(WorkflowStageStatus::SELESAI, $this->statusPeringkat(WorkflowStatus::STAGE_ANALISIS));

        // Jana Laporan BUKAN Selesai — ia menunggu kelulusan KB.
        $this->assertSame(WorkflowStageStatus::DALAM_PROSES, $this->statusPeringkat(WorkflowStatus::STAGE_JANA_LAPORAN));
        $this->assertSame(WorkflowStageStatus::DALAM_PROSES, $this->statusPeringkat(WorkflowStatus::STAGE_SEMAKAN_KELULUSAN));

        $this->assertDatabaseHas('laporan_semakan', [
            'agency_code' => self::ALPHA,
            'status' => LaporanSemakan::MENUNGGU_PPA,
        ]);
    }

    public function test_laporan_tidak_boleh_dihantar_sebelum_borang_lengkap(): void
    {
        $this->sediakanEntiti();

        $this->actingAs($this->pa->fresh());
        $this->post(route('kemajuan.selesai', [self::ALPHA, WorkflowStatus::STAGE_SEMAKAN_AWAL]));
        $this->post(route('kemajuan.selesai', [self::ALPHA, WorkflowStatus::STAGE_PENYEDIAAN]));
        $this->post(route('kemajuan.selesai', [self::ALPHA, WorkflowStatus::STAGE_ANALISIS]));

        $this->post(route('kemajuan.hantar', self::ALPHA))
            ->assertSessionHasErrors('laporan');

        $this->assertDatabaseCount('laporan_semakan', 0);
        $this->assertSame(WorkflowStageStatus::BELUM_MULA, $this->statusPeringkat(WorkflowStatus::STAGE_JANA_LAPORAN));
    }

    public function test_laporan_tidak_boleh_dihantar_sebelum_analisis_data_selesai(): void
    {
        $this->sediakanEntiti();

        $this->actingAs($this->pa->fresh());
        $this->post(route('kemajuan.selesai', [self::ALPHA, WorkflowStatus::STAGE_SEMAKAN_AWAL]));
        $this->post(route('kemajuan.selesai', [self::ALPHA, WorkflowStatus::STAGE_PENYEDIAAN]));

        // Borang lengkap, tetapi peringkat 04 belum ditandakan Selesai.
        $this->post(route('analisis.simpan'), $this->dapatan());

        $this->post(route('kemajuan.hantar', self::ALPHA))
            ->assertSessionHasErrors('laporan');

        $this->assertDatabaseCount('laporan_semakan', 0);
    }

    /*
    |--------------------------------------------------------------------------
    | Senario F & G — PPA mengembalikan laporan
    |--------------------------------------------------------------------------
    */

    public function test_ppa_tidak_boleh_mengembalikan_laporan_tanpa_catatan(): void
    {
        $this->sehinggaLaporanDihantar();

        $this->actingAs($this->ppa)
            ->post(route('kemajuan.kembalikan', self::ALPHA), ['catatan' => ''])
            ->assertSessionHasErrors('catatan');

        $this->assertDatabaseHas('laporan_semakan', [
            'agency_code' => self::ALPHA,
            'status' => LaporanSemakan::MENUNGGU_PPA,
        ]);
    }

    public function test_ppa_mengembalikan_laporan_dengan_catatan(): void
    {
        $this->sehinggaLaporanDihantar();

        $this->actingAs($this->ppa)
            ->post(route('kemajuan.kembalikan', self::ALPHA), [
                'catatan' => 'Jadual 3 perlu disemak semula.',
            ])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('laporan_semakan', [
            'agency_code' => self::ALPHA,
            'status' => LaporanSemakan::DIKEMBALIKAN,
            'catatan' => 'Jadual 3 perlu disemak semula.',
        ]);

        // Laporan kembali kepada PA: peringkat Jana Laporan dibuka semula.
        $this->assertSame(WorkflowStageStatus::DALAM_PROSES, $this->statusPeringkat(WorkflowStatus::STAGE_JANA_LAPORAN));

        // PA boleh menghantar semula.
        $this->actingAs($this->pa->fresh())
            ->post(route('kemajuan.hantar', self::ALPHA))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('laporan_semakan', [
            'agency_code' => self::ALPHA,
            'status' => LaporanSemakan::MENUNGGU_PPA,
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Senario H & I — PPA menghantar, KB mengesahkan
    |--------------------------------------------------------------------------
    */

    public function test_ppa_menghantar_laporan_kepada_kb(): void
    {
        $this->sehinggaLaporanDihantar();

        $this->actingAs($this->ppa)
            ->post(route('kemajuan.semak', self::ALPHA))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('laporan_semakan', [
            'agency_code' => self::ALPHA,
            'status' => LaporanSemakan::MENUNGGU_KB,
            'disemak_oleh_user_id' => $this->ppa->id,
        ]);
    }

    public function test_kb_mengesahkan_menjadikan_jana_laporan_dan_semakan_selesai(): void
    {
        $this->sehinggaLaporanDihantar();

        $this->actingAs($this->ppa)->post(route('kemajuan.semak', self::ALPHA));

        $this->actingAs($this->kb)
            ->post(route('kemajuan.sahkan', self::ALPHA))
            ->assertSessionHasNoErrors();

        $this->assertSame(WorkflowStageStatus::SELESAI, $this->statusPeringkat(WorkflowStatus::STAGE_JANA_LAPORAN));
        $this->assertSame(WorkflowStageStatus::SELESAI, $this->statusPeringkat(WorkflowStatus::STAGE_SEMAKAN_KELULUSAN));

        $this->assertDatabaseHas('laporan_semakan', [
            'agency_code' => self::ALPHA,
            'status' => LaporanSemakan::SAH,
            'disahkan_oleh_user_id' => $this->kb->id,
        ]);

        // Setiap tindakan semakan meninggalkan jejak dalam approval_logs.
        $this->assertSame(3, ApprovalLog::where('agency_code', self::ALPHA)->count());
    }

    /*
    |--------------------------------------------------------------------------
    | Senario J — KB mengembalikan tanpa Catatan
    |--------------------------------------------------------------------------
    */

    public function test_kb_tidak_boleh_mengembalikan_laporan_tanpa_catatan(): void
    {
        $this->sehinggaLaporanDihantar();
        $this->actingAs($this->ppa)->post(route('kemajuan.semak', self::ALPHA));

        $this->actingAs($this->kb)
            ->post(route('kemajuan.kembalikan', self::ALPHA), ['catatan' => '   '])
            ->assertSessionHasErrors('catatan');

        $this->assertDatabaseHas('laporan_semakan', [
            'agency_code' => self::ALPHA,
            'status' => LaporanSemakan::MENUNGGU_KB,
        ]);
    }

    public function test_kb_mengembalikan_laporan_dengan_catatan(): void
    {
        $this->sehinggaLaporanDihantar();
        $this->actingAs($this->ppa)->post(route('kemajuan.semak', self::ALPHA));

        $this->actingAs($this->kb)
            ->post(route('kemajuan.kembalikan', self::ALPHA), ['catatan' => 'Kesimpulan perlu diperincikan.'])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('laporan_semakan', [
            'agency_code' => self::ALPHA,
            'status' => LaporanSemakan::DIKEMBALIKAN,
        ]);

        $this->assertNotSame(WorkflowStageStatus::SELESAI, $this->statusPeringkat(WorkflowStatus::STAGE_JANA_LAPORAN));
    }

    /*
    |--------------------------------------------------------------------------
    | Senario K & L — penyerahan akhir dan jaminan tidak siap lebih awal
    |--------------------------------------------------------------------------
    */

    public function test_penyerahan_akhir_menjadikan_keseluruhan_siap(): void
    {
        $this->sehinggaLaporanDihantar();
        $this->actingAs($this->ppa)->post(route('kemajuan.semak', self::ALPHA));
        $this->actingAs($this->kb)->post(route('kemajuan.sahkan', self::ALPHA));

        // Belum diserahkan — belum Siap.
        $this->assertSame(KemajuanAnalisisService::KESELURUHAN_DALAM_PROSES, $this->keseluruhan());

        $this->actingAs($this->kb)
            ->post(route('kemajuan.serah', self::ALPHA))
            ->assertSessionHasNoErrors();

        $this->assertSame(WorkflowStageStatus::SELESAI, $this->statusPeringkat(WorkflowStatus::STAGE_PENYERAHAN));
        $this->assertSame(KemajuanAnalisisService::KESELURUHAN_SIAP, $this->keseluruhan());

        // Kedudukan yang dibaca modul sedia ada turut diselaraskan.
        $this->assertDatabaseHas('workflow_status', [
            'agency_code' => self::ALPHA,
            'current_stage' => WorkflowStatus::LAST_STAGE,
            'status' => 'Siap',
        ]);
    }

    public function test_penyerahan_ditolak_sebelum_laporan_disahkan(): void
    {
        $this->sehinggaLaporanDihantar();

        $this->actingAs($this->kb)
            ->post(route('kemajuan.serah', self::ALPHA))
            ->assertSessionHasErrors('stage');

        $this->assertNotSame(KemajuanAnalisisService::KESELURUHAN_SIAP, $this->keseluruhan());
    }

    public function test_pa_tidak_boleh_menyerahkan_kepada_nacsa(): void
    {
        $this->sehinggaLaporanDihantar();
        $this->actingAs($this->ppa)->post(route('kemajuan.semak', self::ALPHA));
        $this->actingAs($this->kb)->post(route('kemajuan.sahkan', self::ALPHA));

        $this->actingAs($this->pa->fresh())
            ->post(route('kemajuan.serah', self::ALPHA))
            ->assertForbidden();

        $this->assertNotSame(KemajuanAnalisisService::KESELURUHAN_SIAP, $this->keseluruhan());
    }

    public function test_entiti_berperingkat_tidak_lengkap_tidak_pernah_siap(): void
    {
        $this->sehinggaLaporanDihantar();

        // Enam daripada tujuh peringkat sudah bergerak, tetapi tiada satu pun
        // gabungan separa boleh menghasilkan 'Siap'.
        foreach ([
            WorkflowStatus::STAGE_JANA_LAPORAN,
            WorkflowStatus::STAGE_SEMAKAN_KELULUSAN,
            WorkflowStatus::STAGE_PENYERAHAN,
        ] as $stage) {
            $this->assertNotSame(WorkflowStageStatus::SELESAI, $this->statusPeringkat($stage));
        }

        $this->assertSame(KemajuanAnalisisService::KESELURUHAN_DALAM_PROSES, $this->keseluruhan());
    }

    /*
    |--------------------------------------------------------------------------
    | Muat turun — hanya laporan berstatus Sah
    |--------------------------------------------------------------------------
    */

    public function test_muat_turun_ditolak_sehingga_laporan_disahkan(): void
    {
        $this->sehinggaLaporanDihantar();

        $analisis = AnalisisInventori::where('agency_code', self::ALPHA)->firstOrFail();

        $this->actingAs($this->ppa)
            ->get(route('laporan.unduh', $analisis))
            ->assertForbidden();
    }

    /*
    |--------------------------------------------------------------------------
    | Kunci sunting — laporan yang sedang disemak tidak boleh diubah PA
    |--------------------------------------------------------------------------
    */

    public function test_pa_tidak_boleh_menyunting_borang_semasa_laporan_disemak(): void
    {
        $this->sehinggaLaporanDihantar();

        $this->actingAs($this->pa->fresh());

        // Borang dialihkan kembali ke halaman kemajuan, bukan dibuka.
        $this->get(route('analisis.borang', [
            'sector_code' => self::SEKTOR,
            'agency_code' => self::ALPHA,
        ]))->assertRedirect(route('workflow.show', self::ALPHA));

        $this->post(route('analisis.draf'), [
            'sector_code' => self::SEKTOR,
            'agency_code' => self::ALPHA,
            'kod_rujukan' => 'CUBA-UBAH',
        ])->assertSessionHasErrors('agency_code');

        $this->post(route('analisis.simpan'), $this->dapatan(['kod_rujukan' => 'CUBA-UBAH']))
            ->assertSessionHasErrors('agency_code');

        $this->assertDatabaseMissing('analisis_inventori', [
            'agency_code' => self::ALPHA,
            'kod_rujukan' => 'CUBA-UBAH',
        ]);
    }

    public function test_pa_boleh_menyunting_semula_laporan_yang_dikembalikan(): void
    {
        $this->sehinggaLaporanDihantar();

        $this->actingAs($this->ppa)->post(route('kemajuan.kembalikan', self::ALPHA), [
            'catatan' => 'Jadual 3 perlu disemak semula.',
        ]);

        $this->actingAs($this->pa->fresh())
            ->get(route('analisis.borang', [
                'sector_code' => self::SEKTOR,
                'agency_code' => self::ALPHA,
            ]))
            ->assertOk();

        $this->post(route('analisis.simpan'), $this->dapatan(['kod_rujukan' => 'PTPKM/INV/2026/007-A']))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('analisis_inventori', [
            'agency_code' => self::ALPHA,
            'kod_rujukan' => 'PTPKM/INV/2026/007-A',
        ]);
    }

    public function test_pa_tidak_boleh_menyunting_laporan_yang_telah_disahkan(): void
    {
        $this->sehinggaLaporanDihantar();
        $this->actingAs($this->ppa)->post(route('kemajuan.semak', self::ALPHA));
        $this->actingAs($this->kb)->post(route('kemajuan.sahkan', self::ALPHA));

        $this->actingAs($this->pa->fresh())
            ->post(route('analisis.simpan'), $this->dapatan(['kod_rujukan' => 'SELEPAS-SAH']))
            ->assertSessionHasErrors('agency_code');
    }

    /*
    |--------------------------------------------------------------------------
    | Peraturan status — 05 dan 06 hanya Selesai melalui kelulusan KB
    |--------------------------------------------------------------------------
    */

    public function test_pa_tidak_boleh_menandakan_jana_laporan_selesai_secara_manual(): void
    {
        $this->sehinggaLaporanDihantar();

        $this->actingAs($this->pa->fresh())
            ->post(route('kemajuan.selesai', [self::ALPHA, WorkflowStatus::STAGE_JANA_LAPORAN]))
            ->assertNotFound();

        $this->assertSame(WorkflowStageStatus::DALAM_PROSES, $this->statusPeringkat(WorkflowStatus::STAGE_JANA_LAPORAN));
    }

    public function test_ppa_tidak_boleh_menandakan_semakan_kelulusan_selesai_secara_manual(): void
    {
        $this->sehinggaLaporanDihantar();

        $this->actingAs($this->ppa)
            ->post(route('kemajuan.selesai', [self::ALPHA, WorkflowStatus::STAGE_SEMAKAN_KELULUSAN]))
            ->assertForbidden();

        $this->assertSame(
            WorkflowStageStatus::DALAM_PROSES,
            $this->statusPeringkat(WorkflowStatus::STAGE_SEMAKAN_KELULUSAN),
        );
    }

    public function test_kb_tidak_boleh_mengesahkan_laporan_yang_belum_dihantar_ppa(): void
    {
        $this->sehinggaLaporanDihantar();

        // Laporan masih di tangan PPA — KB tidak boleh memintasnya.
        $this->actingAs($this->kb)
            ->post(route('kemajuan.sahkan', self::ALPHA))
            ->assertSessionHasErrors('laporan');

        $this->assertDatabaseHas('laporan_semakan', [
            'agency_code' => self::ALPHA,
            'status' => LaporanSemakan::MENUNGGU_PPA,
        ]);

        $this->assertNotSame(WorkflowStageStatus::SELESAI, $this->statusPeringkat(WorkflowStatus::STAGE_JANA_LAPORAN));
    }

    /**
     * Selepas KB mengembalikan laporan, PPA MESTI menyemaknya semula —
     * penghantaran semula tidak boleh terus ke KB.
     */
    public function test_laporan_yang_dikembalikan_kb_mesti_melalui_ppa_semula(): void
    {
        $this->sehinggaLaporanDihantar();
        $this->actingAs($this->ppa)->post(route('kemajuan.semak', self::ALPHA));

        $this->actingAs($this->kb)->post(route('kemajuan.kembalikan', self::ALPHA), [
            'catatan' => 'Kesimpulan perlu diperincikan.',
        ]);

        // PA membetulkan dan menghantar semula — kepada PPA, bukan KB.
        $this->actingAs($this->pa->fresh());
        $this->post(route('analisis.simpan'), $this->dapatan());
        $this->post(route('kemajuan.hantar', self::ALPHA))->assertSessionHasNoErrors();

        $this->assertDatabaseHas('laporan_semakan', [
            'agency_code' => self::ALPHA,
            'status' => LaporanSemakan::MENUNGGU_PPA,
        ]);

        // KB masih belum boleh mengesahkan.
        $this->actingAs($this->kb)
            ->post(route('kemajuan.sahkan', self::ALPHA))
            ->assertSessionHasErrors('laporan');

        // Barulah kitaran penuh diselesaikan semula.
        $this->actingAs($this->ppa)->post(route('kemajuan.semak', self::ALPHA));
        $this->actingAs($this->kb)->post(route('kemajuan.sahkan', self::ALPHA))->assertSessionHasNoErrors();

        $this->assertSame(WorkflowStageStatus::SELESAI, $this->statusPeringkat(WorkflowStatus::STAGE_JANA_LAPORAN));
        $this->assertSame(WorkflowStageStatus::SELESAI, $this->statusPeringkat(WorkflowStatus::STAGE_SEMAKAN_KELULUSAN));
    }

    /*
    |--------------------------------------------------------------------------
    | Status Laporan — perbendaharaan paparan
    |--------------------------------------------------------------------------
    */

    public function test_status_laporan_dipaparkan_mengikut_kitaran_semakan(): void
    {
        // Belum dihantar langsung — tiada rekod laporan lagi.
        $this->assertSame(
            LaporanSemakan::PAPARAN_BELUM_LENGKAP,
            LaporanSemakan::paparanUntuk(app(LaporanSemakanService::class)->untuk(self::ALPHA)),
        );

        $this->sehinggaLaporanDihantar();

        $this->assertSame(
            LaporanSemakan::PAPARAN_DALAM_SEMAKAN,
            app(LaporanSemakanService::class)->untuk(self::ALPHA)->statusPaparan(),
        );

        $this->actingAs($this->ppa)->post(route('kemajuan.semak', self::ALPHA));

        $this->assertSame(
            LaporanSemakan::PAPARAN_DALAM_SEMAKAN,
            app(LaporanSemakanService::class)->untuk(self::ALPHA)->fresh()->statusPaparan(),
        );

        $this->actingAs($this->kb)->post(route('kemajuan.sahkan', self::ALPHA));

        $this->assertSame(
            LaporanSemakan::PAPARAN_DISAHKAN,
            app(LaporanSemakanService::class)->untuk(self::ALPHA)->statusPaparan(),
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Jejak audit — setiap tindakan aliran kerja meninggalkan rekod
    |--------------------------------------------------------------------------
    */

    public function test_setiap_tindakan_aliran_kerja_direkodkan_dalam_jejak_audit(): void
    {
        $this->sehinggaLaporanDihantar();
        $this->actingAs($this->ppa)->post(route('kemajuan.kembalikan', self::ALPHA), ['catatan' => 'Betulkan Jadual 3.']);

        $this->actingAs($this->pa->fresh())->post(route('kemajuan.hantar', self::ALPHA));
        $this->actingAs($this->ppa)->post(route('kemajuan.semak', self::ALPHA));
        $this->actingAs($this->kb)->post(route('kemajuan.sahkan', self::ALPHA));
        $this->actingAs($this->kb)->post(route('kemajuan.serah', self::ALPHA));

        $tindakan = app(KemajuanAnalisisService::class)->sejarah(self::ALPHA, 200)->pluck('action')->all();

        foreach ([
            'registration_completed',
            'stage_status_changed',
            'report_submitted',
            'report_returned',
            'report_reviewed',
            'report_approved',
            'report_delivered',
        ] as $dijangka) {
            $this->assertContains($dijangka, $tindakan, "Tindakan {$dijangka} sepatutnya direkodkan.");
        }

        // Catatan pengembalian kekal dalam rekod audit.
        $dikembalikan = app(KemajuanAnalisisService::class)
            ->sejarah(self::ALPHA, 200)
            ->firstWhere('action', 'report_returned');

        $this->assertSame('Betulkan Jadual 3.', $dikembalikan->metadata['catatan']);
        $this->assertSame($this->ppa->id, $dikembalikan->changed_by_user_id);
    }
}
