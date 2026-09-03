<?php

namespace Tests\Feature;

use App\Models\EntitiAssignment;
use App\Models\User;
use App\Models\WorkflowStageStatus;
use App\Services\DashboardStatistikService;
use App\Services\EntityAssignmentService;
use App\Services\KemajuanAnalisisService;
use App\Support\AliranKerja;
use App\Support\SektorDirectory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\MelaluiAliranKerja;
use Tests\TestCase;

/**
 * Tiga kad ringkasan entiti pada papan pemuka — satu corong:
 *
 *     SEMUA ENTITI
 *         |
 *         v
 *     ENTITI DITERIMA          (Buku Kerja MPQ diterima)
 *         |
 *         +--> ENTITI DALAM PROSES  (didaftar + PA ditugaskan, peringkat 5
 *         |                          BELUM Selesai)
 *         +--> ENTITI SELESAI       (peringkat 5 Selesai)
 *
 * Penyebutnya SENGAJA berbeza: kad 1 diukur terhadap keseluruhan entiti
 * (liputan), kad 2 dan 3 terhadap Entiti Diterima (kemajuan).
 *
 * Setiap angka dikira daripada rekod sebenar — tiada nilai tetap.
 */
class DashboardKadEntitiTest extends TestCase
{
    use MelaluiAliranKerja, RefreshDatabase;

    private const ALPHA = 'A010101';

    private const BETA = 'A010102';

    private const GAMMA = 'A010103';

    private const DELTA = 'A010104';

    private User $kb;

    private User $ppa;

    private User $pa;

    protected function setUp(): void
    {
        parent::setUp();

        $this->kb = User::factory()->create(['role' => User::ROLE_KETUA_BAHAGIAN]);
        $this->ppa = User::factory()->create(['role' => User::ROLE_COORDINATOR]);
        $this->pa = User::factory()->create(['role' => User::ROLE_ANALYST]);
    }

    /*
    |--------------------------------------------------------------------------
    | Fikstur
    |--------------------------------------------------------------------------
    */

    private function kemajuan(): KemajuanAnalisisService
    {
        return app(KemajuanAnalisisService::class);
    }

    /**
     * Buku Kerja MPQ diterima: Tarikh Terima + Status Borang Penerimaan Data,
     * direkod oleh KB atau PPA melalui laluan servis sebenar.
     *
     * @param  array<string, string>  $data
     */
    private function terima(string $agencyCode, ?User $oleh = null, ?array $data = null): void
    {
        $this->kemajuan()->lengkapkanPenerimaan(
            SektorDirectory::cariEntiti($agencyCode),
            $oleh ?? $this->kb,
            $data ?? [
                AliranKerja::MEDAN_TARIKH_TERIMA => '2026-08-14',
                AliranKerja::MEDAN_STATUS_BORANG => 'Selesai',
            ],
        );
    }

    /** Pendaftaran Data: Tarikh Daftar + Status Borang Pendaftaran Data. */
    private function daftar(string $agencyCode): void
    {
        $this->kemajuan()->simpanData($agencyCode, AliranKerja::PENDAFTARAN_DATA, [
            AliranKerja::MEDAN_TARIKH_DAFTAR => '2026-08-20',
            AliranKerja::MEDAN_STATUS_BORANG => 'Selesai',
        ], $this->ppa);
    }

    private function tugaskanPA(string $agencyCode, ?User $pegawai = null): void
    {
        app(EntityAssignmentService::class)->assign(
            SektorDirectory::cariEntiti($agencyCode),
            $pegawai ?? $this->pa,
            $this->ppa,
        );
    }

    /**
     * Peringkat 5 ditanda Selesai secara terus.
     *
     * Modul peringkat 5 belum dibina — KemajuanAnalisisService menolak
     * sebarang tindakan pada peringkat fasa akan datang — jadi barisnya
     * ditulis di sini supaya kad boleh diuji sekarang dan tetap betul apabila
     * modul itu tiba.
     */
    private function siapkanPeringkatLima(string $agencyCode): void
    {
        WorkflowStageStatus::query()
            ->forAgency($agencyCode)
            ->atStage(AliranKerja::SEMAKAN_KELULUSAN)
            ->update(['status' => WorkflowStageStatus::SELESAI]);
    }

    /**
     * @return array<string, mixed>
     */
    private function kira(?User $pengguna = null): array
    {
        return app(DashboardStatistikService::class)->kira($pengguna ?? $this->kb);
    }

    private function jumlahEntiti(): int
    {
        return SektorDirectory::semuaEntiti()->count();
    }

    /*
    |--------------------------------------------------------------------------
    | Kad 1 — Entiti Diterima
    |--------------------------------------------------------------------------
    */

    public function test_entiti_diterima_dikira_terhadap_keseluruhan_entiti(): void
    {
        $this->terima(self::ALPHA);
        $this->terima(self::BETA);

        $statistik = $this->kira();

        $jumlah = $this->jumlahEntiti();

        $this->assertSame(2, $statistik['entitiDiterima']);
        $this->assertSame($jumlah, $statistik['jumlahEntiti']);
        $this->assertSame(
            round((2 / $jumlah) * 100, 1),
            $statistik['peratusEntitiDiterima'],
        );
    }

    /**
     * Contoh spesifikasi: 2 daripada 252 entiti = 0.79%.
     *
     * Pembundaran integer akan melaporkannya sebagai "1%" — lebih daripada
     * yang sebenarnya ada — jadi kad corong entiti membawa satu tempat
     * perpuluhan.
     */
    public function test_dua_daripada_dua_ratus_lima_puluh_dua_memberi_sifar_perpuluhan_lapan(): void
    {
        $this->terima(self::ALPHA);
        $this->terima(self::BETA);

        $statistik = $this->kira();

        $this->assertSame(252, $statistik['jumlahEntiti']);
        $this->assertSame(2, $statistik['entitiDiterima']);
        $this->assertSame(0.8, $statistik['peratusEntitiDiterima']);
        $this->assertSame('0.8', \App\Support\Peratus::kad($statistik['peratusEntitiDiterima']));
    }

    /** Nilai bulat tidak membawa ".0" yang palsu tepat. */
    public function test_peratusan_bulat_dipaparkan_tanpa_perpuluhan(): void
    {
        foreach ([self::ALPHA, self::BETA, self::GAMMA, self::DELTA] as $kod) {
            $this->terima($kod);
        }

        $this->daftar(self::ALPHA);
        $this->tugaskanPA(self::ALPHA);

        $statistik = $this->kira();

        $this->assertSame(25.0, $statistik['peratusEntitiDalamProses']);
        $this->assertSame('25', \App\Support\Peratus::kad($statistik['peratusEntitiDalamProses']));
    }

    public function test_tarikh_terima_sahaja_belum_dikira_diterima(): void
    {
        $this->terima(self::ALPHA, data: [AliranKerja::MEDAN_TARIKH_TERIMA => '2026-08-14']);

        $this->assertSame(0, $this->kira()['entitiDiterima']);
    }

    public function test_status_borang_sahaja_belum_dikira_diterima(): void
    {
        $this->terima(self::ALPHA, data: [AliranKerja::MEDAN_STATUS_BORANG => 'Selesai']);

        $this->assertSame(0, $this->kira()['entitiDiterima']);
    }

    public function test_kedua_dua_medan_menjadikan_entiti_diterima(): void
    {
        $this->terima(self::ALPHA);

        $this->assertSame(1, $this->kira()['entitiDiterima']);
    }

    /**
     * Peringkat 1.1 dimiliki KB DAN PPA — kedua-duanya merekod medan yang
     * sama, jadi kedua-duanya menjadikan entiti "Diterima".
     */
    public function test_kb_dan_ppa_kedua_duanya_boleh_menjadikan_entiti_diterima(): void
    {
        $this->terima(self::ALPHA, $this->kb);
        $this->terima(self::BETA, $this->ppa);

        $this->assertSame(2, $this->kira()['entitiDiterima']);
    }

    /**
     * No. Rujukan milik PPR TIDAK diperlukan: entiti yang Buku Kerja MPQ-nya
     * telah diterima tidak boleh tercicir daripada kad kerana menunggu
     * pegawai lain. Kad ini SENGAJA lebih longgar daripada status peringkat
     * 1.1 "Selesai".
     */
    public function test_diterima_tidak_menunggu_no_rujukan_ppr(): void
    {
        $this->terima(self::ALPHA);

        $statistik = $this->kira();

        $this->assertSame(1, $statistik['entitiDiterima']);
        $this->assertSame(0, $statistik['pendaftaranSelesai']);
    }

    /*
    |--------------------------------------------------------------------------
    | Kad 2 — Entiti Dalam Proses
    |--------------------------------------------------------------------------
    */

    public function test_diterima_tanpa_pendaftaran_belum_dalam_proses(): void
    {
        $this->terima(self::ALPHA);

        $statistik = $this->kira();

        $this->assertSame(1, $statistik['entitiDiterima']);
        $this->assertSame(0, $statistik['entitiDalamProses']);
    }

    public function test_pendaftaran_tanpa_pegawai_analisis_belum_dalam_proses(): void
    {
        $this->terima(self::ALPHA);
        $this->daftar(self::ALPHA);

        $this->assertSame(0, $this->kira()['entitiDalamProses']);
    }

    public function test_pendaftaran_dengan_pegawai_analisis_menjadi_dalam_proses(): void
    {
        $this->terima(self::ALPHA);
        $this->daftar(self::ALPHA);
        $this->tugaskanPA(self::ALPHA);

        $this->assertSame(1, $this->kira()['entitiDalamProses']);
    }

    public function test_entiti_yang_bergerak_ke_peringkat_kemudian_kekal_dalam_proses(): void
    {
        $this->terima(self::ALPHA);
        $this->daftar(self::ALPHA);
        $this->tugaskanPA(self::ALPHA);

        $this->kemajuan()->simpanData(self::ALPHA, AliranKerja::SEMAKAN_AWAL_DATA, [
            AliranKerja::MEDAN_TARIKH_SEMAKAN => '2026-08-25',
            AliranKerja::MEDAN_STATUS_BORANG => 'Selesai',
        ], $this->pa);

        $this->assertSame(1, $this->kira()['entitiDalamProses']);
    }

    public function test_peringkat_5_selesai_mengeluarkan_entiti_daripada_dalam_proses(): void
    {
        $this->terima(self::ALPHA);
        $this->daftar(self::ALPHA);
        $this->tugaskanPA(self::ALPHA);

        $sebelum = $this->kira();
        $this->assertSame(1, $sebelum['entitiDalamProses']);
        $this->assertSame(0, $sebelum['entitiSelesai']);

        $this->siapkanPeringkatLima(self::ALPHA);

        $selepas = $this->kira();
        $this->assertSame(0, $selepas['entitiDalamProses']);
        $this->assertSame(1, $selepas['entitiSelesai']);
    }

    public function test_penyebut_kad_dua_ialah_entiti_diterima_bukan_keseluruhan(): void
    {
        // Empat diterima, satu daripadanya dalam proses.
        foreach ([self::ALPHA, self::BETA, self::GAMMA, self::DELTA] as $kod) {
            $this->terima($kod);
        }

        $this->daftar(self::ALPHA);
        $this->tugaskanPA(self::ALPHA);

        $statistik = $this->kira();

        $this->assertSame(4, $statistik['entitiDiterima']);
        $this->assertSame(1, $statistik['entitiDalamProses']);
        $this->assertSame(25.0, $statistik['peratusEntitiDalamProses']);
    }

    /*
    |--------------------------------------------------------------------------
    | Kad 3 — Entiti Selesai
    |--------------------------------------------------------------------------
    */

    public function test_peringkat_5_belum_selesai_bukan_entiti_selesai(): void
    {
        $this->terima(self::ALPHA);
        $this->daftar(self::ALPHA);
        $this->tugaskanPA(self::ALPHA);

        $this->assertSame(0, $this->kira()['entitiSelesai']);
    }

    public function test_peringkat_5_selesai_menjadi_entiti_selesai(): void
    {
        $this->terima(self::ALPHA);
        $this->siapkanPeringkatLima(self::ALPHA);

        $this->assertSame(1, $this->kira()['entitiSelesai']);
    }

    public function test_penyebut_kad_tiga_ialah_entiti_diterima_bukan_keseluruhan(): void
    {
        foreach ([self::ALPHA, self::BETA, self::GAMMA, self::DELTA] as $kod) {
            $this->terima($kod);
        }

        $this->siapkanPeringkatLima(self::ALPHA);

        $statistik = $this->kira();

        $this->assertSame(4, $statistik['entitiDiterima']);
        $this->assertSame(1, $statistik['entitiSelesai']);
        $this->assertSame(25.0, $statistik['peratusEntitiSelesai']);
    }

    /*
    |--------------------------------------------------------------------------
    | Kes hujung
    |--------------------------------------------------------------------------
    */

    public function test_tiada_entiti_diterima_memberi_sifar_peratus_bukan_pembahagian_sifar(): void
    {
        $statistik = $this->kira();

        $this->assertSame(0, $statistik['entitiDiterima']);
        $this->assertSame(0, $statistik['entitiDalamProses']);
        $this->assertSame(0, $statistik['entitiSelesai']);
        $this->assertSame(0.0, $statistik['peratusEntitiDiterima']);
        $this->assertSame(0.0, $statistik['peratusEntitiDalamProses']);
        $this->assertSame(0.0, $statistik['peratusEntitiSelesai']);
    }

    /**
     * Pengguna tanpa satu pun entiti boleh diakses: penyebut kad 1 ialah
     * sifar, jadi setiap peratusan mesti 0 dan bukan NaN/Infinity.
     */
    public function test_tiada_entiti_langsung_dalam_skop_tidak_membahagi_dengan_sifar(): void
    {
        // Pegawai Analisis tanpa penugasan tiada satu pun entiti dalam skop.
        $statistik = $this->kira(User::factory()->create(['role' => User::ROLE_ANALYST]));

        $this->assertSame(0, $statistik['jumlahEntiti']);
        $this->assertSame(0, $statistik['entitiDiterima']);
        $this->assertSame(0.0, $statistik['peratusEntitiDiterima']);
        $this->assertSame(0.0, $statistik['peratusEntitiDalamProses']);
        $this->assertSame(0.0, $statistik['peratusEntitiSelesai']);
    }

    public function test_kesemua_entiti_diterima_memberi_seratus_peratus(): void
    {
        // Pegawai Analisis melihat entiti yang ditugaskan kepadanya SAHAJA,
        // jadi skopnya ialah satu entiti — dan entiti itu telah diterima.
        $this->terima(self::ALPHA);
        $this->daftar(self::ALPHA);
        $this->tugaskanPA(self::ALPHA);

        $statistik = $this->kira($this->pa->fresh());

        $this->assertSame(1, $statistik['jumlahEntiti']);
        $this->assertSame(1, $statistik['entitiDiterima']);
        $this->assertSame(100.0, $statistik['peratusEntitiDiterima']);
    }

    public function test_kesemua_entiti_diterima_yang_siap_memberi_seratus_dan_sifar_dalam_proses(): void
    {
        $this->terima(self::ALPHA);
        $this->daftar(self::ALPHA);
        $this->tugaskanPA(self::ALPHA);
        $this->siapkanPeringkatLima(self::ALPHA);

        $statistik = $this->kira();

        $this->assertSame(1, $statistik['entitiDiterima']);
        $this->assertSame(1, $statistik['entitiSelesai']);
        $this->assertSame(100.0, $statistik['peratusEntitiSelesai']);
        $this->assertSame(0, $statistik['entitiDalamProses']);
        $this->assertSame(0.0, $statistik['peratusEntitiDalamProses']);
    }

    /**
     * Sejarah penugasan menghasilkan BEBERAPA baris entiti_assignment bagi
     * satu entiti. Kad mesti mengira entiti, bukan baris.
     */
    public function test_penugasan_berbilang_tidak_menggelembungkan_kiraan(): void
    {
        $this->terima(self::ALPHA);
        $this->daftar(self::ALPHA);

        $this->tugaskanPA(self::ALPHA);
        $this->tugaskanPA(self::ALPHA, User::factory()->create(['role' => User::ROLE_ANALYST]));
        $this->tugaskanPA(self::ALPHA, User::factory()->create(['role' => User::ROLE_ANALYST]));

        $this->assertGreaterThan(
            1,
            EntitiAssignment::query()->forAgency(self::ALPHA)->count(),
            'Fikstur sepatutnya menghasilkan beberapa baris penugasan.',
        );

        $this->assertSame(1, $this->kira()['entitiDalamProses']);
    }

    /**
     * "Set Semula" Ketua Bahagian mengosongkan Tarikh Terima dan Status
     * Borang, jadi entiti itu terkeluar daripada ketiga-tiga kad tanpa
     * peraturan tambahan.
     */
    public function test_set_semula_mengeluarkan_entiti_daripada_kad(): void
    {
        $this->terima(self::ALPHA);
        $this->assertSame(1, $this->kira()['entitiDiterima']);

        $this->kemajuan()->setSemula(self::ALPHA, $this->kb, 'Ujian');

        $this->assertSame(0, $this->kira()['entitiDiterima']);
    }

    /*
    |--------------------------------------------------------------------------
    | Paparan
    |--------------------------------------------------------------------------
    */

    public function test_papan_pemuka_memaparkan_tajuk_dan_nota_kad_yang_dikemas_kini(): void
    {
        $this->terima(self::ALPHA);

        $jumlah = $this->jumlahEntiti();

        $this->actingAs($this->kb)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Entiti Diterima')
            ->assertSee('Buku Kerja MPQ Diterima')
            ->assertSee('Entiti Dalam Proses')
            ->assertSee('Entiti Selesai')
            ->assertSee("1 daripada {$jumlah} entiti")
            ->assertSee('0 daripada 1 entiti diterima')
            ->assertDontSee('Entiti Selesai Pendaftaran')
            ->assertDontSee('entiti selesai pendaftaran');
    }

    /*
    |--------------------------------------------------------------------------
    | Kad vs carta — DUA ukuran "selesai" yang berlainan
    |--------------------------------------------------------------------------
    */

    /**
     * Kad "Entiti Selesai" (peringkat 5) dan carta Kemajuan Analisis (kesemua
     * peringkat fasa semasa, berakhir pada 3.1) mengukur perkara yang
     * BERLAINAN, dan mesti kekal begitu.
     *
     * Entiti yang telah menamatkan fasa semasa muncul sebagai "Siap" pada
     * carta tetapi BUKAN sebagai "Entiti Selesai" pada kad — modul peringkat
     * 5 belum dibina, jadi tiada entiti boleh menyeberanginya dalam fasa ini.
     */
    public function test_entiti_siap_fasa_semasa_bukan_entiti_selesai_peringkat_5(): void
    {
        $this->lengkapkanFasaSemasa(self::ALPHA, $this->ppa);

        $statistik = $this->kira();

        // Carta: Kemajuan Analisis fasa semasa tamat.
        $this->assertSame(1, $statistik['selesai']);

        // Kad: peringkat 5 masih belum Selesai.
        $this->assertSame(0, $statistik['entitiSelesai']);
        $this->assertSame(0.0, $statistik['peratusEntitiSelesai']);
    }

    /**
     * Perkataan pada skrin mesti membezakan kedua-dua ukuran itu: carta
     * menggunakan "Siap", kad menggunakan "Selesai".
     */
    public function test_carta_menggunakan_perkataan_siap_bukan_selesai(): void
    {
        $this->lengkapkanFasaSemasa(self::ALPHA, $this->ppa);

        $this->actingAs($this->kb)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Entiti Siap Kemajuan Analisis Mengikut Sektor')
            ->assertSee('Entiti Selesai')
            ->assertDontSee('Entiti Selesai Kemajuan Analisis Mengikut Sektor')
            // Sarikata menyatakan di mana fasa semasa berakhir, diambil
            // daripada AliranKerja dan bukan ditulis tetap.
            ->assertSee(\App\Support\AliranKerja::labelPenuh(\App\Support\AliranKerja::TERAKHIR_SEMASA));
    }

    public function test_bar_kemajuan_menggunakan_peratusan_kadnya_sendiri(): void
    {
        foreach ([self::ALPHA, self::BETA, self::GAMMA, self::DELTA] as $kod) {
            $this->terima($kod);
        }

        $this->daftar(self::ALPHA);
        $this->tugaskanPA(self::ALPHA);
        $this->siapkanPeringkatLima(self::BETA);

        $statistik = $this->kira();

        $html = $this->actingAs($this->kb)->get(route('dashboard'))->assertOk()->getContent();

        // Kad 2 dan kad 3: 1 daripada 4 entiti diterima = 25%.
        $this->assertSame(25.0, $statistik['peratusEntitiDalamProses']);
        $this->assertSame(25.0, $statistik['peratusEntitiSelesai']);

        $this->assertStringContainsString(
            'width: '.$statistik['peratusEntitiDiterima'].'%',
            $html,
        );
        $this->assertStringContainsString('is-warning" style="width: 25%', $html);
        $this->assertStringContainsString('is-success" style="width: 25%', $html);
    }
}
