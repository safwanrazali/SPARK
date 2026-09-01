<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\AnalisisInventori;
use App\Models\LaporanSemakan;
use App\Models\StatusLaporan;
use App\Models\User;
use App\Models\WorkflowStatus;
use App\Services\DashboardStatistikService;
use App\Services\EntityAssignmentService;
use App\Services\KemajuanAnalisisService;
use App\Services\LaporanSemakanService;
use App\Support\AliranKerja;
use App\Support\SektorDirectory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\MelaluiAliranKerja;
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
    use MelaluiAliranKerja, RefreshDatabase;

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
     *
     * Status mengikut peraturan yang ditulis oleh
     * KemajuanAnalisisService::selaraskanKedudukan: entiti hanya melepasi
     * peringkat 1 setelah pendaftarannya Selesai, dan sejak itu ia berstatus
     * 'Dalam Proses' sehingga kesemua tujuh peringkat Selesai.
     */
    private function workflow(string $agencyCode, int $peringkat, ?string $tarikh = null): WorkflowStatus
    {
        return WorkflowStatus::factory()
            ->onStage($peringkat)
            ->create(SektorDirectory::cariEntiti($agencyCode) + [
                'status' => $peringkat > WorkflowStatus::FIRST_STAGE
                    ? DashboardStatistikService::STATUS_DALAM_PROSES
                    : WorkflowStatus::DEFAULT_STATUS,
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

    private function laporanSemakan(string $agencyCode, string $jenis, string $status): LaporanSemakan
    {
        return LaporanSemakan::create(SektorDirectory::cariEntiti($agencyCode) + [
            'report_type' => $jenis,
            'status' => $status,
        ]);
    }

    /**
     * Serahkan laporan yang telah disahkan kepada NACSA — tindakan "Hantar"
     * peringkat 07. Melalui servis sebenar, jadi jejak `report_delivered`
     * yang dibaca papan pemuka ditulis oleh kod pengeluaran.
     */
    private function serahkanKepadaNacsa(string $agencyCode, string $jenis = 'inventori'): LaporanSemakan
    {
        $laporan = $this->laporanSemakan($agencyCode, $jenis, LaporanSemakan::SAH);

        app(LaporanSemakanService::class)->rekodPenyerahan($laporan, $this->coordinator);

        return $laporan;
    }

    /**
     * Daftarkan entiti melalui aliran sebenar — peringkat 01 Selesai.
     */
    private function daftarkan(string $agencyCode): void
    {
        app(KemajuanAnalisisService::class)->lengkapkanPenerimaan(
            SektorDirectory::cariEntiti($agencyCode),
            $this->coordinator,
            ['tarikh_terima' => '2026-08-14', 'status_borang' => 'Selesai', 'no_rujukan' => 'FIKSTUR/1.1'],
        );
    }

    /**
     * Daftarkan entiti dan tandakan KESEMUA peringkat fasa semasa Selesai —
     * satu-satunya cara entiti benar-benar menjadi 'Siap'.
     *
     * Peringkat 3.2, 4 dan 5 sengaja tidak disentuh: ia belum dibina, jadi
     * menuntutnya akan menjadikan 'Siap' mustahil dicapai.
     */
    private function siapkanSemuaPeringkat(string $agencyCode): void
    {
        $this->daftarkan($agencyCode);

        foreach (AliranKerja::semasa() as $stage) {
            $this->siapkanPeringkat($agencyCode, $stage, $this->coordinator);
        }
    }

    /**
     * Tandakan peringkat fasa semasa Selesai sehingga $hingga (eksklusif).
     */
    private function peringkatHingga(string $agencyCode, string $hingga): void
    {
        $this->daftarkan($agencyCode);

        foreach (AliranKerja::semasa() as $stage) {
            if ($stage === $hingga) {
                return;
            }

            $this->siapkanPeringkat($agencyCode, $stage, $this->coordinator);
        }
    }

    /**
     * Bilangan entiti dalam senarai induk — penyebut setiap peratusan
     * papan pemuka. Dikira daripada config supaya ujian tidak pecah apabila
     * senarai induk dikemas kini.
     */
    private function jumlahSenaraiInduk(?string $sektor = null): int
    {
        return $sektor === null
            ? SektorDirectory::semuaEntiti()->count()
            : SektorDirectory::entitiDalamSektor($sektor)->count();
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

    public function test_pegawai_analisis_ditolak_daripada_papan_pemuka(): void
    {
        $this->actingAs($this->analyst)
            ->get(route('dashboard'))
            ->assertForbidden();
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

        // Jumlah Entiti ialah keseluruhan senarai induk, bukan hanya
        // entiti yang telah disentuh.
        $this->assertSame($this->jumlahSenaraiInduk(), $statistik['jumlahEntiti']);
        $this->assertSame(3, $statistik['jumlahDipantau']);
        $this->assertSame(2, $statistik['dalamProses']);
        $this->assertSame(1, $statistik['selesai']);
        $this->assertSame(0, $statistik['belumDidaftar']);
    }

    public function test_entiti_tanpa_rekod_workflow_dikira_belum_didaftar(): void
    {
        $this->workflow(self::ALPHA, 3);

        // Entiti ini dipantau melalui status laporan sahaja.
        $this->statusLaporan(self::BETA, 'inventori', 'Dalam Proses');

        $statistik = $this->kira();

        $this->assertSame($this->jumlahSenaraiInduk(), $statistik['jumlahEntiti']);
        $this->assertSame(2, $statistik['jumlahDipantau']);
        $this->assertSame(1, $statistik['dalamProses']);
        $this->assertSame(0, $statistik['selesai']);
        $this->assertSame(1, $statistik['belumDidaftar']);
    }

    /**
     * "Set Semula" Ketua Bahagian menarik entiti keluar daripada aliran
     * kerja, jadi angka papan pemuka mesti turun bersamanya.
     *
     * Baris peringkat dan baris kedudukan sengaja dikekalkan oleh setSemula()
     * supaya jejak audit entiti itu kekal; tanpa penyingkiran eksplisit,
     * baris yang tertinggal itu terus dikira sebagai entiti "Dalam Proses"
     * dan terus menyumbang kepada Jumlah Laporan.
     */
    public function test_set_semula_entiti_menurunkan_kiraan_papan_pemuka(): void
    {
        $ppr = User::factory()->create(['role' => User::ROLE_PENYELARAS_REKOD]);
        $kb = User::factory()->create(['role' => User::ROLE_KETUA_BAHAGIAN]);

        $kemajuan = app(KemajuanAnalisisService::class);

        foreach ([self::ALPHA, self::BETA, self::GAMMA] as $kod) {
            $kemajuan->lengkapkanPenerimaan(SektorDirectory::cariEntiti($kod), $ppr, ['tarikh_terima' => '2026-08-14', 'status_borang' => 'Selesai', 'no_rujukan' => 'FIKSTUR/1.1']);
            $this->serahkanKepadaNacsa($kod);
        }

        $sebelum = $this->kira();

        $this->assertSame(3, $sebelum['jumlahDipantau']);
        $this->assertSame(3, $sebelum['dalamProses']);
        $this->assertSame(0, $sebelum['selesai']);
        $this->assertSame(3, $sebelum['jumlahLaporan']['inventori']);

        // Set Semula dipanggil melalui servis: skrin Penetapan Entiti yang
        // menghosnya telah dibuang, tetapi operasi domainnya kekal.
        app(KemajuanAnalisisService::class)->setSemula(self::GAMMA, $kb, 'Data tidak lengkap.');

        $selepas = $this->kira();

        $this->assertSame(2, $selepas['jumlahDipantau']);
        $this->assertSame(2, $selepas['dalamProses']);
        $this->assertSame(0, $selepas['selesai']);
        $this->assertSame(2, $selepas['jumlahLaporan']['inventori']);

        // Entiti itu bukan sekadar dipindahkan ke "belum didaftar" —
        // ia keluar sepenuhnya daripada skop pemantauan.
        $this->assertSame(0, $selepas['belumDidaftar']);
    }

    /**
     * Entiti yang TIDAK PERNAH didaftarkan tiada baris peringkat, jadi ia
     * mesti kekal dikira sebagai "belum didaftar" — bukan disingkirkan
     * bersama entiti yang ditetapkan semula.
     */
    public function test_entiti_belum_didaftar_tidak_tersingkir_oleh_peraturan_set_semula(): void
    {
        $ppr = User::factory()->create(['role' => User::ROLE_PENYELARAS_REKOD]);

        app(KemajuanAnalisisService::class)
            ->lengkapkanPenerimaan(SektorDirectory::cariEntiti(self::ALPHA), $ppr, ['tarikh_terima' => '2026-08-14', 'status_borang' => 'Selesai', 'no_rujukan' => 'FIKSTUR/1.1']);

        // Dipantau melalui status laporan sahaja — tiada baris peringkat.
        $this->statusLaporan(self::BETA, 'inventori', 'Dalam Proses');

        $statistik = $this->kira();

        $this->assertSame(2, $statistik['jumlahDipantau']);
        $this->assertSame(1, $statistik['dalamProses']);
        $this->assertSame(1, $statistik['belumDidaftar']);
    }

    /**
     * Carta "Kemajuan Keseluruhan" — taburan entiti merentas tiga keadaan
     * Kemajuan Analisis, dan bukan perbendaharaan kemajuan baharu.
     */
    public function test_taburan_kemajuan_merentas_tiga_keadaan(): void
    {
        // Sektor 010 mengandungi TIGA entiti dalam senarai induk. Dua
        // disentuh (1 siap, 1 dalam proses); yang ketiga tidak pernah
        // disentuh langsung dan mesti muncul sebagai "Belum Mula".
        $this->assertSame(3, $this->jumlahSenaraiInduk('010'));

        $this->workflowSiap('K100100');
        $this->workflow('A100101', 4);

        $statistik = $this->kira('010');
        $taburan = collect($statistik['kemajuanTaburan'])->keyBy('kunci');

        $this->assertSame(3, $statistik['jumlahEntiti']);
        $this->assertSame(2, $statistik['jumlahDipantau']);

        $this->assertCount(3, $taburan);

        $this->assertSame(KemajuanAnalisisService::KESELURUHAN_SIAP, $taburan['selesai']['label']);
        $this->assertSame(1, $taburan['selesai']['nilai']);
        $this->assertSame(33, $taburan['selesai']['peratus']);

        $this->assertSame(KemajuanAnalisisService::KESELURUHAN_DALAM_PROSES, $taburan['proses']['label']);
        $this->assertSame(1, $taburan['proses']['nilai']);
        $this->assertSame(33, $taburan['proses']['peratus']);

        // Entiti yang TIDAK PERNAH disentuh tetap dikira — inilah sebabnya
        // penyebutnya mesti keseluruhan senarai induk.
        $this->assertSame(KemajuanAnalisisService::KESELURUHAN_BELUM_MULA, $taburan['belum']['label']);
        $this->assertSame(1, $taburan['belum']['nilai']);
        $this->assertSame(33, $taburan['belum']['peratus']);

    }

    /**
     * Kad "Entiti Dalam Proses" dan "Entiti Selesai" diukur terhadap entiti
     * yang TELAH selesai "Penerimaan & Pendaftaran Data" — bukan terhadap
     * keseluruhan senarai induk. Entiti yang belum melepasi pintu masuk itu
     * belum boleh bergerak, jadi ia tidak layak menjadi penyebut.
     */
    public function test_peratus_kemajuan_diukur_terhadap_entiti_selesai_pendaftaran(): void
    {
        // Sektor 010: tiga entiti. Dua didaftarkan, satu daripadanya siap
        // sepenuhnya; entiti ketiga tidak pernah disentuh.
        $this->siapkanSemuaPeringkat('K100100');
        $this->daftarkan('A100101');

        $statistik = $this->kira('010');

        $this->assertSame(3, $statistik['jumlahEntiti']);
        $this->assertSame(2, $statistik['pendaftaranSelesai']);
        $this->assertSame(1, $statistik['selesai']);
        $this->assertSame(1, $statistik['dalamProses']);

        // Liputan pendaftaran: 2 daripada 3 entiti.
        $this->assertSame(67, $statistik['peratusPendaftaranSelesai']);

        // Kemajuan: 1 daripada 2 entiti yang telah selesai pendaftaran.
        $this->assertSame(50, $statistik['peratusDalamProses']);
        $this->assertSame(50, $statistik['peratusSelesai']);

        // Carta Kemajuan Keseluruhan kekal meliputi KESEMUA entiti, jadi
        // hirisannya tidak sama dengan kad — dan sengaja begitu.
        $taburan = collect($statistik['kemajuanTaburan'])->keyBy('kunci');
        $this->assertSame(33, $taburan['selesai']['peratus']); // 1 / 3
        $this->assertSame(1, $taburan['belum']['nilai']);
    }

    /**
     * Tiada entiti yang selesai pendaftaran — penyebut sifar tidak boleh
     * menghasilkan NaN atau Infinity.
     */
    public function test_peratus_kemajuan_sifar_apabila_tiada_pendaftaran_selesai(): void
    {
        $this->statusLaporan(self::ALPHA, 'inventori', 'Dalam Proses');

        $statistik = $this->kira();

        $this->assertSame(0, $statistik['pendaftaranSelesai']);
        $this->assertSame(0, $statistik['peratusDalamProses']);
        $this->assertSame(0, $statistik['peratusSelesai']);
    }

    /**
     * Carta "Entiti Selesai Kemajuan Analisis Mengikut Sektor".
     *
     * Gelang membahagikan KESELURUHAN entiti kepada sektornya — `jumlah`
     * ialah saiz hirisan — manakala `selesai` dan `peratus` melaporkan kadar
     * siap dalam sektor itu sendiri.
     */
    public function test_entiti_disenaraikan_bagi_setiap_sektor(): void
    {
        $this->workflowSiap(self::ALPHA);   // sektor 001
        $this->workflowSiap(self::BETA);    // sektor 001
        $this->workflow(self::GAMMA, 4);    // sektor 001, belum siap
        $this->workflowSiap(self::DELTA);   // sektor 010

        $statistik = $this->kira();
        $mengikutSektor = collect($statistik['selesaiMengikutSektor'])->keyBy('kod');

        // Kesebelas-sebelas sektor hadir, mengikut susunan senarai induk.
        $this->assertSame(array_map('strval', array_keys(config('sektor'))), $mengikutSektor->keys()->all());

        // Saiz hirisan = bilangan entiti sektor itu; semuanya berjumlah
        // keseluruhan entiti, jadi gelang menutup pada 100%.
        foreach (config('sektor') as $kod => $sektor) {
            $this->assertSame(count($sektor['agencies']), $mengikutSektor[(string) $kod]['jumlah']);
        }

        $this->assertSame(
            $this->jumlahSenaraiInduk(),
            collect($statistik['selesaiMengikutSektor'])->sum('jumlah'),
        );

        $this->assertSame(2, $mengikutSektor['001']['selesai']);
        $this->assertSame(1, $mengikutSektor['010']['selesai']);

        // Sektor tanpa entiti selesai kekal disenaraikan pada sifar.
        $this->assertSame(0, $mengikutSektor['005']['selesai']);
        $this->assertSame(0, $mengikutSektor['005']['peratus']);
    }

    /**
     * Peratusan setiap sektor ialah kadar siap DALAM sektor itu.
     */
    public function test_peratus_sektor_ialah_kadar_siap_dalam_sektor_itu(): void
    {
        // Sektor 010 mempunyai tiga entiti; satu daripadanya siap.
        $this->workflowSiap('K100100');

        $sektor010 = collect($this->kira()['selesaiMengikutSektor'])->firstWhere('kod', '010');

        $this->assertSame(3, $sektor010['jumlah']);
        $this->assertSame(1, $sektor010['selesai']);
        $this->assertSame(33, $sektor010['peratus']); // 1 / 3 entiti sektor itu

        // Sektor 002 mempunyai 19 entiti dan tiada satu pun siap.
        $sektor002 = collect($this->kira()['selesaiMengikutSektor'])->firstWhere('kod', '002');

        $this->assertSame(19, $sektor002['jumlah']);
        $this->assertSame(0, $sektor002['selesai']);
        $this->assertSame(0, $sektor002['peratus']);
    }

    public function test_kemajuan_keseluruhan_dikira_daripada_peringkat_dicapai(): void
    {
        // 5 + 5 peringkat fasa semasa Selesai daripada maksimum 2 × 5 → 100%.
        $this->siapkanSemuaPeringkat(self::ALPHA);
        $this->siapkanSemuaPeringkat(self::BETA);

        $this->assertSame(100, $this->kira()['kemajuan']);
    }

    public function test_kemajuan_keseluruhan_separa(): void
    {
        // Kemajuan dikira daripada PERINGKAT yang Selesai, bukan daripada
        // nombor peringkat utama semasa: dengan sub-peringkat, satu nombor
        // peringkat tidak lagi memberitahu berapa banyak kerja telah siap.
        //
        // ALPHA: 1.1 sahaja                          = 1
        // BETA : 1.1, 1.2, 1.3, 2                    = 4
        // Jumlah 5 daripada maksimum 2 × 5 = 10      → 50%.
        $this->daftarkan(self::ALPHA);
        $this->peringkatHingga(self::BETA, AliranKerja::ANALISIS_INVENTORI);

        $this->assertSame(50, $this->kira()['kemajuan']);
    }

    /**
     * Kad "Entiti Selesai Pendaftaran" — peringkat 01 Selesai, mengikut
     * takrifan yang sama seperti KemajuanAnalisisService::pendaftaranSelesai().
     */
    public function test_peratus_pendaftaran_selesai_dikira_daripada_peringkat_01(): void
    {
        $ppr = User::factory()->create(['role' => User::ROLE_PENYELARAS_REKOD]);
        $kemajuan = app(KemajuanAnalisisService::class);

        // Sektor 010 mempunyai tiga entiti; dua menyelesaikan pendaftaran.
        $this->assertSame(3, $this->jumlahSenaraiInduk('010'));

        foreach (['K100100', 'A100101'] as $kod) {
            $kemajuan->lengkapkanPenerimaan(SektorDirectory::cariEntiti($kod), $ppr, ['tarikh_terima' => '2026-08-14', 'status_borang' => 'Selesai', 'no_rujukan' => 'FIKSTUR/1.1']);
        }

        $statistik = $this->kira('010');

        $this->assertSame(2, $statistik['pendaftaranSelesai']);
        $this->assertSame(67, $statistik['peratusPendaftaranSelesai']); // 2/3
    }

    /**
     * Entiti yang baris peringkatnya wujud tetapi BELUM Selesai tidak dikira —
     * termasuk entiti yang telah ditetapkan semula oleh Ketua Bahagian.
     */
    public function test_pendaftaran_yang_ditetapkan_semula_tidak_dikira_selesai(): void
    {
        $ppr = User::factory()->create(['role' => User::ROLE_PENYELARAS_REKOD]);
        $kemajuan = app(KemajuanAnalisisService::class);

        foreach (['K100100', 'A100101'] as $kod) {
            $kemajuan->lengkapkanPenerimaan(SektorDirectory::cariEntiti($kod), $ppr, ['tarikh_terima' => '2026-08-14', 'status_borang' => 'Selesai', 'no_rujukan' => 'FIKSTUR/1.1']);
        }

        $this->assertSame(2, $this->kira('010')['pendaftaranSelesai']);

        $kemajuan->setSemula('A100101', $ppr, 'Data tidak lengkap.');

        $selepas = $this->kira('010');

        $this->assertSame(1, $selepas['pendaftaranSelesai']);
        $this->assertSame(33, $selepas['peratusPendaftaranSelesai']); // 1/3
    }

    /**
     * Hanya laporan yang TELAH diserahkan kepada NACSA dikira.
     *
     * Laporan yang masih dalam kitaran — termasuk yang telah disahkan KB
     * tetapi belum ditekan "Hantar" pada peringkat 07 — tidak menokok kiraan.
     */
    public function test_kiraan_laporan_hanya_merangkumi_yang_diserahkan_kepada_nacsa(): void
    {
        $kemajuan = app(KemajuanAnalisisService::class);
        $kemajuan->lengkapkanPenerimaan(SektorDirectory::cariEntiti(self::ALPHA), $this->coordinator, ['tarikh_terima' => '2026-08-14', 'status_borang' => 'Selesai', 'no_rujukan' => 'FIKSTUR/1.1']);
        $kemajuan->lengkapkanPenerimaan(SektorDirectory::cariEntiti(self::BETA), $this->coordinator, ['tarikh_terima' => '2026-08-14', 'status_borang' => 'Selesai', 'no_rujukan' => 'FIKSTUR/1.1']);

        // BETA: laporan disahkan KB tetapi BELUM diserahkan. Rekod dapatan
        // analisisnya juga wujud — kedua-duanya tetap tidak dikira.
        $this->laporanSemakan(self::BETA, 'inventori', LaporanSemakan::SAH);
        AnalisisInventori::factory()->create(
            SektorDirectory::cariEntiti(self::BETA) + ['selesai' => true, 'user_id' => $this->analyst->id]
        );

        $this->assertSame(
            ['inventori' => 0, 'risiko' => 0, 'kesiapsiagaan' => 0],
            $this->kira()['jumlahLaporan'],
        );

        // ALPHA menekan "Hantar" — barulah ia dikira.
        $this->serahkanKepadaNacsa(self::ALPHA);

        $this->assertSame(
            ['inventori' => 1, 'risiko' => 0, 'kesiapsiagaan' => 0],
            $this->kira()['jumlahLaporan'],
        );
    }

    /**
     * Jenis laporan diambil daripada metadata jejak penyerahan, jadi setiap
     * jenis dikira dalam lajurnya sendiri.
     */
    public function test_kiraan_laporan_diasingkan_mengikut_jenis(): void
    {
        app(KemajuanAnalisisService::class)
            ->lengkapkanPenerimaan(SektorDirectory::cariEntiti(self::ALPHA), $this->coordinator, ['tarikh_terima' => '2026-08-14', 'status_borang' => 'Selesai', 'no_rujukan' => 'FIKSTUR/1.1']);

        $this->serahkanKepadaNacsa(self::ALPHA, 'inventori');
        $this->serahkanKepadaNacsa(self::ALPHA, 'risiko');

        $this->assertSame(
            ['inventori' => 1, 'risiko' => 1, 'kesiapsiagaan' => 0],
            $this->kira()['jumlahLaporan'],
        );
    }

    /**
     * Menekan "Hantar" dua kali pada entiti yang sama tetap satu laporan.
     */
    public function test_penyerahan_berulang_dikira_sekali_sahaja(): void
    {
        app(KemajuanAnalisisService::class)
            ->lengkapkanPenerimaan(SektorDirectory::cariEntiti(self::ALPHA), $this->coordinator, ['tarikh_terima' => '2026-08-14', 'status_borang' => 'Selesai', 'no_rujukan' => 'FIKSTUR/1.1']);

        $laporan = $this->serahkanKepadaNacsa(self::ALPHA);
        app(LaporanSemakanService::class)->rekodPenyerahan($laporan, $this->coordinator);

        $this->assertSame(2, ActivityLog::where('action', LaporanSemakanService::ACTION_DELIVERED)->count());
        $this->assertSame(1, $this->kira()['jumlahLaporan']['inventori']);
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

    public function test_papan_pemuka_tanpa_rekod_tidak_membahagi_dengan_sifar(): void
    {
        $statistik = $this->kira();

        $this->assertSame($this->jumlahSenaraiInduk(), $statistik['jumlahEntiti']);
        $this->assertSame(0, $statistik['jumlahDipantau']);
        $this->assertSame(0, $statistik['kemajuan']);
        $this->assertSame(0, $statistik['pendaftaranSelesai']);
        $this->assertSame(0, $statistik['peratusPendaftaranSelesai']);
        $this->assertSame(0, $statistik['peratusDalamProses']);
        $this->assertSame(0, $statistik['peratusSelesai']);
        $this->assertSame(['inventori' => 0, 'risiko' => 0, 'kesiapsiagaan' => 0], $statistik['jumlahLaporan']);

        // Setiap sektor kekal disenaraikan, semuanya pada sifar selesai —
        // tetapi hirisannya tetap bersaiz bilangan entiti sektor itu.
        $this->assertCount(count(config('sektor')), $statistik['selesaiMengikutSektor']);
        $this->assertSame(0, collect($statistik['selesaiMengikutSektor'])->sum('selesai'));
        $this->assertSame(
            $this->jumlahSenaraiInduk(),
            collect($statistik['selesaiMengikutSektor'])->sum('jumlah'),
        );
    }

    /**
     * Penyebut sifar — pengguna tanpa satu pun entiti boleh diakses. Tiada
     * peratusan boleh menjadi NaN atau Infinity.
     */
    public function test_pengguna_tanpa_entiti_tidak_membahagi_dengan_sifar(): void
    {
        $statistik = app(DashboardStatistikService::class)->kira($this->analyst);

        $this->assertSame(0, $statistik['jumlahEntiti']);
        $this->assertSame(0, $statistik['jumlahDipantau']);
        $this->assertSame(0, $statistik['kemajuan']);
        $this->assertSame(0, $statistik['peratusPendaftaranSelesai']);
        $this->assertSame(0, $statistik['peratusDalamProses']);
        $this->assertSame(0, $statistik['peratusSelesai']);
        $this->assertSame(0, collect($statistik['kemajuanTaburan'])->sum('peratus'));
    }

    /**
     * Papan pemuka kosong mesti dilukis tanpa ralat dan tanpa carta palsu.
     */
    public function test_papan_pemuka_kosong_memaparkan_keadaan_kosong(): void
    {
        $this->actingAs($this->coordinator)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Tiada entiti selesai')
            ->assertSee('0 daripada ' . $this->jumlahSenaraiInduk() . ' entiti ·')
            ->assertDontSee('NaN')
            ->assertDontSee('INF');
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
        $this->assertSame($this->jumlahSenaraiInduk(), $semua['jumlahEntiti']);
        $this->assertSame(3, $semua['jumlahDipantau']);
        $this->assertSame(2, $semua['selesai']);

        $sektor010 = $this->kira('010');
        $this->assertSame($this->jumlahSenaraiInduk('010'), $sektor010['jumlahEntiti']);
        $this->assertSame(1, $sektor010['jumlahDipantau']);
        $this->assertSame(1, $sektor010['selesai']);
        $this->assertSame(0, $sektor010['dalamProses']);
        $this->assertSame(1, $sektor010['jumlahSektor']);
        $this->assertSame('Sains, Teknologi dan Inovasi', $sektor010['penapis']['sector_name']);
    }

    public function test_penapis_sektor_tidak_sah_diabaikan(): void
    {
        $this->workflow(self::ALPHA, 3);

        $statistik = $this->kira('SEKTOR-TIDAK-WUJUD');

        $this->assertSame($this->jumlahSenaraiInduk(), $statistik['jumlahEntiti']);
        $this->assertSame(1, $statistik['jumlahDipantau']);
        $this->assertNull($statistik['penapis']['sector_code']);
    }

    public function test_penapis_tarikh_menghadkan_entiti_mengikut_tarikh_status(): void
    {
        $this->workflow(self::ALPHA, 3, '2026-08-01 09:00:00');
        $this->workflow(self::BETA, 5, '2026-08-20 09:00:00');

        $julat = $this->kira(null, '2026-08-15', '2026-08-31');

        // Hanya entiti BETA (peringkat 5) berada dalam julat tarikh.
        // Penapis tarikh mengecilkan entiti DIPANTAU; jumlah entiti dalam
        // senarai induk tidak berubah kerana entiti tidak hilang wujud.
        $this->assertSame(1, $julat['jumlahDipantau']);
        $this->assertSame(1, $julat['dalamProses']);
        $this->assertSame($this->jumlahSenaraiInduk(), $julat['jumlahEntiti']);
    }

    public function test_penapis_tarikh_meliputi_sempadan_hari_penuh(): void
    {
        $this->workflow(self::ALPHA, 2, '2026-08-15 23:30:00');

        $this->assertSame(1, $this->kira(null, '2026-08-15', '2026-08-15')['jumlahDipantau']);
    }

    public function test_julat_tarikh_terbalik_dibetulkan(): void
    {
        $this->workflow(self::ALPHA, 2, '2026-08-10 12:00:00');

        // Dari dan hingga ditukar tempat.
        $this->assertSame(1, $this->kira(null, '2026-08-20', '2026-08-01')['jumlahDipantau']);
    }

    public function test_penapis_sektor_dan_tarikh_boleh_digabungkan(): void
    {
        $this->workflow(self::ALPHA, 4, '2026-08-10 12:00:00');  // sektor 001, dalam julat
        $this->workflow(self::BETA, 4, '2026-09-10 12:00:00');   // sektor 001, luar julat
        $this->workflow(self::DELTA, 4, '2026-08-11 12:00:00');  // sektor 010, dalam julat

        $statistik = $this->kira('001', '2026-08-01', '2026-08-31');

        $this->assertSame(1, $statistik['jumlahDipantau']);
        $this->assertSame($this->jumlahSenaraiInduk('001'), $statistik['jumlahEntiti']);
        $this->assertTrue($statistik['penapis']['aktif']);
    }

    /*
    |--------------------------------------------------------------------------
    | Paparan
    |--------------------------------------------------------------------------
    */

    /**
     * Lapan kad ringkasan — tidak lebih, tidak kurang — dan kedua-dua carta.
     */
    public function test_papan_pemuka_memaparkan_lapan_kad_dan_dua_carta(): void
    {
        $this->workflow(self::ALPHA, 2);
        $this->workflowSiap(self::BETA);

        $response = $this->actingAs($this->coordinator)->get(route('dashboard'))->assertOk();

        foreach ([
            'Jumlah Sektor',
            'Jumlah Entiti',
            'Entiti Selesai Pendaftaran',
            'Entiti Dalam Proses',
            'Entiti Selesai',
            'Jumlah Laporan Analisis Inventori Kriptografi',
            'Jumlah Laporan Penilaian Risiko Migrasi PQC',
            'Jumlah Laporan Kesiapsiagaan',
        ] as $tajuk) {
            $response->assertSee($tajuk);
        }

        $response->assertSee('Entiti Selesai Kemajuan Analisis Mengikut Sektor')
            ->assertSee('Kemajuan Keseluruhan')
            ->assertSee('Aktiviti Terkini');

        // Tepat lapan kad ringkasan.
        $this->assertSame(8, substr_count($response->getContent(), 'class="metric-card"'));

        // Nilai peratusan dilabel sebagai peratusan.
        $response->assertSee('metric-card__unit', false);
    }

    /**
     * Kedua-dua komponen yang digantikan tidak boleh berbaki di mana-mana.
     */
    public function test_komponen_lama_dibuang_daripada_papan_pemuka(): void
    {
        $this->workflow(self::ALPHA, 2);

        $response = $this->actingAs($this->coordinator)->get(route('dashboard'))->assertOk();

        // "Status 3 Laporan" kekal sebagai modulnya sendiri dalam bar sisi —
        // yang dibuang ialah kad papan pemuka dan penanda gayanya.
        $response->assertDontSee('Taburan Kemajuan Analisis 7 Peringkat')
            ->assertDontSee('Laporan Selesai')
            ->assertDontSee('workflow-taburan', false)
            ->assertDontSee('status-pills', false)
            ->assertDontSee('bar-chart', false);
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
        $this->siapkanSemuaPeringkat(self::ALPHA);
        $this->siapkanSemuaPeringkat(self::BETA);

        $this->actingAs($this->coordinator)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertViewHas('jumlahDipantau', 2)
            ->assertViewHas('selesai', 2)
            ->assertViewHas('kemajuan', 100);

        // Entiti ketiga yang baru memasuki aliran menurunkan kemajuan
        // secara automatik: (5 + 5 + 1) / (3 × 5) = 73%.
        $this->daftarkan(self::GAMMA);

        $this->actingAs($this->coordinator)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertViewHas('jumlahDipantau', 3)
            ->assertViewHas('kemajuan', 73);
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
