<?php

namespace Tests\Feature;

use App\Models\AnalisisInventori;
use App\Models\StatusLaporan;
use App\Models\User;
use App\Models\WorkflowStageStatus;
use App\Services\EntityAssignmentService;
use App\Services\KemajuanAnalisisService;
use App\Services\StatusTigaLaporanService;
use App\Support\AliranKerja;
use App\Support\SektorDirectory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Aliran kerja Kemajuan Analisis Entiti — LIMA peringkat utama.
 *
 *   1.1 Penerimaan Data                  KB / PPA
 *   1.2 Pendaftaran Data                 PPA
 *   1.3 Semakan Awal Data                PA
 *   2   Penyediaan & Pengesahan Data     PA
 *   3.1 Analisis Inventori Kriptografi   PA   ← fasa semasa berakhir di sini
 *   3.2 / 4 / 5                          fasa akan datang
 *
 * NOTA RESTRUKTUR: fail ini pernah menguji kitaran semakan dan kelulusan
 * laporan (PA → PPA → KB → NACSA). Kitaran itu milik peringkat 4 dan 5, yang
 * prosesnya BELUM DITENTUKAN, jadi route dan butangnya telah ditanggalkan dan
 * ujiannya digugurkan bersama. Servis, model dan jadualnya kekal utuh —
 * lihat App\Services\LaporanSemakanService — dan ujiannya perlu ditulis
 * semula mengikut proses sebenar apabila spesifikasi peringkat itu tiba.
 */
class KemajuanAnalisisAliranTest extends TestCase
{
    use RefreshDatabase;

    private const SEKTOR = '001';

    private const ALPHA = 'A010101';

    private const BETA = 'A010102';

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
     * Peringkat 1.1 Selesai (pintu masuk aliran) dan entiti ditugaskan kepada PA.
     */
    private function sediakanEntiti(string $agencyCode = self::ALPHA): void
    {
        app(KemajuanAnalisisService::class)->lengkapkanPenerimaan(
            SektorDirectory::cariEntiti($agencyCode),
            $this->ppa,
            ['tarikh_terima' => '2026-08-14', 'status_borang' => 'Selesai', 'no_rujukan' => 'FIKSTUR/1.1'],
        );

        app(EntityAssignmentService::class)->assign(
            SektorDirectory::cariEntiti($agencyCode),
            $this->pa,
            $this->ppa,
        );
    }

    /**
     * Lalui aliran sehingga peringkat $hingga (eksklusif) Selesai, melalui
     * route sebenar supaya kebenaran peranan turut diuji sepanjang jalan.
     */
    private function lalui(string $hingga, string $agencyCode = self::ALPHA): void
    {
        $this->sediakanEntiti($agencyCode);

        $pemilik = [
            AliranKerja::PENDAFTARAN_DATA => $this->ppa,
            AliranKerja::SEMAKAN_AWAL_DATA => $this->pa,
            AliranKerja::PENYEDIAAN_DATA => $this->pa,
            AliranKerja::ANALISIS_INVENTORI => $this->pa,
        ];

        foreach ($pemilik as $kunci => $pengguna) {
            if ($kunci === $hingga) {
                return;
            }

            $this->actingAs($pengguna)
                ->post(route('kemajuan.selesai', [$agencyCode, $kunci]))
                ->assertRedirect();
        }
    }

    private function statusPeringkat(string $stage, string $agencyCode = self::ALPHA): string
    {
        return app(KemajuanAnalisisService::class)
            ->peringkat($agencyCode)
            ->get($stage)?->status ?? 'tiada';
    }

    private function keseluruhan(string $agencyCode = self::ALPHA): string
    {
        return app(KemajuanAnalisisService::class)->keseluruhan($agencyCode);
    }

    /**
     * Muatan minimum bagi "Simpan Dapatan" borang Analisis Inventori.
     *
     * @return array<string, mixed>
     */
    private function dapatan(array $ubah = []): array
    {
        return array_replace([
            'sector_code' => self::SEKTOR,
            'agency_code' => self::ALPHA,
            'tarikh_laporan' => '2026-08-20',
            'kod_rujukan' => 'R-LP-MIG-4-0007-V1.0',
            'status_laporan' => 'Selesai',
        ], $ubah);
    }

    /*
    |--------------------------------------------------------------------------
    | Turutan peringkat
    |--------------------------------------------------------------------------
    */

    /*
    |--------------------------------------------------------------------------
    | Pintu masuk aliran kerja — peringkat 1.1 pada halaman Kemajuan
    |--------------------------------------------------------------------------
    */

    /**
     * KB atau PPA membuka halaman Kemajuan bagi entiti yang belum berada
     * dalam aliran kerja dan merekod Tarikh Terima serta Status Borang
     * Penerimaan Data.
     *
     * Itu MEMASUKKAN entiti ke dalam aliran kerja — inilah yang menggantikan
     * penandaan pukal skrin Penetapan Entiti yang telah dibuang — dan
     * membuka peringkat 1.2 Pendaftaran Data, WALAUPUN peringkat 1.1 sendiri
     * belum Selesai kerana No. Rujukan (milik PPR) masih tiada.
     */
    public function test_merekod_peringkat_satu_memasukkan_entiti_dan_membuka_peringkat_seterusnya(): void
    {
        $kod = self::BETA;

        // Titik permulaan: entiti langsung tiada baris peringkat.
        $this->assertDatabaseMissing('workflow_status', ['agency_code' => $kod]);
        $this->assertTrue(app(KemajuanAnalisisService::class)->peringkat($kod)->isEmpty());

        $this->actingAs($this->ppa)
            ->post(route('kemajuan.simpan', [$kod, AliranKerja::PENERIMAAN_DATA]), [
                'tarikh_terima' => '2026-08-14',
                'status_borang' => 'Selesai',
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $peringkat = app(KemajuanAnalisisService::class)->peringkat($kod);

        // Entiti kini berada dalam aliran kerja, dengan baris bagi SETIAP
        // peringkat yang ditakrifkan.
        $this->assertCount(count(AliranKerja::kekunci()), $peringkat);
        $this->assertDatabaseHas('workflow_status', ['agency_code' => $kod]);

        $satuSatu = $peringkat->get(AliranKerja::PENERIMAAN_DATA);

        // Dua daripada tiga syarat ada, jadi peringkat 1.1 Dalam Proses —
        // BUKAN Selesai: No. Rujukan masih menunggu PPR.
        $this->assertSame(WorkflowStageStatus::DALAM_PROSES, $satuSatu->status);
        $this->assertSame('2026-08-14', $satuSatu->tarikh_terima->format('Y-m-d'));
        $this->assertSame('Selesai', $satuSatu->status_borang);
        $this->assertNull($satuSatu->no_rujukan);

        // ...namun peringkat 1.2 TETAP terbuka: syarat lanjut peringkat 1.1
        // ialah dua medan itu sahaja.
        $this->assertNull(
            app(KemajuanAnalisisService::class)->ralatPeringkat($kod, AliranKerja::PENDAFTARAN_DATA),
        );

        $this->actingAs($this->ppa)
            ->post(route('kemajuan.selesai', [$kod, AliranKerja::PENDAFTARAN_DATA]))
            ->assertSessionHasNoErrors();
    }

    /**
     * Ketua Bahagian turut memiliki peringkat 1.1 — jadual peranan menamakan
     * KB dan PPA bagi peringkat ini.
     */
    public function test_ketua_bahagian_turut_boleh_memasukkan_entiti(): void
    {
        $this->actingAs($this->kb)
            ->post(route('kemajuan.simpan', [self::BETA, AliranKerja::PENERIMAAN_DATA]), [
                'tarikh_terima' => '2026-08-14',
                'status_borang' => 'Selesai',
            ])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('workflow_status', ['agency_code' => self::BETA]);
    }

    /**
     * Peranan lain tidak boleh memasukkan entiti ke dalam aliran kerja, dan
     * percubaan mereka tidak boleh meninggalkan baris peringkat separuh jadi.
     */
    public function test_peranan_lain_tidak_boleh_memasukkan_entiti(): void
    {
        foreach ([$this->pa, $this->ppr] as $bukanPemilik) {
            $this->actingAs($bukanPemilik)
                ->post(route('kemajuan.simpan', [self::BETA, AliranKerja::PENERIMAAN_DATA]), [
                    'tarikh_terima' => '2026-08-14',
                ])
                ->assertForbidden();
        }

        $this->assertDatabaseMissing('workflow_status', ['agency_code' => self::BETA]);
        $this->assertTrue(app(KemajuanAnalisisService::class)->peringkat(self::BETA)->isEmpty());
    }

    /**
     * Menyimpan data peringkat 1.1 TANPA menandakannya Selesai turut
     * memasukkan entiti ke dalam aliran — tetapi peringkat 1.2 kekal tertutup
     * sehingga 1.1 benar-benar Selesai.
     */
    public function test_menyimpan_data_peringkat_satu_memasukkan_entiti_tanpa_membuka_peringkat_seterusnya(): void
    {
        $this->actingAs($this->ppa)
            ->post(route('kemajuan.simpan', [self::BETA, AliranKerja::PENERIMAAN_DATA]), [
                'tarikh_terima' => '2026-08-14',
            ])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('workflow_status', ['agency_code' => self::BETA]);
        $this->assertFalse(app(KemajuanAnalisisService::class)->penerimaanSelesai(self::BETA));

        $this->assertNotNull(
            app(KemajuanAnalisisService::class)->ralatPeringkat(self::BETA, AliranKerja::PENDAFTARAN_DATA),
        );
    }

    /**
     * No. Rujukan direkod PADA baris peringkat, jadi ia belum berkenaan
     * sebelum entiti memasuki aliran kerja — memasukkannya ialah tindakan
     * peringkat 1.1, bukan tindakan PPR.
     */
    public function test_no_rujukan_ditolak_sebelum_entiti_memasuki_aliran(): void
    {
        $this->actingAs($this->ppr)
            ->post(route('kemajuan.rujukan', [self::BETA, AliranKerja::PENERIMAAN_DATA]), [
                'no_rujukan' => 'BPD/2026/001',
            ])
            ->assertSessionHasErrors('no_rujukan');

        $this->assertTrue(app(KemajuanAnalisisService::class)->peringkat(self::BETA)->isEmpty());
    }

    /**
     * Peringkat 1.1 menjadi Selesai apabila KETIGA-TIGA medannya ada —
     * termasuk No. Rujukan, yang dimasukkan oleh PPR selepas KB/PPA.
     */
    public function test_peringkat_satu_selesai_apabila_ketiga_tiga_medan_ada(): void
    {
        $kod = self::BETA;
        $kemajuan = app(KemajuanAnalisisService::class);

        $this->actingAs($this->ppa)
            ->post(route('kemajuan.simpan', [$kod, AliranKerja::PENERIMAAN_DATA]), [
                'tarikh_terima' => '2026-08-14',
                'status_borang' => 'Selesai',
            ]);

        $this->assertSame(
            WorkflowStageStatus::DALAM_PROSES,
            $kemajuan->peringkat($kod)->get(AliranKerja::PENERIMAAN_DATA)->status,
        );
        $this->assertFalse($kemajuan->penerimaanSelesai($kod));

        // PPR melengkapkan medan terakhir — dan itulah yang menyiapkannya.
        $this->actingAs($this->ppr)
            ->post(route('kemajuan.rujukan', [$kod, AliranKerja::PENERIMAAN_DATA]), [
                'no_rujukan' => 'BPD/2026/001',
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame(
            WorkflowStageStatus::SELESAI,
            $kemajuan->peringkat($kod)->get(AliranKerja::PENERIMAAN_DATA)->status,
        );
        $this->assertTrue($kemajuan->penerimaanSelesai($kod));
    }

    /**
     * Satu medan sahaja tidak memadai: peringkat 1.2 kekal tertutup sehingga
     * KEDUA-DUA syarat lanjut peringkat 1.1 ada.
     */
    public function test_peringkat_seterusnya_tertutup_sehingga_kedua_dua_syarat_lanjut_ada(): void
    {
        $kod = self::BETA;
        $kemajuan = app(KemajuanAnalisisService::class);

        $this->actingAs($this->ppa)
            ->post(route('kemajuan.simpan', [$kod, AliranKerja::PENERIMAAN_DATA]), [
                'tarikh_terima' => '2026-08-14',
            ]);

        $ralat = $kemajuan->ralatPeringkat($kod, AliranKerja::PENDAFTARAN_DATA);

        $this->assertNotNull($ralat);
        $this->assertStringContainsString('Status Borang Penerimaan Data', $ralat);

        $this->actingAs($this->ppa)
            ->post(route('kemajuan.selesai', [$kod, AliranKerja::PENDAFTARAN_DATA]))
            ->assertSessionHasErrors('stage');

        // Medan kedua direkod — peringkat 1.2 terbuka serta-merta.
        $this->actingAs($this->ppa)
            ->post(route('kemajuan.simpan', [$kod, AliranKerja::PENERIMAAN_DATA]), [
                'tarikh_terima' => '2026-08-14',
                'status_borang' => 'Selesai',
            ]);

        $this->assertNull($kemajuan->ralatPeringkat($kod, AliranKerja::PENDAFTARAN_DATA));
    }

    /**
     * Membuang salah satu medan membuka semula peringkat itu: status
     * DITERBITKAN daripada data, jadi ia mengikut data ke KEDUA-DUA arah.
     */
    public function test_membuang_medan_membuka_semula_peringkat(): void
    {
        $this->sediakanEntiti();

        $kemajuan = app(KemajuanAnalisisService::class);

        $this->assertTrue($kemajuan->penerimaanSelesai(self::ALPHA));

        $this->actingAs($this->ppr)
            ->post(route('kemajuan.rujukan', [self::ALPHA, AliranKerja::PENERIMAAN_DATA]), [
                'no_rujukan' => '',
            ]);

        $this->assertSame(
            WorkflowStageStatus::DALAM_PROSES,
            $kemajuan->peringkat(self::ALPHA)->get(AliranKerja::PENERIMAAN_DATA)->status,
        );

        // Namun peringkat 1.2 kekal TERBUKA: syarat lanjut masih dipenuhi.
        $this->assertNull($kemajuan->ralatPeringkat(self::ALPHA, AliranKerja::PENDAFTARAN_DATA));
    }

    /**
     * Status peringkat berderivasi tidak boleh ditetapkan terus — jika boleh,
     * ia menjadi sumber kebenaran kedua yang bercanggah dengan datanya sendiri.
     */
    public function test_status_peringkat_berderivasi_tidak_boleh_ditetapkan_terus(): void
    {
        $this->lalui(AliranKerja::PENDAFTARAN_DATA);

        // Tiada laluan HTTP.
        $this->actingAs($this->ppa)
            ->post(route('kemajuan.selesai', [self::ALPHA, AliranKerja::PENERIMAAN_DATA]))
            ->assertNotFound();

        // Tiada laluan servis.
        $this->expectException(\App\Exceptions\InvalidWorkflowTransitionException::class);

        app(KemajuanAnalisisService::class)->tandakanSelesai(
            self::BETA,
            AliranKerja::PENERIMAAN_DATA,
            $this->ppa,
        );
    }

    public function test_entiti_memasuki_aliran_dengan_lapan_baris_peringkat(): void
    {
        $this->sediakanEntiti();

        $peringkat = app(KemajuanAnalisisService::class)->peringkat(self::ALPHA);

        // Setiap peringkat yang DITAKRIFKAN mendapat barisnya, termasuk yang
        // belum dibina — itulah yang menjadikan 3.2, 4 dan 5 boleh dihidupkan
        // kemudian tanpa migrasi struktur semula.
        $this->assertSame(AliranKerja::kekunci(), $peringkat->keys()->map(strval(...))->all());

        $this->assertSame(
            WorkflowStageStatus::SELESAI,
            $this->statusPeringkat(AliranKerja::PENERIMAAN_DATA),
        );

        $this->assertSame(
            WorkflowStageStatus::BELUM_MULA,
            $this->statusPeringkat(AliranKerja::PENDAFTARAN_DATA),
        );
    }

    public function test_aliran_fasa_semasa_diselesaikan_mengikut_turutan(): void
    {
        $this->lalui(AliranKerja::PENDAFTARAN_DATA);

        // 1.2 — PPA
        $this->actingAs($this->ppa)
            ->post(route('kemajuan.selesai', [self::ALPHA, AliranKerja::PENDAFTARAN_DATA]))
            ->assertRedirect()
            ->assertSessionHas('success');

        // 1.3 — PA
        $this->actingAs($this->pa)
            ->post(route('kemajuan.selesai', [self::ALPHA, AliranKerja::SEMAKAN_AWAL_DATA]))
            ->assertRedirect();

        // 2 — PA
        $this->actingAs($this->pa)
            ->post(route('kemajuan.selesai', [self::ALPHA, AliranKerja::PENYEDIAAN_DATA]))
            ->assertRedirect();

        $this->assertSame(KemajuanAnalisisService::KESELURUHAN_DALAM_PROSES, $this->keseluruhan());

        // 3.1 — PA. Peringkat terakhir fasa semasa.
        $this->actingAs($this->pa)
            ->post(route('kemajuan.selesai', [self::ALPHA, AliranKerja::ANALISIS_INVENTORI]))
            ->assertRedirect();

        foreach (AliranKerja::semasa() as $kunci) {
            $this->assertSame(WorkflowStageStatus::SELESAI, $this->statusPeringkat($kunci), $kunci);
        }

        // Siap bermakna fasa SEMASA selesai. Peringkat 3.2, 4 dan 5 belum
        // dibina, jadi menuntutnya menjadikan Siap mustahil dicapai.
        $this->assertSame(KemajuanAnalisisService::KESELURUHAN_SIAP, $this->keseluruhan());

        $this->assertSame(
            WorkflowStageStatus::BELUM_MULA,
            $this->statusPeringkat(AliranKerja::ANALISIS_RISIKO_PQC),
        );
    }

    public function test_peringkat_tidak_boleh_dilangkau(): void
    {
        $this->sediakanEntiti();

        // 1.2 belum Selesai, jadi 1.3 belum terbuka.
        $this->actingAs($this->pa)
            ->post(route('kemajuan.selesai', [self::ALPHA, AliranKerja::SEMAKAN_AWAL_DATA]))
            ->assertSessionHasErrors('stage');

        $this->assertSame(
            WorkflowStageStatus::BELUM_MULA,
            $this->statusPeringkat(AliranKerja::SEMAKAN_AWAL_DATA),
        );
    }

    public function test_sub_peringkat_dalam_peringkat_satu_turut_berturutan(): void
    {
        $this->sediakanEntiti();

        // Melangkau 1.2 untuk terus ke 2 juga dihalang — sub-peringkat ialah
        // sebahagian daripada turutan, bukan hiasan paparan.
        $this->actingAs($this->pa)
            ->post(route('kemajuan.selesai', [self::ALPHA, AliranKerja::PENYEDIAAN_DATA]))
            ->assertSessionHasErrors('stage');
    }

    /*
    |--------------------------------------------------------------------------
    | Peranan setiap peringkat
    |--------------------------------------------------------------------------
    */

    public function test_peringkat_pendaftaran_data_milik_ppa_sahaja(): void
    {
        $this->lalui(AliranKerja::PENDAFTARAN_DATA);

        foreach ([$this->pa, $this->ppr, $this->kb] as $bukanPemilik) {
            $this->actingAs($bukanPemilik)
                ->post(route('kemajuan.selesai', [self::ALPHA, AliranKerja::PENDAFTARAN_DATA]))
                ->assertForbidden();
        }

        $this->assertSame(
            WorkflowStageStatus::BELUM_MULA,
            $this->statusPeringkat(AliranKerja::PENDAFTARAN_DATA),
        );

        $this->actingAs($this->ppa)
            ->post(route('kemajuan.selesai', [self::ALPHA, AliranKerja::PENDAFTARAN_DATA]))
            ->assertRedirect();

        $this->assertSame(
            WorkflowStageStatus::SELESAI,
            $this->statusPeringkat(AliranKerja::PENDAFTARAN_DATA),
        );
    }

    public function test_peringkat_pa_tidak_boleh_dilaksanakan_oleh_peranan_lain(): void
    {
        $this->lalui(AliranKerja::SEMAKAN_AWAL_DATA);

        foreach ([$this->ppa, $this->ppr, $this->kb] as $bukanPemilik) {
            $this->actingAs($bukanPemilik)
                ->post(route('kemajuan.selesai', [self::ALPHA, AliranKerja::SEMAKAN_AWAL_DATA]))
                ->assertForbidden();
        }
    }

    public function test_pa_lain_tidak_boleh_menyentuh_entiti_yang_bukan_miliknya(): void
    {
        $this->lalui(AliranKerja::SEMAKAN_AWAL_DATA);

        $paLain = User::factory()->create(['role' => User::ROLE_ANALYST, 'name' => 'Pegawai B']);

        $this->actingAs($paLain)
            ->post(route('kemajuan.selesai', [self::ALPHA, AliranKerja::SEMAKAN_AWAL_DATA]))
            ->assertForbidden();

        $this->assertSame(
            WorkflowStageStatus::BELUM_MULA,
            $this->statusPeringkat(AliranKerja::SEMAKAN_AWAL_DATA),
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Data tangkapan setiap peringkat
    |--------------------------------------------------------------------------
    */

    public function test_peringkat_menangkap_medannya_sendiri(): void
    {
        $this->lalui(AliranKerja::PENDAFTARAN_DATA);

        $this->actingAs($this->ppa)
            ->post(route('kemajuan.simpan', [self::ALPHA, AliranKerja::PENDAFTARAN_DATA]), [
                'tarikh_terima' => '2026-08-14',
                'status_borang' => 'Dalam Semakan',
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $rekod = app(KemajuanAnalisisService::class)
            ->peringkat(self::ALPHA)
            ->get(AliranKerja::PENDAFTARAN_DATA);

        $this->assertSame('2026-08-14', $rekod->tarikh_terima->format('Y-m-d'));
        $this->assertSame('Dalam Semakan', $rekod->status_borang);

        // Merekod data bermakna kerja peringkat itu telah bermula.
        $this->assertSame(WorkflowStageStatus::DALAM_PROSES, $rekod->status);
    }

    public function test_peringkat_dua_menangkap_tarikh_mula_tamat_dan_nama_fail(): void
    {
        $this->lalui(AliranKerja::PENYEDIAAN_DATA);

        $this->actingAs($this->pa)
            ->post(route('kemajuan.simpan', [self::ALPHA, AliranKerja::PENYEDIAAN_DATA]), [
                'tarikh_mula' => '2026-08-01',
                'tarikh_tamat' => '2026-08-20',
                'status_borang' => 'Selesai',
                'nama_fail' => 'mastertable-A010101.xlsx',
            ])
            ->assertRedirect();

        $rekod = app(KemajuanAnalisisService::class)
            ->peringkat(self::ALPHA)
            ->get(AliranKerja::PENYEDIAAN_DATA);

        $this->assertSame('2026-08-01', $rekod->tarikh_mula->format('Y-m-d'));
        $this->assertSame('2026-08-20', $rekod->tarikh_tamat->format('Y-m-d'));
        $this->assertSame('Selesai', $rekod->status_borang);
        $this->assertSame('mastertable-A010101.xlsx', $rekod->nama_fail);
    }

    /**
     * Status Borang ialah senarai TERTUTUP: nilai di luar perbendaharaan
     * ditolak walaupun borang dihantar terus tanpa melalui antara muka.
     */
    public function test_status_borang_di_luar_perbendaharaan_ditolak(): void
    {
        $this->lalui(AliranKerja::PENDAFTARAN_DATA);

        $this->actingAs($this->ppa)
            ->post(route('kemajuan.simpan', [self::ALPHA, AliranKerja::PENDAFTARAN_DATA]), [
                'status_borang' => 'Diterima Lengkap',
            ])
            ->assertSessionHasErrors('status_borang');

        $this->assertNull(
            app(KemajuanAnalisisService::class)
                ->peringkat(self::ALPHA)
                ->get(AliranKerja::PENDAFTARAN_DATA)->status_borang,
        );
    }

    /**
     * Setiap nilai perbendaharaan mesti benar-benar boleh disimpan — senarai
     * yang ditolak separuh oleh pengesahan lebih buruk daripada tiada senarai.
     */
    public function test_setiap_nilai_status_borang_diterima(): void
    {
        $this->lalui(AliranKerja::PENDAFTARAN_DATA);

        foreach (AliranKerja::STATUS_BORANG as $status) {
            $this->actingAs($this->ppa)
                ->post(route('kemajuan.simpan', [self::ALPHA, AliranKerja::PENDAFTARAN_DATA]), [
                    'status_borang' => $status,
                ])
                ->assertSessionHasNoErrors();

            $this->assertSame(
                $status,
                app(KemajuanAnalisisService::class)
                    ->peringkat(self::ALPHA)
                    ->get(AliranKerja::PENDAFTARAN_DATA)->status_borang,
            );
        }
    }

    /**
     * Borang menawarkan kesemua tujuh pilihan — bukan medan teks bebas.
     */
    public function test_borang_menawarkan_kesemua_pilihan_status_borang(): void
    {
        $this->lalui(AliranKerja::PENDAFTARAN_DATA);

        $paparan = $this->actingAs($this->ppa)
            ->get(route('workflow.show', self::ALPHA))
            ->assertOk();

        foreach (AliranKerja::STATUS_BORANG as $status) {
            $paparan->assertSee(sprintf('<option value="%s"', $status), false);
        }
    }

    public function test_tarikh_tamat_tidak_boleh_mendahului_tarikh_mula(): void
    {
        $this->lalui(AliranKerja::PENYEDIAAN_DATA);

        $this->actingAs($this->pa)
            ->post(route('kemajuan.simpan', [self::ALPHA, AliranKerja::PENYEDIAAN_DATA]), [
                'tarikh_mula' => '2026-08-20',
                'tarikh_tamat' => '2026-08-01',
            ])
            ->assertSessionHasErrors('tarikh_tamat');
    }

    /**
     * Medan milik peringkat LAIN tidak boleh ditulis melalui borang peringkat
     * ini, walaupun ia dihantar terus.
     */
    public function test_medan_luar_takrifan_peringkat_diabaikan(): void
    {
        $this->lalui(AliranKerja::PENDAFTARAN_DATA);

        $this->actingAs($this->ppa)
            ->post(route('kemajuan.simpan', [self::ALPHA, AliranKerja::PENDAFTARAN_DATA]), [
                'tarikh_terima' => '2026-08-14',
                // 'nama_fail' ialah medan peringkat 2, bukan 1.2.
                'nama_fail' => 'cubaan-menulis-medan-peringkat-lain.xlsx',
            ])
            ->assertRedirect();

        $rekod = app(KemajuanAnalisisService::class)
            ->peringkat(self::ALPHA)
            ->get(AliranKerja::PENDAFTARAN_DATA);

        $this->assertNull($rekod->nama_fail);
    }

    public function test_selesai_menyimpan_data_yang_dihantar_bersamanya(): void
    {
        $this->lalui(AliranKerja::SEMAKAN_AWAL_DATA);

        $this->actingAs($this->pa)
            ->post(route('kemajuan.selesai', [self::ALPHA, AliranKerja::SEMAKAN_AWAL_DATA]), [
                'tarikh_semakan' => '2026-08-18',
                'status_borang' => 'Selesai',
            ])
            ->assertRedirect();

        $rekod = app(KemajuanAnalisisService::class)
            ->peringkat(self::ALPHA)
            ->get(AliranKerja::SEMAKAN_AWAL_DATA);

        $this->assertSame(WorkflowStageStatus::SELESAI, $rekod->status);
        $this->assertSame('2026-08-18', $rekod->tarikh_semakan->format('Y-m-d'));
        $this->assertSame('Selesai', $rekod->status_borang);
    }

    /*
    |--------------------------------------------------------------------------
    | No. Rujukan Borang — milik PPR, bukan pemilik peringkat
    |--------------------------------------------------------------------------
    */

    public function test_ppr_memasukkan_setiap_no_rujukan(): void
    {
        $this->sediakanEntiti();

        foreach ([
            AliranKerja::PENERIMAAN_DATA => 'BPD/2026/001',
            AliranKerja::PENDAFTARAN_DATA => 'BPFD/2026/002',
            AliranKerja::SEMAKAN_AWAL_DATA => 'BSAD/2026/003',
            AliranKerja::ANALISIS_INVENTORI => 'LAP/2026/004',
        ] as $kunci => $rujukan) {
            $this->actingAs($this->ppr)
                ->post(route('kemajuan.rujukan', [self::ALPHA, $kunci]), ['no_rujukan' => $rujukan])
                ->assertRedirect()
                ->assertSessionHas('success');

            $rekod = app(KemajuanAnalisisService::class)->peringkat(self::ALPHA)->get($kunci);

            $this->assertSame($rujukan, $rekod->no_rujukan);
            $this->assertSame($this->ppr->id, $rekod->no_rujukan_oleh_user_id);
            $this->assertNotNull($rekod->no_rujukan_pada);
        }
    }

    /**
     * Inilah pemisahan yang paling mudah hilang: pegawai yang MELAKSANAKAN
     * peringkat bukan pegawai yang memasukkan nombor rujukannya.
     */
    public function test_pemilik_peringkat_tidak_boleh_memasukkan_no_rujukan(): void
    {
        $this->sediakanEntiti();

        foreach ([$this->ppa, $this->pa, $this->kb] as $bukanPpr) {
            $this->actingAs($bukanPpr)
                ->post(route('kemajuan.rujukan', [self::ALPHA, AliranKerja::PENERIMAAN_DATA]), [
                    'no_rujukan' => 'CUBAAN/2026/001',
                ])
                ->assertForbidden();
        }

        // Nilai fikstur kekal tidak berubah — tiada percubaan itu menembusi.
        $this->assertSame(
            'FIKSTUR/1.1',
            app(KemajuanAnalisisService::class)
                ->peringkat(self::ALPHA)
                ->get(AliranKerja::PENERIMAAN_DATA)->no_rujukan,
        );
    }

    public function test_ppr_tidak_boleh_menggerakkan_peringkat(): void
    {
        $this->lalui(AliranKerja::PENDAFTARAN_DATA);

        $this->actingAs($this->ppr)
            ->post(route('kemajuan.selesai', [self::ALPHA, AliranKerja::PENDAFTARAN_DATA]))
            ->assertForbidden();

        $this->actingAs($this->ppr)
            ->post(route('kemajuan.simpan', [self::ALPHA, AliranKerja::PENDAFTARAN_DATA]), [
                'tarikh_terima' => '2026-08-14',
            ])
            ->assertForbidden();
    }

    /**
     * No. Rujukan tidak menunggu giliran peringkat: borang fizikal boleh
     * didaftarkan sebelum peringkat itu ditandakan Selesai.
     */
    public function test_no_rujukan_boleh_dimasukkan_sebelum_peringkat_selesai(): void
    {
        $this->lalui(AliranKerja::PENDAFTARAN_DATA);

        $this->actingAs($this->ppr)
            ->post(route('kemajuan.rujukan', [self::ALPHA, AliranKerja::SEMAKAN_AWAL_DATA]), [
                'no_rujukan' => 'BSAD/2026/009',
            ])
            ->assertRedirect();

        $this->assertSame(
            'BSAD/2026/009',
            app(KemajuanAnalisisService::class)
                ->peringkat(self::ALPHA)
                ->get(AliranKerja::SEMAKAN_AWAL_DATA)->no_rujukan,
        );
    }

    /**
     * No. Rujukan Laporan peringkat 3.1 dimasukkan oleh PPR juga — Pegawai
     * Analisis memiliki peringkat itu tetapi bukan nombor rujukannya.
     */
    public function test_no_rujukan_laporan_dimasukkan_oleh_ppr_bukan_pa(): void
    {
        $this->lalui(AliranKerja::ANALISIS_INVENTORI);

        $this->actingAs($this->pa)
            ->post(route('kemajuan.rujukan', [self::ALPHA, AliranKerja::ANALISIS_INVENTORI]), [
                'no_rujukan' => 'CUBAAN/2026/001',
            ])
            ->assertForbidden();

        $this->actingAs($this->ppr)
            ->post(route('kemajuan.rujukan', [self::ALPHA, AliranKerja::ANALISIS_INVENTORI]), [
                'no_rujukan' => 'LAP/2026/004',
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertSame(
            'LAP/2026/004',
            app(KemajuanAnalisisService::class)
                ->peringkat(self::ALPHA)
                ->get(AliranKerja::ANALISIS_INVENTORI)->no_rujukan,
        );
    }

    /**
     * Borangnya mesti benar-benar dipaparkan kepada PPR pada SETIAP peringkat
     * berujukan — tanpa ini medan itu wujud dalam pangkalan data tetapi tiada
     * jalan mengisinya.
     */
    public function test_ppr_melihat_borang_setiap_no_rujukan(): void
    {
        $this->lalui(AliranKerja::ANALISIS_INVENTORI);

        $paparan = $this->actingAs($this->ppr)
            ->get(route('workflow.show', self::ALPHA))
            ->assertOk();

        foreach (['1.1', '1.2', '1.3', '3.1'] as $kunci) {
            $paparan
                ->assertSee(route('kemajuan.rujukan', [self::ALPHA, $kunci]), false)
                ->assertSee(AliranKerja::labelRujukan($kunci));
        }
    }

    /**
     * PPR TIDAK memperoleh apa-apa kuasa lain daripada peringkat berujukan:
     * merekod nombor rujukan ialah keseluruhan tanggungjawabnya.
     */
    public function test_ppr_tiada_kuasa_selain_no_rujukan(): void
    {
        $this->lalui(AliranKerja::ANALISIS_INVENTORI);

        foreach (AliranKerja::semasa() as $kunci) {
            $this->actingAs($this->ppr)
                ->post(route('kemajuan.selesai', [self::ALPHA, $kunci]))
                ->assertForbidden();

            $this->actingAs($this->ppr)
                ->post(route('kemajuan.simpan', [self::ALPHA, $kunci]))
                ->assertForbidden();
        }
    }

    public function test_peringkat_tanpa_no_rujukan_menolak_laluan_rujukan(): void
    {
        $this->sediakanEntiti();

        $this->actingAs($this->ppr)
            ->post(route('kemajuan.rujukan', [self::ALPHA, AliranKerja::PENYEDIAAN_DATA]), [
                'no_rujukan' => 'X/2026/001',
            ])
            ->assertNotFound();
    }

    /*
    |--------------------------------------------------------------------------
    | Peringkat fasa akan datang
    |--------------------------------------------------------------------------
    */

    public function test_peringkat_fasa_akan_datang_tidak_menerima_sebarang_tindakan(): void
    {
        $this->lalui(AliranKerja::ANALISIS_INVENTORI);

        $this->actingAs($this->pa)
            ->post(route('kemajuan.selesai', [self::ALPHA, AliranKerja::ANALISIS_INVENTORI]))
            ->assertRedirect();

        foreach (AliranKerja::akanDatang() as $kunci) {
            foreach ([$this->pa, $this->ppa, $this->kb, $this->ppr] as $pengguna) {
                $this->actingAs($pengguna)
                    ->post(route('kemajuan.selesai', [self::ALPHA, $kunci]))
                    ->assertNotFound();

                $this->actingAs($pengguna)
                    ->post(route('kemajuan.simpan', [self::ALPHA, $kunci]))
                    ->assertNotFound();
            }

            $this->assertSame(WorkflowStageStatus::BELUM_MULA, $this->statusPeringkat($kunci));
        }
    }

    public function test_servis_menolak_peringkat_fasa_akan_datang(): void
    {
        $this->lalui(AliranKerja::ANALISIS_INVENTORI);

        $ralat = app(KemajuanAnalisisService::class)
            ->ralatPeringkat(self::ALPHA, AliranKerja::PENJANAAN_LAPORAN);

        $this->assertNotNull($ralat);
        $this->assertStringContainsString('belum dibina', $ralat);
    }

    public function test_peringkat_tidak_dikenali_menghasilkan_404(): void
    {
        $this->sediakanEntiti();

        $this->actingAs($this->pa)
            ->post(route('kemajuan.selesai', [self::ALPHA, '9.9']))
            ->assertNotFound();
    }

    /*
    |--------------------------------------------------------------------------
    | Borang Analisis Inventori Kriptografi — kerja peringkat 3.1
    |--------------------------------------------------------------------------
    */

    public function test_membuka_borang_menandakan_peringkat_analisis_dalam_proses(): void
    {
        $this->lalui(AliranKerja::ANALISIS_INVENTORI);

        $this->assertSame(
            WorkflowStageStatus::BELUM_MULA,
            $this->statusPeringkat(AliranKerja::ANALISIS_INVENTORI),
        );

        $this->actingAs($this->pa)
            ->get(route('analisis.borang', ['sector_code' => self::SEKTOR, 'agency_code' => self::ALPHA]))
            ->assertOk();

        $this->assertSame(
            WorkflowStageStatus::DALAM_PROSES,
            $this->statusPeringkat(AliranKerja::ANALISIS_INVENTORI),
        );
    }

    public function test_membuka_borang_sebelum_peringkat_terbuka_tidak_menggerakkan_peringkat(): void
    {
        $this->sediakanEntiti();

        $this->actingAs($this->pa)
            ->get(route('analisis.borang', ['sector_code' => self::SEKTOR, 'agency_code' => self::ALPHA]))
            ->assertOk();

        $this->assertSame(
            WorkflowStageStatus::BELUM_MULA,
            $this->statusPeringkat(AliranKerja::ANALISIS_INVENTORI),
        );
    }

    public function test_simpan_dapatan_menyimpan_analisis_dan_kembali_ke_kemajuan(): void
    {
        $this->lalui(AliranKerja::ANALISIS_INVENTORI);

        $this->actingAs($this->pa)
            ->post(route('analisis.simpan'), $this->dapatan())
            ->assertRedirect(route('workflow.show', self::ALPHA))
            ->assertSessionHas('success');

        $analisis = AnalisisInventori::where('agency_code', self::ALPHA)->firstOrFail();

        $this->assertTrue($analisis->selesai);
        $this->assertSame('R-LP-MIG-4-0007-V1.0', $analisis->kod_rujukan);

        // Menyimpan dapatan TIDAK menutup peringkat: penutupan ialah tindakan
        // "Selesai" yang berasingan pada halaman kemajuan.
        $this->assertNotSame(
            WorkflowStageStatus::SELESAI,
            $this->statusPeringkat(AliranKerja::ANALISIS_INVENTORI),
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Muat turun laporan
    |--------------------------------------------------------------------------
    */

    public function test_muat_turun_ditolak_sebelum_peringkat_analisis_selesai(): void
    {
        $this->lalui(AliranKerja::ANALISIS_INVENTORI);

        $this->actingAs($this->pa)->post(route('analisis.simpan'), $this->dapatan());

        $analisis = AnalisisInventori::where('agency_code', self::ALPHA)->firstOrFail();

        $this->actingAs($this->pa)
            ->get(route('laporan.unduh', $analisis))
            ->assertForbidden();
    }

    /*
    |--------------------------------------------------------------------------
    | Paparan
    |--------------------------------------------------------------------------
    */

    public function test_halaman_kemajuan_memaparkan_lima_peringkat_utama(): void
    {
        $this->sediakanEntiti();

        $paparan = $this->actingAs($this->ppa)
            ->get(route('workflow.show', self::ALPHA))
            ->assertOk();

        // Nama peringkat mengandungi '&', jadi ia disemak dalam bentuk yang
        // TELAH dilepaskan (assertSee melepaskan needle secara lalai).
        foreach (AliranKerja::UTAMA as $nama) {
            $paparan->assertSee($nama);
        }

        foreach (AliranKerja::kekunci() as $kunci) {
            $paparan->assertSee(AliranKerja::label($kunci));
        }

        // Peringkat yang belum dibina ditandakan sebagai tempat yang
        // dikhaskan, bukan sebagai kerja yang tertunggak.
        $paparan->assertSee('Belum dibina');
    }

    public function test_bar_tindakan_memaparkan_peringkat_milik_pengguna_sahaja(): void
    {
        $this->lalui(AliranKerja::PENDAFTARAN_DATA);

        // PPA memiliki 1.2 — tindakannya muncul.
        $this->actingAs($this->ppa)
            ->get(route('workflow.show', self::ALPHA))
            ->assertOk()
            ->assertSee(route('kemajuan.selesai', [self::ALPHA, AliranKerja::PENDAFTARAN_DATA]), false);

        // PA tidak memiliki 1.2, dan 1.3 belum terbuka — tiada tindakan.
        $this->actingAs($this->pa)
            ->get(route('workflow.show', self::ALPHA))
            ->assertOk()
            ->assertDontSee(route('kemajuan.selesai', [self::ALPHA, AliranKerja::PENDAFTARAN_DATA]), false)
            ->assertDontSee(route('kemajuan.selesai', [self::ALPHA, AliranKerja::SEMAKAN_AWAL_DATA]), false);
    }

    public function test_ppr_melihat_borang_no_rujukan_sahaja(): void
    {
        $this->sediakanEntiti();

        $paparan = $this->actingAs($this->ppr)
            ->get(route('workflow.show', self::ALPHA))
            ->assertOk()
            ->assertSee(route('kemajuan.rujukan', [self::ALPHA, AliranKerja::PENERIMAAN_DATA]), false);

        // Tiada tindakan peringkat langsung — bukan pada satu peringkat pun.
        foreach (AliranKerja::semasa() as $kunci) {
            $paparan
                ->assertDontSee(route('kemajuan.selesai', [self::ALPHA, $kunci]), false)
                ->assertDontSee(route('kemajuan.simpan', [self::ALPHA, $kunci]), false);
        }
    }

    public function test_maklumat_peringkat_dipaparkan_selepas_direkod(): void
    {
        $this->lalui(AliranKerja::PENDAFTARAN_DATA);

        $this->actingAs($this->ppa)
            ->post(route('kemajuan.simpan', [self::ALPHA, AliranKerja::PENDAFTARAN_DATA]), [
                'status_borang' => 'Tidak Boleh Diteruskan',
            ]);

        $this->actingAs($this->ppa)
            ->get(route('workflow.show', self::ALPHA))
            ->assertOk()
            ->assertSee('Tidak Boleh Diteruskan');
    }

    /*
    |--------------------------------------------------------------------------
    | Jejak audit
    |--------------------------------------------------------------------------
    */

    public function test_setiap_tindakan_peringkat_direkodkan_dalam_jejak_audit(): void
    {
        $this->lalui(AliranKerja::PENDAFTARAN_DATA);

        $this->actingAs($this->ppa)
            ->post(route('kemajuan.simpan', [self::ALPHA, AliranKerja::PENDAFTARAN_DATA]), [
                'tarikh_terima' => '2026-08-14',
            ]);

        $this->actingAs($this->ppr)
            ->post(route('kemajuan.rujukan', [self::ALPHA, AliranKerja::PENDAFTARAN_DATA]), [
                'no_rujukan' => 'BPFD/2026/002',
            ]);

        $this->actingAs($this->ppa)
            ->post(route('kemajuan.selesai', [self::ALPHA, AliranKerja::PENDAFTARAN_DATA]));

        foreach ([
            KemajuanAnalisisService::ACTION_STAGE_DATA_SAVED,
            KemajuanAnalisisService::ACTION_STAGE_REFERENCE_SAVED,
            KemajuanAnalisisService::ACTION_STAGE_STATUS_CHANGED,
        ] as $tindakan) {
            $this->assertDatabaseHas('activity_log', [
                'agency_code' => self::ALPHA,
                'action' => $tindakan,
            ]);
        }

        $this->actingAs($this->ppa)
            ->get(route('workflow.show', self::ALPHA))
            ->assertOk()
            ->assertSee('1.2 Pendaftaran Data');
    }

    /*
    |--------------------------------------------------------------------------
    | Status Tiga Laporan
    |--------------------------------------------------------------------------
    */

    public function test_status_tiga_laporan_mengikut_kemajuan_peringkat(): void
    {
        $this->lalui(AliranKerja::ANALISIS_INVENTORI);

        $status = app(StatusTigaLaporanService::class)->untukEntiti(self::ALPHA);

        $this->assertSame(StatusLaporan::PAPARAN_DALAM_PROSES, $status['inventori']['status']);

        $this->actingAs($this->pa)
            ->post(route('kemajuan.selesai', [self::ALPHA, AliranKerja::ANALISIS_INVENTORI]));

        $status = app(StatusTigaLaporanService::class)->untukEntiti(self::ALPHA);

        $this->assertSame(StatusLaporan::PAPARAN_SELESAI, $status['inventori']['status']);
    }

    /**
     * Ketiga-tiga jenis laporan wujud dalam seni bina; dua daripadanya belum
     * mempunyai proses, jadi ia dipaparkan sebagai N/A dan bukan sebagai
     * kerja yang tertunggak.
     */
    public function test_ketiga_tiga_jenis_laporan_wujud_dengan_dua_belum_tersedia(): void
    {
        $this->lalui(AliranKerja::ANALISIS_INVENTORI);

        $status = app(StatusTigaLaporanService::class)->untukEntiti(self::ALPHA);

        $this->assertSame(['inventori', 'risiko', 'kesiapsiagaan'], array_keys($status));

        $this->assertSame(StatusLaporan::PAPARAN_TIADA, $status['risiko']['status']);
        $this->assertSame(StatusLaporan::PAPARAN_TIADA, $status['kesiapsiagaan']['status']);
    }
}
