<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\AnalisDraftHistory;
use App\Models\AnalisisInventori;
use App\Models\EntitiAssignment;
use App\Models\StatusLaporan;
use App\Models\User;
use App\Models\WorkflowStatus;
use App\Services\DashboardStatistikService;
use App\Services\EntityAssignmentService;
use App\Services\KemajuanAnalisisService;
use App\Services\StatusTigaLaporanService;
use App\Support\AliranKerja;
use App\Support\SektorDirectory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\MelaluiAliranKerja;
use Tests\TestCase;

/**
 * FASA 12 — ujian integrasi merentas fasa.
 *
 * Ujian fasa 1–11 menguji setiap modul secara berasingan. Ujian ini
 * menjalankan aliran sebenar dari hujung ke hujung, mengikut rajah
 * spesifikasi bahagian 5:
 *
 *   SEKTOR → ENTITI → ASSIGNMENT → WORKFLOW → STATUS + TARIKH → DASHBOARD
 *   DAPATAN → INPUT BERSTRUKTUR → DRAF → RESUME → VALIDATION →
 *   PREVIEW → GENERATE → (REVIEW → APPROVAL: Fasa 10, belum dibina)
 */
class Phase12IntegrationTest extends TestCase
{
    use MelaluiAliranKerja, RefreshDatabase;

    private const SEKTOR = '001';

    private const ALPHA = 'A010101';

    private const BETA = 'A010102';

    private User $penyelaras;

    private User $analystA;

    private User $analystB;

    private User $penyelarasRekod;

    private User $penyelia;

    protected function setUp(): void
    {
        parent::setUp();

        $this->penyelaras = User::factory()->create([
            'role' => User::ROLE_COORDINATOR,
            'name' => 'Pegawai Penyelaras',
            'username' => 'penyelaras',
            'password' => 'rahsia-penyelaras',
        ]);

        $this->analystA = User::factory()->create([
            'role' => User::ROLE_ANALYST,
            'name' => 'Pegawai Analisis A',
            'username' => 'analisis.a',
            'password' => 'rahsia-analisis',
        ]);

        $this->analystB = User::factory()->create([
            'role' => User::ROLE_ANALYST,
            'name' => 'Pegawai Analisis B',
        ]);

        // Kawalan penyeliaan peringkat kini milik Pentadbir Sistem sahaja.
        $this->penyelia = User::factory()->create(['role' => User::ROLE_ADMINISTRATOR]);

        $this->penyelarasRekod = User::factory()->create([
            'role' => User::ROLE_PENYELARAS_REKOD,
            'name' => 'Pegawai Penyelaras Rekod',
            'username' => 'rekod',
            'password' => 'rahsia-rekod',
        ]);
    }

    /**
     * Peringkat 1.1 aliran kerja — prasyarat sebelum entiti boleh ditugaskan.
     *
     * Ujian yang memberi tumpuan kepada langkah kemudian memanggil servis
     * terus; aliran hujung ke hujung di bawah melaluinya melalui HTTP.
     */
    private function daftarkan(string $agencyCode): void
    {
        app(KemajuanAnalisisService::class)->lengkapkanPenerimaan(
            SektorDirectory::cariEntiti($agencyCode),
            $this->penyelaras,
        );
    }

    /**
     * Muatan borang analisis yang lengkap — mewakili dapatan yang
     * dimasukkan secara manual oleh Pegawai Analisis (tiada muat naik).
     *
     * @param  array<string, mixed>  $ubah
     * @return array<string, mixed>
     */
    private function dapatanAnalisis(array $ubah = []): array
    {
        $algoritma = fn (string $id, bool $dipilih, string $bilangan = '') => [
            md5($id) => array_filter([
                'id' => $id,
                'dipilih' => $dipilih ? '1' : null,
                'bilangan' => $bilangan,
                'nota' => '',
            ], fn ($v) => $v !== null),
        ];

        return array_replace([
            'sector_code' => self::SEKTOR,
            'agency_code' => self::ALPHA,
            'tarikh_laporan' => '2026-08-16',
            'kod_rujukan' => 'R-LP-MIG-4-0001-V1.0',
            'status_laporan' => 'Memerlukan Tindakan Susulan',
            'data_status' => [
                'j0' => ['kebolehgunaan' => 'Lengkap', 'nota' => ''],
                'j1' => ['kebolehgunaan' => 'Lengkap', 'nota' => ''],
                'j2' => ['kebolehgunaan' => 'Tidak Lengkap', 'nota' => 'Belum diterima'],
            ],
            'profil' => [
                md5('Sistem/Aplikasi') => ['jumlah' => '12', 'nota' => ''],
                md5('Pelayan') => ['jumlah' => '8', 'nota' => ''],
            ],
            // Checkbox: AES ditanda; ChaCha20 sengaja TIDAK ditanda.
            'algoritma' => $algoritma('Sifer Blok|AES', true, '12')
                + $algoritma('Sifer Alir|ChaCha20', false, '5'),
            // Katalog AKSA MySEAL tiada RSA/MD5, jadi ia direkodkan di sini,
            // masing-masing dengan bilangan sistem/aset tersendiri.
            'algoritma_lain' => [
                ['nama' => 'RSA', 'bilangan' => '5'],
                ['nama' => 'MD5', 'bilangan' => '3'],
            ],
            'protokol' => [['nama' => 'TLS', 'versi' => '1.2', 'bilangan' => '9']],
            'pustaka' => [['nama' => 'OpenSSL', 'versi' => '3.0', 'bilangan' => '9', 'nota' => '']],
            'vendor' => [['nama' => 'Vendor A', 'produk' => 'HSM', 'bilangan' => '2']],
            'tindakan' => [0, 1],
            'tindakan_lain' => '',
            'kesimpulan' => 'Kesimpulan yang ditaip oleh pegawai.',
        ], $ubah);
    }

    /*
    |--------------------------------------------------------------------------
    | Aliran penuh: pemantauan + pelaporan
    |--------------------------------------------------------------------------
    */

    public function test_kitaran_hayat_penuh_daripada_penugasan_sehingga_laporan_dijana(): void
    {
        // 1 ── Penyelaras log masuk dan membuka papan pemuka.
        $this->post(route('login.attempt'), [
            'username' => 'penyelaras',
            'password' => 'rahsia-penyelaras',
        ])->assertRedirect('/');

        $this->get(route('dashboard'))->assertOk();

        // 1b ── Peringkat 1.1 Penerimaan Data disiapkan. Sebelum langkah ini
        //       entiti langsung tidak boleh ditugaskan.
        //
        //       Dipanggil melalui servis kerana pencetus antara mukanya belum
        //       ditetapkan: kotak semak pukal telah dibuang, dan penggantinya
        //       masih menunggu keputusan.
        $this->daftarkan(self::ALPHA);

        $this->actingAs($this->penyelaras);

        // 2 ── Sektor → Entiti → pilih entiti (spesifikasi bahagian 7).
        //      Senarai PPA memaparkan entiti yang telah didaftarkan sahaja.
        $this->get(route('penugasan.index', ['sector_code' => self::SEKTOR]))
            ->assertOk()
            ->assertViewHas('entiti', fn ($senarai) => $senarai->total() === 1);

        $this->get(route('penugasan.show', self::ALPHA))
            ->assertOk()
            ->assertSee(self::ALPHA);

        // 3 ── Assignment: entiti ditugaskan kepada Pegawai Analisis A.
        $this->post(route('penugasan.simpan', self::ALPHA), [
            'assigned_to_user_id' => $this->analystA->id,
            'notes' => 'Kelompok pertama.',
        ])->assertRedirect();

        $this->assertDatabaseHas('entiti_assignment', [
            'agency_code' => self::ALPHA,
            'assigned_to_user_id' => $this->analystA->id,
            'assigned_by_user_id' => $this->penyelaras->id,
            'status' => EntitiAssignment::STATUS_ACTIVE,
        ]);

        // 4 ── Aliran kerja: entiti telah melepasi peringkat 1.1 pada langkah
        //      1b, jadi kedudukan semasanya ialah sub-peringkat 1.2 di dalam
        //      peringkat utama 1. Tiada pendaftaran manual: ia berlaku sendiri
        //      pada langkah itu.
        $workflow = WorkflowStatus::where('agency_code', self::ALPHA)->firstOrFail();
        $this->assertSame(1, $workflow->current_stage);
        $this->assertSame(AliranKerja::PENDAFTARAN_DATA, $workflow->current_stage_key);
        $this->assertSame('Penerimaan & Semakan Awal Data', $workflow->stage_name);
        $this->assertNotNull($workflow->status_since);
        $this->assertSame($this->penyelaras->id, $workflow->updated_by_user_id);

        $this->post(route('logout'));

        // 5 ── Pegawai Analisis log masuk; tiada papan pemuka keseluruhan.
        $this->post(route('login.attempt'), [
            'username' => 'analisis.a',
            'password' => 'rahsia-analisis',
        ]);

        // Papan pemuka keseluruhan ditolak bagi Pegawai Analisis.
        $this->get(route('dashboard'))->assertForbidden();

        // 6 ── Input berstruktur: borang analisis entiti yang ditugaskan.
        $this->get(route('analisis.borang', [
            'sector_code' => self::SEKTOR,
            'agency_code' => self::ALPHA,
        ]))->assertOk()->assertSee(self::ALPHA);

        // 7 ── Save draft (separa siap, tanpa pengesahan penuh).
        $this->post(route('analisis.draf'), [
            'sector_code' => self::SEKTOR,
            'agency_code' => self::ALPHA,
            'seksyen' => 'maklumat',
            'kod_rujukan' => 'R-LP-MIG-4-0001-V1.0',
            'tarikh_laporan' => '2026-08-16',
        ])->assertRedirect();

        $analisis = AnalisisInventori::where('agency_code', self::ALPHA)->firstOrFail();
        $this->assertFalse((bool) $analisis->selesai);
        $this->assertTrue(
            AnalisDraftHistory::where('analisis_inventori_id', $analisis->id)
                ->where('is_current', true)
                ->exists(),
        );

        // 8 ── Resume: keadaan borang dipulihkan selepas keluar dan kembali.
        $this->get(route('analisis.borang', [
            'sector_code' => self::SEKTOR,
            'agency_code' => self::ALPHA,
        ]))->assertOk()->assertSee('R-LP-MIG-4-0001-V1.0', false);

        // 9 ── Simpanan muktamad: dapatan penuh + tanda selesai.
        $this->post(route('analisis.simpan'), $this->dapatanAnalisis(['selesai' => '1']))
            ->assertRedirect(route('workflow.show', self::ALPHA));

        $analisis->refresh();
        $this->assertTrue((bool) $analisis->selesai);
        $this->assertSame('R-LP-MIG-4-0001-V1.0', $analisis->kod_rujukan);
        $this->assertSame('Memerlukan Tindakan Susulan', $analisis->status_laporan);

        // Checkbox algoritma: hanya yang ditanda direkodkan.
        $this->assertArrayHasKey('Sifer Blok|AES', $analisis->data['algoritma']);
        $this->assertArrayNotHasKey('Sifer Alir|ChaCha20', $analisis->data['algoritma']);

        // Algoritma di luar katalog AKSA MySEAL kekal dalam "Lain-lain".
        $this->assertSame([
            ['nama' => 'RSA', 'bilangan' => '5'],
            ['nama' => 'MD5', 'bilangan' => '3'],
        ], $analisis->data['algoritma_lain']);

        // Draf tidak lagi menjadi sumber pemulihan, tetapi kekal sebagai sejarah.
        $this->assertFalse(
            AnalisDraftHistory::where('analisis_inventori_id', $analisis->id)
                ->where('is_current', true)
                ->exists(),
        );
        $this->assertTrue(
            AnalisDraftHistory::where('analisis_inventori_id', $analisis->id)->exists(),
        );

        // Analisis selesai menaikkan status laporan Inventori.
        $this->assertDatabaseHas('status_laporan', [
            'agency_code' => self::ALPHA,
            'jenis' => 'inventori',
            'status' => 'Dalam Proses',
        ]);

        // 10 ── Preview: laporan mengikut templat rasmi.
        $this->get(route('laporan.inventori', $analisis))
            ->assertOk()
            ->assertSee('Laporan Analisis Inventori Kriptografi')
            ->assertSee(self::ALPHA)
            ->assertSee('AES')
            ->assertSee('RSA');

        $this->post(route('logout'));

        // 11 ── Entiti dibawa ke hujung fasa semasa (peringkat 3.1).
        //
        //       Peraturan turutan dan kebenaran peringkat diuji hujung ke
        //       hujung dalam KemajuanAnalisisAliranTest; di sini peringkat
        //       ditanda melalui servis supaya ujian ini kekal tertumpu kepada
        //       integrasi merentas modul.
        $kemajuan = app(KemajuanAnalisisService::class);

        foreach (AliranKerja::semasa() as $peringkat) {
            $kemajuan->tandakanSelesai(self::ALPHA, $peringkat, $this->penyelia);
        }

        $workflow->refresh();

        // Kedudukan berhenti pada peringkat terakhir FASA SEMASA: peringkat
        // 3.2, 4 dan 5 belum dibina, jadi entiti tidak boleh bergerak ke sana.
        $this->assertSame(AliranKerja::TERAKHIR_SEMASA, $workflow->current_stage_key);
        $this->assertSame(3, $workflow->current_stage);

        // 12 ── Status Tiga Laporan dikira, bukan ditetapkan. Kesemua
        //       peringkat fasa semasa kini Selesai, jadi laporan Inventori
        //       Selesai tanpa sesiapa menyentuh halaman itu.
        $this->actingAs($this->penyelaras);

        // Risiko PQC dan Kesiapsiagaan kekal "N/A" — modulnya belum wujud.
        $this->assertSame(
            [
                StatusLaporan::PAPARAN_SELESAI,
                StatusLaporan::PAPARAN_TIADA,
                StatusLaporan::PAPARAN_TIADA,
            ],
            array_column(app(StatusTigaLaporanService::class)->untukEntiti(self::ALPHA), 'status'),
        );

        $this->get(route('status.index'))
            ->assertOk()
            ->assertSee(StatusLaporan::PAPARAN_SELESAI);

        // 13 ── Dashboard dikira semula daripada rekod sebenar.
        //
        //       Kesemua peringkat fasa semasa kini Selesai, jadi entiti ini
        //       dikira siap. Jaminan songsangnya — peringkat tidak lengkap
        //       tidak pernah menjadi 'Siap' — diuji dalam
        //       KemajuanAnalisisAliranTest.
        $this->get(route('dashboard'))
            ->assertOk()
            ->assertViewHas('selesai', 1)
            ->assertViewHas('kemajuan', 100)
            ->assertViewHas('analisisSelesai', 1);

        // 14 ── Jejak audit merekod keseluruhan rantaian.
        $tindakan = ActivityLog::where('agency_code', self::ALPHA)->pluck('action')->unique();

        foreach ([
            'assignment_created',
            'workflow_initialized',
            'draft_created',
            'analysis_saved',
            'registration_completed',
            'stage_status_changed',
        ] as $dijangka) {
            $this->assertContains($dijangka, $tindakan, "Tindakan [{$dijangka}] tiada dalam jejak audit.");
        }

        $this->get(route('audit.index', ['agency_code' => self::ALPHA]))
            ->assertOk()
            ->assertSee('Peringkat Workflow Berubah');
    }

    /*
    |--------------------------------------------------------------------------
    | Pemilihan sektor → entiti (spesifikasi bahagian 7)
    |--------------------------------------------------------------------------
    */

    public function test_pemilihan_sektor_memaparkan_entiti_dalam_sektor_tersebut(): void
    {
        $this->actingAs($this->penyelaras);

        // Memilih sektor memaparkan SEMUA entiti sektor tersebut, termasuk
        // entiti yang belum mempunyai sebarang rekod pemantauan.
        $this->get(route('workflow.index', ['sector_code' => self::SEKTOR]))
            ->assertOk()
            ->assertViewHas('entiti', function ($senarai) {
                $sektorDipaparkan = collect($senarai->items())->pluck('sector_code')->unique();

                return $senarai->total() === SektorDirectory::entitiDalamSektor(self::SEKTOR)->count()
                    && $sektorDipaparkan->all() === [self::SEKTOR];
            });

        // Sektor lain tidak memaparkan entiti sektor 001.
        $sektorLain = collect(array_keys(SektorDirectory::sektor()))
            ->first(fn (string $kod) => $kod !== self::SEKTOR);

        if ($sektorLain !== null) {
            $this->get(route('workflow.index', ['sector_code' => $sektorLain]))
                ->assertOk()
                ->assertDontSee(self::ALPHA)
                ->assertViewHas('entiti', fn ($senarai) => $senarai->total()
                    === SektorDirectory::entitiDalamSektor($sektorLain)->count());
        }

        // Kod sektor tidak sah tidak menyebabkan ralat; penapis diabaikan.
        $this->get(route('workflow.index', ['sector_code' => 'TIADA']))
            ->assertOk()
            ->assertViewHas('sectorCode', null);
    }

    /*
    |--------------------------------------------------------------------------
    | Penugasan semula memindahkan akses
    |--------------------------------------------------------------------------
    */

    public function test_penugasan_semula_memindahkan_akses_dan_mengekalkan_kerja_sedia_ada(): void
    {
        $this->daftarkan(self::ALPHA);

        $this->actingAs($this->penyelaras)
            ->post(route('penugasan.simpan', self::ALPHA), ['assigned_to_user_id' => $this->analystA->id]);

        // Pegawai A menyimpan draf.
        $this->actingAs($this->analystA)
            ->post(route('analisis.draf'), [
                'sector_code' => self::SEKTOR,
                'agency_code' => self::ALPHA,
                'seksyen' => 'maklumat',
                'kod_rujukan' => 'DRAF-A',
            ])->assertRedirect();

        // Penyelaras menukar ganti kepada Pegawai B.
        $this->actingAs($this->penyelaras)
            ->post(route('penugasan.simpan', self::ALPHA), ['assigned_to_user_id' => $this->analystB->id])
            ->assertRedirect();

        // Akses Pegawai A ditarik serta-merta — termasuk kerja yang dia mulakan.
        $this->actingAs($this->analystA->fresh())
            ->get(route('entiti.show', self::ALPHA))
            ->assertForbidden();

        $this->actingAs($this->analystA->fresh())
            ->get(route('analisis.borang', ['sector_code' => self::SEKTOR, 'agency_code' => self::ALPHA]))
            ->assertForbidden();

        // Pegawai B mewarisi entiti dan draf yang telah disimpan.
        $this->actingAs($this->analystB->fresh())
            ->get(route('analisis.borang', ['sector_code' => self::SEKTOR, 'agency_code' => self::ALPHA]))
            ->assertOk()
            ->assertSee('DRAF-A', false);

        // Sejarah penugasan kekal lengkap.
        $sejarah = EntitiAssignment::where('agency_code', self::ALPHA)->get();
        $this->assertCount(2, $sejarah);
        $this->assertSame(1, $sejarah->where('status', EntitiAssignment::STATUS_ACTIVE)->count());
        $this->assertSame(1, $sejarah->where('status', EntitiAssignment::STATUS_REASSIGNED)->count());
    }

    public function test_penarikan_penugasan_menutup_akses_pegawai_analisis(): void
    {
        $this->daftarkan(self::ALPHA);

        $this->actingAs($this->penyelaras)
            ->post(route('penugasan.simpan', self::ALPHA), ['assigned_to_user_id' => $this->analystA->id]);

        $this->actingAs($this->analystA->fresh())
            ->get(route('entiti.show', self::ALPHA))
            ->assertOk();

        $this->actingAs($this->penyelaras)
            ->post(route('penugasan.tarik', self::ALPHA), ['reason' => 'Pegawai bertukar bahagian.'])
            ->assertRedirect();

        $this->actingAs($this->analystA->fresh())
            ->get(route('entiti.show', self::ALPHA))
            ->assertForbidden();

        $this->assertDatabaseHas('activity_log', [
            'agency_code' => self::ALPHA,
            'action' => 'assignment_removed',
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Status + tarikh (spesifikasi bahagian 11)
    |--------------------------------------------------------------------------
    */

    /*
    |--------------------------------------------------------------------------
    | Dashboard dikira, bukan disimpan (spesifikasi bahagian 10)
    |--------------------------------------------------------------------------
    */

    public function test_dashboard_dikira_semula_apabila_workflow_bergerak(): void
    {
        $this->actingAs($this->penyelaras);

        // Kemajuan dikira daripada PERINGKAT yang Selesai, bukan daripada
        // nombor peringkat utama: dengan sub-peringkat, satu nombor peringkat
        // tidak lagi memberitahu berapa banyak kerja telah siap.
        //
        // ALPHA: 1.1 sahaja                      = 1
        // BETA : kesemua lima peringkat fasa     = 5
        // Jumlah 6 daripada maksimum 2 x 5 = 10  -> 60%.
        $this->daftarkan(self::ALPHA);
        $this->lengkapkanFasaSemasa(self::BETA, $this->penyelaras);

        $this->get(route('dashboard'))
            ->assertOk()
            ->assertViewHas('jumlahDipantau', 2)
            ->assertViewHas('dalamProses', 1)
            ->assertViewHas('selesai', 1)
            ->assertViewHas('kemajuan', 60);

        // Satu peringkat maju -> angka berubah tanpa sebarang nilai manual.
        app(KemajuanAnalisisService::class)->tandakanSelesai(
            self::ALPHA,
            AliranKerja::PENDAFTARAN_DATA,
            $this->penyelaras,
        );

        $this->get(route('dashboard'))
            ->assertOk()
            ->assertViewHas('kemajuan', 70);
    }

    public function test_taburan_kemajuan_dashboard_mengikut_rekod_sebenar(): void
    {
        WorkflowStatus::factory()->onStage(4)->create(
            SektorDirectory::cariEntiti(self::ALPHA) + ['status' => DashboardStatistikService::STATUS_DALAM_PROSES]
        );
        WorkflowStatus::factory()->siap()->create(SektorDirectory::cariEntiti(self::BETA));

        $this->actingAs($this->penyelaras)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertViewHas('kemajuanTaburan', function (array $taburan) {
                $mengikutKunci = collect($taburan)->keyBy('kunci');

                // Taburan meliputi KESELURUHAN senarai induk: dua entiti
                // yang disentuh, dan bakinya kekal "Belum Mula".
                $belumDisentuh = SektorDirectory::semuaEntiti()->count() - 2;

                return count($taburan) === 3
                    && $mengikutKunci['selesai']['nilai'] === 1
                    && $mengikutKunci['proses']['nilai'] === 1
                    && $mengikutKunci['belum']['nilai'] === $belumDisentuh;
            });
    }

    public function test_dashboard_pegawai_analisis_tidak_pernah_memaparkan_angka_keseluruhan(): void
    {
        app(EntityAssignmentService::class)->assign(
            SektorDirectory::cariEntiti(self::ALPHA),
            $this->analystA,
            $this->penyelaras,
        );

        WorkflowStatus::factory()->create(SektorDirectory::cariEntiti(self::BETA));

        // Papan pemuka ditolak sepenuhnya — tiada versi ditapis dihidangkan.
        $this->actingAs($this->analystA->fresh())
            ->get(route('dashboard'))
            ->assertForbidden();
    }

    /*
    |--------------------------------------------------------------------------
    | Pusat maklumat entiti menghimpunkan hasil setiap modul
    |--------------------------------------------------------------------------
    */

    public function test_pusat_maklumat_entiti_memaparkan_hasil_semua_modul(): void
    {
        $this->daftarkan(self::ALPHA);

        $this->actingAs($this->penyelaras);

        $this->post(route('penugasan.simpan', self::ALPHA), ['assigned_to_user_id' => $this->analystA->id]);

        // Entiti yang telah melepasi peringkat 1.1 berada pada sub-peringkat
        // 1.2, di dalam peringkat utama 1. Kemajuan dipacu oleh status setiap
        // peringkat, bukan oleh kemas kini peringkat secara manual.
        $this->assertSame(
            AliranKerja::PENDAFTARAN_DATA,
            WorkflowStatus::where('agency_code', self::ALPHA)->firstOrFail()->current_stage_key,
        );

        $this->actingAs($this->analystA)
            ->post(route('analisis.simpan'), $this->dapatanAnalisis(['selesai' => '1']));

        $this->actingAs($this->penyelaras)
            ->get(route('entiti.show', self::ALPHA))
            ->assertOk()
            ->assertSee(self::ALPHA)
            ->assertSee('Pendaftaran Data')            // aliran kerja
            ->assertSee('Pegawai Analisis A')          // penugasan
            ->assertSee('R-LP-MIG-4-0001-V1.0')          // dapatan analisis
            ->assertSee('Dalam Proses')                // status laporan
            ->assertSee('Status Peringkat Analisis Berubah'); // sejarah
    }

    /*
    |--------------------------------------------------------------------------
    | Draf → resume → validation → preview → generate
    |--------------------------------------------------------------------------
    */

    public function test_draf_disimpan_seksyen_demi_seksyen_dengan_penomboran_versi(): void
    {
        app(EntityAssignmentService::class)->assign(
            SektorDirectory::cariEntiti(self::ALPHA),
            $this->analystA,
            $this->penyelaras,
        );

        $this->actingAs($this->analystA->fresh());

        $this->post(route('analisis.draf'), [
            'sector_code' => self::SEKTOR,
            'agency_code' => self::ALPHA,
            'seksyen' => 'maklumat',
            'kod_rujukan' => 'VERSI-1',
        ]);

        $analisis = AnalisisInventori::where('agency_code', self::ALPHA)->firstOrFail();
        $this->assertSame(1, (int) AnalisDraftHistory::where('analisis_inventori_id', $analisis->id)->max('version'));

        // Simpanan kedua hanya menulis seksyen yang benar-benar berubah.
        $this->post(route('analisis.draf'), [
            'sector_code' => self::SEKTOR,
            'agency_code' => self::ALPHA,
            'seksyen' => 'maklumat',
            'kod_rujukan' => 'VERSI-2',
        ]);

        $this->assertSame(2, (int) AnalisDraftHistory::where('analisis_inventori_id', $analisis->id)->max('version'));

        $versiDua = AnalisDraftHistory::where('analisis_inventori_id', $analisis->id)
            ->where('version', 2)
            ->get();

        $this->assertCount(1, $versiDua);
        $this->assertSame('maklumat', $versiDua->first()->section_name);

        // Resume mengambil nilai terkini, bukan versi lama.
        $this->get(route('analisis.borang', ['sector_code' => self::SEKTOR, 'agency_code' => self::ALPHA]))
            ->assertOk()
            ->assertSee('VERSI-2', false)
            ->assertDontSee('VERSI-1', false);

        // Sejarah versi kekal untuk kebolehjejakan.
        $this->assertDatabaseHas('analisis_draft_history', [
            'analisis_inventori_id' => $analisis->id,
            'version' => 1,
            'is_current' => false,
        ]);
    }

    public function test_draf_boleh_disimpan_secara_autosave_melalui_json(): void
    {
        app(EntityAssignmentService::class)->assign(
            SektorDirectory::cariEntiti(self::ALPHA),
            $this->analystA,
            $this->penyelaras,
        );

        $this->actingAs($this->analystA->fresh())
            ->postJson(route('analisis.draf'), [
                'sector_code' => self::SEKTOR,
                'agency_code' => self::ALPHA,
                'seksyen' => 'algoritma',
                'algoritma_lain' => 'SNOW 3G',
            ])
            ->assertOk()
            ->assertJson(['berjaya' => true])
            ->assertJsonStructure(['berjaya', 'mesej', 'disimpan_pada']);

        $this->assertDatabaseHas('analisis_inventori', ['agency_code' => self::ALPHA]);
    }

    public function test_pratonton_laporan_menggunakan_dapatan_yang_dimasukkan(): void
    {
        app(EntityAssignmentService::class)->assign(
            SektorDirectory::cariEntiti(self::ALPHA),
            $this->analystA,
            $this->penyelaras,
        );

        $this->actingAs($this->analystA->fresh())
            ->post(route('analisis.simpan'), $this->dapatanAnalisis([
                'kesimpulan' => 'Inventori memerlukan penambahbaikan.

Sistem legasi menghadapi kekangan.',
                // MD5 dan RSA tiada dalam katalog AKSA MySEAL (Approved);
                // penandaan lapuk/kuantum mesti tetap berfungsi daripada
                // medan "Lain-lain".
                'algoritma_lain' => [
                    ['nama' => 'MD5', 'bilangan' => '2'],
                    ['nama' => 'RSA', 'bilangan' => '4'],
                ],
                'selesai' => '1',
            ]));

        $analisis = AnalisisInventori::where('agency_code', self::ALPHA)->firstOrFail();

        // Business rules dikira daripada pilihan checkbox, bukan ditaip.
        $this->assertSame(['MD5'], $analisis->algoritmaLapuk());
        $this->assertSame(['RSA'], $analisis->algoritmaKuantum());

        $this->actingAs($this->analystA->fresh())
            ->get(route('laporan.inventori', $analisis))
            ->assertOk()
            ->assertSee('Laporan Analisis Inventori Kriptografi')
            ->assertSee('MD5')
            ->assertSee('RSA')
            // Kesimpulan kini ditaip sepenuhnya oleh pegawai; setiap perenggan
            // yang ditaip mesti muncul dalam laporan.
            ->assertSee('Inventori memerlukan penambahbaikan.')
            ->assertSee('Sistem legasi menghadapi kekangan.')
            // Templat rasmi memaparkan SATU status bagi setiap Jadual 0-2,
            // diambil daripada medan kebolehgunaan, bersama penerangannya.
            // Ayat "Ringkasan Status Data" tidak lagi dipaparkan dalam laporan
            // (lihat seksyen Status Penerimaan dan Kebolehgunaan Data).
            ->assertSee('Status Kebolehgunaan')
            ->assertSee('Tidak Lengkap')
            ->assertSee('Belum diterima')
            ->assertDontSee('memerlukan tindakan susulan oleh entiti', false);
    }

    /**
     * Penjanaan laporan sebenar (PDF) menggunakan Browsershot + Chrome tanpa
     * kepala. Jika persekitaran ujian tiada Chrome/Node, ujian ini dilangkau
     * dan penjanaan PDF perlu disahkan secara manual.
     */
    public function test_penjanaan_laporan_menghasilkan_fail_pdf_mengikut_kod_rujukan(): void
    {
        $analisis = AnalisisInventori::factory()->create(
            SektorDirectory::cariEntiti(self::ALPHA) + [
                'kod_rujukan' => 'PTPKM-INV-2026-001',
                'user_id' => $this->analystA->id,
            ]
        );

        try {
            $respons = $this->actingAs($this->penyelaras)
                ->withoutExceptionHandling()
                ->get(route('laporan.unduh', $analisis));
        } catch (\Throwable $e) {
            $this->markTestSkipped(
                'Penjanaan PDF memerlukan Chrome/Node (Browsershot): '.$e->getMessage()
            );
        }

        $respons->assertOk();

        $this->assertSame('application/pdf', $respons->headers->get('Content-Type'));
        $this->assertStringStartsWith('%PDF', $respons->getContent());
        $this->assertSame(
            'attachment; filename="laporan-PTPKM-INV-2026-001.pdf"',
            $respons->headers->get('Content-Disposition'),
        );
    }

    public function test_laporan_tanpa_dapatan_tidak_menyebabkan_ralat(): void
    {
        $kosong = AnalisisInventori::create(SektorDirectory::cariEntiti(self::ALPHA) + [
            'status_laporan' => 'Selesai',
            'data' => [],
            'selesai' => false,
            'user_id' => $this->analystA->id,
        ]);

        $this->actingAs($this->penyelaras)
            ->get(route('laporan.inventori', $kosong))
            ->assertOk()
            ->assertSee('Laporan Analisis Inventori Kriptografi');
    }

    /*
    |--------------------------------------------------------------------------
    | Tiada muat naik dokumen dalam aliran pelaporan (spesifikasi bahagian 3)
    |--------------------------------------------------------------------------
    */

    public function test_laporan_boleh_disiapkan_tanpa_sebarang_muat_naik(): void
    {
        app(EntityAssignmentService::class)->assign(
            SektorDirectory::cariEntiti(self::ALPHA),
            $this->analystA,
            $this->penyelaras,
        );

        $this->actingAs($this->analystA->fresh())
            ->post(route('analisis.simpan'), $this->dapatanAnalisis(['selesai' => '1']))
            ->assertRedirect(route('workflow.show', self::ALPHA));

        $analisis = AnalisisInventori::where('agency_code', self::ALPHA)->firstOrFail();

        $this->actingAs($this->analystA->fresh())
            ->get(route('laporan.inventori', $analisis))
            ->assertOk();

        // Tiada rekod muat naik terlibat dalam keseluruhan aliran.
        $this->assertDatabaseCount('muat_naik', 0);
    }

    /*
    |--------------------------------------------------------------------------
    | Jejak audit tidak boleh diubah (spesifikasi bahagian 24)
    |--------------------------------------------------------------------------
    */

    public function test_jejak_audit_tidak_merekod_kandungan_dapatan_analisis(): void
    {
        app(EntityAssignmentService::class)->assign(
            SektorDirectory::cariEntiti(self::ALPHA),
            $this->analystA,
            $this->penyelaras,
        );

        $this->actingAs($this->analystA->fresh())
            ->post(route('analisis.simpan'), $this->dapatanAnalisis(['selesai' => '1']));

        foreach (ActivityLog::where('agency_code', self::ALPHA)->get() as $log) {
            $metadata = $log->metadata ?? [];

            $this->assertArrayNotHasKey('data', $metadata);
            $this->assertArrayNotHasKey('section_data', $metadata);
            $this->assertStringNotContainsString('OpenSSL', json_encode($metadata));
        }
    }
}
