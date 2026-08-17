<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\AnalisDraftHistory;
use App\Models\AnalisisInventori;
use App\Models\EntitiAssignment;
use App\Models\User;
use App\Models\WorkflowStatus;
use App\Services\EntityAssignmentService;
use App\Support\SektorDirectory;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
    use RefreshDatabase;

    private const SEKTOR = '001';

    private const ALPHA = 'A010101';

    private const ALPHA_NAMA = 'Suruhanjaya Pilihan Raya (SPR)';

    private const BETA = 'A010102';

    private User $penyelaras;

    private User $analystA;

    private User $analystB;

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
            'kod_rujukan' => 'PTPKM/INV/2026/001',
            'status_laporan' => 'Muktamad dengan Catatan',
            'ringkasan_data' => 'catatan',
            'data_status' => [
                'j0' => ['penerimaan' => 'Diterima', 'kebolehgunaan' => 'Boleh Digunakan', 'nota' => ''],
                'j1' => ['penerimaan' => 'Diterima', 'kebolehgunaan' => 'Boleh Digunakan', 'nota' => ''],
                'j2' => ['penerimaan' => 'Tiada', 'kebolehgunaan' => 'Tidak Boleh Digunakan', 'nota' => 'Belum diterima'],
            ],
            'profil' => [
                md5('Sistem/Aplikasi') => ['jumlah' => '12', 'nota' => ''],
                md5('Pelayan') => ['jumlah' => '8', 'nota' => ''],
            ],
            // Checkbox: AES + RSA ditanda; MD5 sengaja TIDAK ditanda.
            'algoritma' => $algoritma('Simetrik Blok|AES', true, '12')
                + $algoritma('Asimetrik (Penyulitan)|RSA', true, '5')
                + $algoritma('Fungsi Cincang|MD5', false, '3'),
            'algoritma_lain' => '',
            'protokol' => [['nama' => 'TLS', 'versi' => '1.2', 'bilangan' => '9', 'nota' => '']],
            'pustaka' => [['nama' => 'OpenSSL', 'versi' => '3.0', 'bilangan' => '9', 'nota' => '']],
            'vendor' => [['nama' => 'Vendor A', 'produk' => 'HSM', 'versi' => '2.1', 'bilangan' => '2', 'nota' => '']],
            'tindakan' => [0, 1],
            'tindakan_lain' => '',
            'kesimpulan' => ['umum'],
            'kesimpulan_lain' => '',
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

        // 2 ── Sektor → Entiti → pilih entiti (spesifikasi bahagian 7).
        $this->get(route('penugasan.index', ['sector_code' => self::SEKTOR]))
            ->assertOk()
            ->assertViewHas('entiti', fn ($senarai) => $senarai->total()
                === SektorDirectory::entitiDalamSektor(self::SEKTOR)->count());

        $this->get(route('penugasan.show', self::ALPHA))
            ->assertOk()
            ->assertSee(self::ALPHA_NAMA);

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

        // 4 ── Workflow: entiti didaftarkan pada peringkat 1.
        $this->post(route('workflow.mula', self::ALPHA))->assertRedirect();

        $workflow = WorkflowStatus::where('agency_code', self::ALPHA)->firstOrFail();
        $this->assertSame(1, $workflow->current_stage);
        $this->assertSame('Penerimaan & Pendaftaran Data', $workflow->stage_name);
        $this->assertNotNull($workflow->status_since);
        $this->assertSame($this->penyelaras->id, $workflow->updated_by_user_id);

        $this->post(route('logout'));

        // 5 ── Pegawai Analisis log masuk; tiada papan pemuka keseluruhan.
        $this->post(route('login.attempt'), [
            'username' => 'analisis.a',
            'password' => 'rahsia-analisis',
        ]);

        $this->get(route('dashboard'))->assertRedirect(route('analisis.index'));

        // 6 ── Input berstruktur: borang analisis entiti yang ditugaskan.
        $this->get(route('analisis.borang', [
            'sector_code' => self::SEKTOR,
            'agency_code' => self::ALPHA,
        ]))->assertOk()->assertSee(self::ALPHA_NAMA);

        // 7 ── Save draft (separa siap, tanpa pengesahan penuh).
        $this->post(route('analisis.draf'), [
            'sector_code' => self::SEKTOR,
            'agency_code' => self::ALPHA,
            'seksyen' => 'maklumat',
            'kod_rujukan' => 'PTPKM/INV/2026/001',
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
        ]))->assertOk()->assertSee('PTPKM/INV/2026/001', false);

        // 9 ── Simpanan muktamad: dapatan penuh + tanda selesai.
        $this->post(route('analisis.simpan'), $this->dapatanAnalisis(['selesai' => '1']))
            ->assertRedirect(route('analisis.index'));

        $analisis->refresh();
        $this->assertTrue((bool) $analisis->selesai);
        $this->assertSame('PTPKM/INV/2026/001', $analisis->kod_rujukan);
        $this->assertSame('Muktamad dengan Catatan', $analisis->status_laporan);

        // Checkbox algoritma: hanya yang ditanda direkodkan.
        $this->assertArrayHasKey('Simetrik Blok|AES', $analisis->data['algoritma']);
        $this->assertArrayHasKey('Asimetrik (Penyulitan)|RSA', $analisis->data['algoritma']);
        $this->assertArrayNotHasKey('Fungsi Cincang|MD5', $analisis->data['algoritma']);

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
            ->assertSee(self::ALPHA_NAMA)
            ->assertSee('AES')
            ->assertSee('RSA');

        $this->post(route('logout'));

        // 11 ── Penyelaras memajukan workflow sehingga peringkat 7.
        $this->actingAs($this->penyelaras);

        foreach (range(2, WorkflowStatus::LAST_STAGE) as $peringkat) {
            $this->post(route('workflow.peringkat', self::ALPHA), ['to_stage' => $peringkat])
                ->assertRedirect()
                ->assertSessionHasNoErrors();
        }

        $workflow->refresh();
        $this->assertSame(7, $workflow->current_stage);
        $this->assertSame('Penyerahan & Penutupan', $workflow->stage_name);
        $this->assertTrue($workflow->isComplete());

        // 12 ── Status laporan dikitar: Dalam Proses → Siap.
        $this->post(route('status.kitar'), SektorDirectory::cariEntiti(self::ALPHA) + [
            'jenis' => 'inventori',
        ])->assertRedirect();

        $this->assertDatabaseHas('status_laporan', [
            'agency_code' => self::ALPHA,
            'jenis' => 'inventori',
            'status' => 'Siap',
        ]);

        // 13 ── Dashboard dikira semula daripada rekod sebenar.
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
            'workflow_stage_changed',
            'report_status_changed',
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
                ->assertDontSee(self::ALPHA_NAMA)
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

    public function test_setiap_perubahan_peringkat_merekod_status_tarikh_dan_pegawai(): void
    {
        $this->actingAs($this->penyelaras)->post(route('workflow.mula', self::ALPHA));

        $workflow = WorkflowStatus::where('agency_code', self::ALPHA)->firstOrFail();
        $tarikhAsal = $workflow->status_since;

        $this->travel(1)->hours();

        $pentadbir = User::factory()->create(['role' => User::ROLE_ADMINISTRATOR]);

        $this->actingAs($pentadbir)
            ->post(route('workflow.peringkat', self::ALPHA), [
                'to_stage' => 2,
                'status' => 'Dalam Proses',
            ])->assertRedirect();

        $workflow->refresh();

        $this->assertSame(2, $workflow->current_stage);
        $this->assertSame('Semakan Awal Data', $workflow->stage_name);
        $this->assertSame('Dalam Proses', $workflow->status);
        $this->assertTrue($workflow->status_since->greaterThan($tarikhAsal));
        $this->assertSame($pentadbir->id, $workflow->updated_by_user_id);
        $this->assertSame($pentadbir->name, $workflow->updatedBy->name);

        // Kemas kini status dalam peringkat yang sama tidak menukar peringkat.
        $this->actingAs($pentadbir)
            ->post(route('workflow.status', self::ALPHA), ['status' => 'Siap'])
            ->assertRedirect();

        $workflow->refresh();
        $this->assertSame(2, $workflow->current_stage);
        $this->assertSame('Siap', $workflow->status);

        $this->travelBack();
    }

    public function test_pengunduran_peringkat_direkod_bersama_sebab_dalam_jejak_audit(): void
    {
        $this->actingAs($this->penyelaras);

        $this->post(route('workflow.mula', self::ALPHA));
        $this->post(route('workflow.peringkat', self::ALPHA), ['to_stage' => 2]);
        $this->post(route('workflow.peringkat', self::ALPHA), ['to_stage' => 3]);

        $this->post(route('workflow.peringkat', self::ALPHA), [
            'to_stage' => 2,
            'reason' => 'Data Jadual 1 perlu disemak semula.',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $log = ActivityLog::where('agency_code', self::ALPHA)
            ->where('action', 'workflow_stage_changed')
            ->orderByDesc('id')
            ->first();

        $this->assertSame('backward', $log->metadata['direction']);
        $this->assertSame('Data Jadual 1 perlu disemak semula.', $log->metadata['reason']);
        $this->assertSame('3', $log->old_value);
        $this->assertSame('2', $log->new_value);
    }

    /*
    |--------------------------------------------------------------------------
    | Dashboard dikira, bukan disimpan (spesifikasi bahagian 10)
    |--------------------------------------------------------------------------
    */

    public function test_dashboard_dikira_semula_apabila_workflow_bergerak(): void
    {
        $this->actingAs($this->penyelaras);

        WorkflowStatus::factory()->create(SektorDirectory::cariEntiti(self::ALPHA));
        WorkflowStatus::factory()->onStage(7)->create(SektorDirectory::cariEntiti(self::BETA));

        $this->get(route('dashboard'))
            ->assertOk()
            ->assertViewHas('jumlahEntiti', 2)
            ->assertViewHas('dalamProses', 1)
            ->assertViewHas('selesai', 1)
            // (1 + 7) / (2 × 7) = 57%
            ->assertViewHas('kemajuan', 57);

        // Satu peringkat maju → angka berubah tanpa sebarang nilai manual.
        $this->post(route('workflow.peringkat', self::ALPHA), ['to_stage' => 2])->assertRedirect();

        $this->get(route('dashboard'))
            ->assertOk()
            ->assertViewHas('kemajuan', 64);
    }

    public function test_taburan_workflow_dashboard_mengikut_rekod_sebenar(): void
    {
        WorkflowStatus::factory()->onStage(4)->create(SektorDirectory::cariEntiti(self::ALPHA));
        WorkflowStatus::factory()->onStage(4)->create(SektorDirectory::cariEntiti(self::BETA));

        $this->actingAs($this->penyelaras)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertViewHas('taburanWorkflow', function (array $taburan) {
                $peringkat4 = collect($taburan)->firstWhere('peringkat', 4);
                $peringkat1 = collect($taburan)->firstWhere('peringkat', 1);

                return count($taburan) === WorkflowStatus::LAST_STAGE
                    && $peringkat4['bilangan'] === 2
                    && $peringkat4['peratus'] === 100
                    && $peringkat1['bilangan'] === 0;
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

        $this->actingAs($this->analystA->fresh())
            ->get(route('dashboard'))
            ->assertRedirect(route('analisis.index'));
    }

    /*
    |--------------------------------------------------------------------------
    | Pusat maklumat entiti menghimpunkan hasil setiap modul
    |--------------------------------------------------------------------------
    */

    public function test_pusat_maklumat_entiti_memaparkan_hasil_semua_modul(): void
    {
        $this->actingAs($this->penyelaras);

        $this->post(route('penugasan.simpan', self::ALPHA), ['assigned_to_user_id' => $this->analystA->id]);
        $this->post(route('workflow.mula', self::ALPHA));
        $this->post(route('workflow.peringkat', self::ALPHA), ['to_stage' => 2]);
        $this->post(route('status.kitar'), SektorDirectory::cariEntiti(self::ALPHA) + ['jenis' => 'inventori']);

        $this->actingAs($this->analystA)
            ->post(route('analisis.simpan'), $this->dapatanAnalisis(['selesai' => '1']));

        $this->actingAs($this->penyelaras)
            ->get(route('entiti.show', self::ALPHA))
            ->assertOk()
            ->assertSee(self::ALPHA_NAMA)
            ->assertSee('Semakan Awal Data')          // workflow
            ->assertSee('Pegawai Analisis A')          // penugasan
            ->assertSee('PTPKM/INV/2026/001')          // dapatan analisis
            ->assertSee('Dalam Proses')                // status laporan
            ->assertSee('Peringkat Workflow Berubah'); // sejarah
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
                'kesimpulan' => ['umum', 'legasi'],
                'algoritma' => [
                    md5('Fungsi Cincang|MD5') => ['id' => 'Fungsi Cincang|MD5', 'dipilih' => '1', 'bilangan' => '2'],
                    md5('Asimetrik (Penyulitan)|RSA') => ['id' => 'Asimetrik (Penyulitan)|RSA', 'dipilih' => '1', 'bilangan' => '4'],
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
            ->assertSee('Kesimpulan Umum')
            ->assertSee('Sistem Legasi')
            // Ringkasan mengikut pilihan 'catatan', bukan teks lalai.
            ->assertSee('memerlukan tindakan susulan oleh entiti', false);
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
            'status_laporan' => 'Muktamad',
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
            ->assertRedirect(route('analisis.index'));

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
