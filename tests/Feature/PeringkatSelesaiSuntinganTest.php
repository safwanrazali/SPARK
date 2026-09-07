<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\WorkflowStageStatus;
use App\Services\EntityAssignmentService;
use App\Support\AliranKerja;
use App\Support\SektorDirectory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\MelaluiAliranKerja;
use Tests\TestCase;

/**
 * Kembali kepada peringkat yang telah SELESAI.
 *
 * Blok "Peringkat Kemajuan" mengekalkan borang setiap peringkat Selesai yang
 * MASIH tanggungjawab pengguna, supaya maklumat yang tersalah rekod boleh
 * dibetulkan tanpa menetapkan semula aliran kerja.
 *
 * DUA perkara yang diuji di sini, dan yang kedua lebih penting:
 *
 * 1. Borang yang betul sahaja dipaparkan — peringkat milik pengguna, sama ada
 *    Selesai atau sedang berjalan; bukan peringkat orang lain, dan tidak
 *    pernah peringkat fasa akan datang.
 * 2. Borang yang dipaparkan BUKAN kebenaran. Setiap tindakan kekal disemak
 *    pada pelayan: URL langsung, permintaan JSON dan borang yang dihantar
 *    terus semuanya ditolak dengan gate yang sama seperti sebelum ini.
 *
 * Peranan peringkat datang daripada App\Support\AliranKerjaDefinisi dan
 * gate-nya daripada AppServiceProvider — tiada senarai kedua di sini.
 *
 *   1.1 Penerimaan Data                  KB / PPA
 *   1.2 Pendaftaran Data                 PPA
 *   1.3 Semakan Awal Data                PA
 *   2   Penyediaan & Pengesahan Data     PA
 *   3.1 Analisis Inventori Kriptografi   PA
 */
class PeringkatSelesaiSuntinganTest extends TestCase
{
    use MelaluiAliranKerja;
    use RefreshDatabase;

    private const ALPHA = 'A010101';

    private User $kb;

    private User $ppa;

    private User $pa;

    private User $ps;

    protected function setUp(): void
    {
        parent::setUp();

        $this->kb = User::factory()->create(['role' => User::ROLE_KETUA_BAHAGIAN]);
        $this->ppa = User::factory()->create(['role' => User::ROLE_COORDINATOR]);
        $this->pa = User::factory()->create(['role' => User::ROLE_ANALYST]);
        $this->ps = User::factory()->create(['role' => User::ROLE_ADMINISTRATOR]);
    }

    /**
     * Bawa entiti melalui aliran kerja SEHINGGA $hingga (eksklusif), dengan
     * Pegawai Analisis ujian ini ditugaskan kepadanya.
     *
     * Penugasan dibuat DAHULU supaya fikstur tidak mencipta pegawai lain —
     * tanpa itu, $this->pa tidak mempunyai akses entiti langsung.
     */
    private function bawaHingga(string $hingga): void
    {
        $this->masukkanKeAliranTanpaSiap(self::ALPHA, $this->ppa);

        app(EntityAssignmentService::class)->assign(
            SektorDirectory::cariEntiti(self::ALPHA),
            $this->pa,
            $this->ppa,
        );

        foreach (AliranKerja::semasa() as $kunci) {
            if ($kunci === $hingga) {
                return;
            }

            $this->siapkanPeringkat(self::ALPHA, $kunci, $this->ppa);
        }
    }

    private function urlSimpan(string $kunci): string
    {
        return route('kemajuan.simpan', [self::ALPHA, $kunci]);
    }

    private function statusPeringkat(string $kunci): ?string
    {
        return WorkflowStageStatus::query()
            ->forAgency(self::ALPHA)
            ->atStage($kunci)
            ->value('status');
    }

    /*
    |--------------------------------------------------------------------------
    | A — pengguna yang berhak + peringkat Selesai
    |--------------------------------------------------------------------------
    */

    public function test_borang_peringkat_selesai_milik_pengguna_kekal_dipaparkan(): void
    {
        $this->bawaHingga('tiada-peringkat-sedemikian');

        $response = $this->actingAs($this->pa)->get(route('workflow.show', self::ALPHA));

        $response->assertOk();

        // Peringkat PA: 1.3, 2 dan 3.1 — kesemuanya Selesai dan masih miliknya,
        // jadi borangnya kekal terbuka untuk pembetulan.
        foreach ([AliranKerja::SEMAKAN_AWAL_DATA, AliranKerja::PENYEDIAAN_DATA, AliranKerja::ANALISIS_INVENTORI] as $kunci) {
            $response->assertSee($this->urlSimpan($kunci), false);
            $response->assertSee(AliranKerja::labelPenuh($kunci));
        }
    }

    public function test_pengguna_berhak_boleh_menyimpan_semula_peringkat_yang_telah_selesai(): void
    {
        $this->bawaHingga('tiada-peringkat-sedemikian');

        $this->assertSame(WorkflowStageStatus::SELESAI, $this->statusPeringkat(AliranKerja::SEMAKAN_AWAL_DATA));

        $this->actingAs($this->pa)
            ->post($this->urlSimpan(AliranKerja::SEMAKAN_AWAL_DATA), [
                AliranKerja::MEDAN_TARIKH_SEMAKAN => '2026-09-01',
                AliranKerja::MEDAN_STATUS_BORANG => 'Selesai',
            ])
            ->assertRedirect();

        $rekod = WorkflowStageStatus::query()
            ->forAgency(self::ALPHA)
            ->atStage(AliranKerja::SEMAKAN_AWAL_DATA)
            ->first();

        $this->assertSame('2026-09-01', $rekod->tarikh_semakan->format('Y-m-d'));

        // Pembetulan yang kekal lengkap tidak menjatuhkan status peringkat.
        $this->assertSame(WorkflowStageStatus::SELESAI, $rekod->status);
    }

    /*
    |--------------------------------------------------------------------------
    | B — pengguna yang TIDAK berhak + peringkat Selesai
    |--------------------------------------------------------------------------
    */

    public function test_borang_peringkat_selesai_milik_peranan_lain_tidak_dipaparkan(): void
    {
        $this->bawaHingga('tiada-peringkat-sedemikian');

        // 1.1 milik KB/PPA dan 1.2 milik PPA — Selesai, tetapi bukan kerja PA.
        $this->actingAs($this->pa)
            ->get(route('workflow.show', self::ALPHA))
            ->assertOk()
            ->assertDontSee($this->urlSimpan(AliranKerja::PENERIMAAN_DATA), false)
            ->assertDontSee($this->urlSimpan(AliranKerja::PENDAFTARAN_DATA), false);
    }

    public function test_peranan_lain_masih_melihat_maklumat_peringkat_yang_bukan_miliknya(): void
    {
        $this->bawaHingga('tiada-peringkat-sedemikian');

        // Melihat bukan hak terhad: PPA membaca data peringkat 1.3 milik PA
        // dalam jadual "Maklumat Peringkat", tetapi tidak mendapat borangnya.
        $this->actingAs($this->ppa)
            ->get(route('workflow.show', self::ALPHA))
            ->assertOk()
            ->assertSee('Maklumat Peringkat')
            ->assertSee(AliranKerja::label(AliranKerja::SEMAKAN_AWAL_DATA))
            ->assertDontSee($this->urlSimpan(AliranKerja::SEMAKAN_AWAL_DATA), false);
    }

    public function test_pengguna_tanpa_hak_peringkat_ditolak_walaupun_melalui_url_langsung(): void
    {
        $this->bawaHingga('tiada-peringkat-sedemikian');

        $sebelum = WorkflowStageStatus::query()
            ->forAgency(self::ALPHA)
            ->atStage(AliranKerja::PENERIMAAN_DATA)
            ->value(AliranKerja::MEDAN_TARIKH_TERIMA);

        $this->actingAs($this->pa)
            ->post($this->urlSimpan(AliranKerja::PENERIMAAN_DATA), [
                AliranKerja::MEDAN_TARIKH_TERIMA => '2026-01-01',
                AliranKerja::MEDAN_STATUS_BORANG => 'Dalam Proses',
            ])
            ->assertForbidden();

        $this->assertEquals($sebelum, WorkflowStageStatus::query()
            ->forAgency(self::ALPHA)
            ->atStage(AliranKerja::PENERIMAAN_DATA)
            ->value(AliranKerja::MEDAN_TARIKH_TERIMA));
    }

    public function test_permintaan_json_turut_ditolak(): void
    {
        $this->bawaHingga('tiada-peringkat-sedemikian');

        $this->actingAs($this->ppa)
            ->postJson($this->urlSimpan(AliranKerja::SEMAKAN_AWAL_DATA), [
                AliranKerja::MEDAN_TARIKH_SEMAKAN => '2026-01-01',
                AliranKerja::MEDAN_STATUS_BORANG => 'Dalam Proses',
            ])
            ->assertForbidden();
    }

    public function test_menandakan_selesai_semula_turut_ditolak_bagi_peringkat_orang_lain(): void
    {
        $this->bawaHingga('tiada-peringkat-sedemikian');

        $this->actingAs($this->pa)
            ->post(route('kemajuan.selesai', [self::ALPHA, AliranKerja::PENDAFTARAN_DATA]))
            ->assertForbidden();
    }

    public function test_pentadbir_sistem_tiada_borang_dan_tiada_kuasa_menyunting(): void
    {
        $this->bawaHingga('tiada-peringkat-sedemikian');

        $paparan = $this->actingAs($this->ps)
            ->get(route('workflow.show', self::ALPHA))
            ->assertOk();

        // Pentadbir Sistem melihat sahaja — tiada gate peringkat langsung.
        foreach (AliranKerja::semasa() as $kunci) {
            $paparan->assertDontSee($this->urlSimpan($kunci), false);
        }

        $this->actingAs($this->ps)
            ->post($this->urlSimpan(AliranKerja::PENERIMAAN_DATA), [
                AliranKerja::MEDAN_TARIKH_TERIMA => '2026-01-01',
                AliranKerja::MEDAN_STATUS_BORANG => 'Dalam Proses',
            ])
            ->assertForbidden();
    }

    public function test_pegawai_analisis_entiti_lain_ditolak_pada_lapisan_akses_entiti(): void
    {
        $this->bawaHingga('tiada-peringkat-sedemikian');

        $asing = User::factory()->create(['role' => User::ROLE_ANALYST]);

        // Peranan betul, entiti salah: kawalan akses entiti menolaknya dahulu.
        $this->actingAs($asing)
            ->post($this->urlSimpan(AliranKerja::SEMAKAN_AWAL_DATA), [
                AliranKerja::MEDAN_TARIKH_SEMAKAN => '2026-01-01',
                AliranKerja::MEDAN_STATUS_BORANG => 'Dalam Proses',
            ])
            ->assertForbidden();
    }

    /*
    |--------------------------------------------------------------------------
    | C dan D — peringkat semasa dan peringkat fasa akan datang
    |--------------------------------------------------------------------------
    */

    public function test_borang_peringkat_semasa_kekal_seperti_sebelum_ini(): void
    {
        $this->bawaHingga(AliranKerja::ANALISIS_INVENTORI);

        $this->actingAs($this->pa)
            ->get(route('workflow.show', self::ALPHA))
            ->assertOk()
            ->assertSee($this->urlSimpan(AliranKerja::ANALISIS_INVENTORI), false)
            ->assertSee('workflow-step--semasa', false);
    }

    public function test_peringkat_fasa_akan_datang_tidak_pernah_mempunyai_borang(): void
    {
        $this->bawaHingga('tiada-peringkat-sedemikian');

        $response = $this->actingAs($this->pa)->get(route('workflow.show', self::ALPHA));

        $response->assertOk()->assertSee('workflow-step--akan-datang', false);

        foreach (AliranKerja::akanDatang() as $kunci) {
            $response->assertDontSee($this->urlSimpan($kunci), false);
        }
    }

    public function test_peringkat_fasa_akan_datang_menolak_permintaan_langsung(): void
    {
        $this->bawaHingga('tiada-peringkat-sedemikian');

        $this->actingAs($this->pa)
            ->post($this->urlSimpan(AliranKerja::ANALISIS_RISIKO_PQC), [])
            ->assertNotFound();
    }

    /*
    |--------------------------------------------------------------------------
    | G — kemajuan hiliran tidak diundurkan
    |--------------------------------------------------------------------------
    */

    public function test_menyunting_peringkat_awal_tidak_mengundurkan_peringkat_hiliran(): void
    {
        $this->bawaHingga('tiada-peringkat-sedemikian');

        foreach (AliranKerja::semasa() as $kunci) {
            $this->assertSame(WorkflowStageStatus::SELESAI, $this->statusPeringkat($kunci), $kunci);
        }

        // PPA membetulkan Tarikh Daftar peringkat 1.2 yang telah Selesai.
        $this->actingAs($this->ppa)
            ->post($this->urlSimpan(AliranKerja::PENDAFTARAN_DATA), [
                AliranKerja::MEDAN_TARIKH_DAFTAR => '2026-09-02',
                AliranKerja::MEDAN_STATUS_BORANG => 'Selesai',
            ])
            ->assertRedirect();

        // Setiap peringkat lain kekal Selesai — tiada penetapan semula aliran.
        foreach (AliranKerja::semasa() as $kunci) {
            $this->assertSame(WorkflowStageStatus::SELESAI, $this->statusPeringkat($kunci), $kunci);
        }
    }

    public function test_mengosongkan_medan_hanya_menjejaskan_peringkat_itu_sendiri(): void
    {
        $this->bawaHingga('tiada-peringkat-sedemikian');

        // Status Borang dikosongkan: peringkat 1.2 sendiri tidak lagi lengkap.
        $this->actingAs($this->ppa)
            ->post($this->urlSimpan(AliranKerja::PENDAFTARAN_DATA), [
                AliranKerja::MEDAN_TARIKH_DAFTAR => '2026-09-02',
                AliranKerja::MEDAN_STATUS_BORANG => '',
            ])
            ->assertRedirect();

        $this->assertSame(
            WorkflowStageStatus::DALAM_PROSES,
            $this->statusPeringkat(AliranKerja::PENDAFTARAN_DATA),
        );

        // Kerja hiliran yang telah disiapkan TIDAK dimusnahkan.
        foreach ([AliranKerja::SEMAKAN_AWAL_DATA, AliranKerja::PENYEDIAAN_DATA, AliranKerja::ANALISIS_INVENTORI] as $kunci) {
            $this->assertSame(WorkflowStageStatus::SELESAI, $this->statusPeringkat($kunci), $kunci);
        }
    }
}
