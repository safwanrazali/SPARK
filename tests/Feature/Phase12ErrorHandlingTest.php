<?php

namespace Tests\Feature;

use App\Exceptions\ImmutableAuditLogException;
use App\Models\ActivityLog;
use App\Models\AnalisisInventori;
use App\Models\EntitiAssignment;
use App\Models\User;
use App\Models\WorkflowStatus;
use App\Services\EntityAssignmentService;
use App\Support\SektorDirectory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * FASA 12 — pengesahan input, peralihan tidak sah dan pengendalian ralat.
 *
 * Setiap kegagalan mesti:
 * - ditangkap dengan mesej yang boleh difahami pegawai
 * - TIDAK meninggalkan data separa tulis
 * - TIDAK mendedahkan surih tindanan atau ralat pangkalan data
 */
class Phase12ErrorHandlingTest extends TestCase
{
    use RefreshDatabase;

    private const SEKTOR = '001';

    private const ALPHA = 'A010101';

    private const BETA = 'A010102';

    private User $penyelaras;

    private User $analyst;

    protected function setUp(): void
    {
        parent::setUp();

        $this->penyelaras = User::factory()->create(['role' => User::ROLE_COORDINATOR]);
        $this->analyst = User::factory()->create(['role' => User::ROLE_ANALYST]);

        app(EntityAssignmentService::class)->assign(
            SektorDirectory::cariEntiti(self::ALPHA),
            $this->analyst,
            $this->penyelaras,
        );

        $this->analyst = $this->analyst->fresh();
    }

    /*
    |--------------------------------------------------------------------------
    | Pengesahan input laporan (spesifikasi bahagian 20)
    |--------------------------------------------------------------------------
    */

    public function test_simpanan_muktamad_menolak_borang_tanpa_medan_wajib(): void
    {
        $this->actingAs($this->analyst)
            ->from(route('analisis.index'))
            ->post(route('analisis.simpan'), [
                'sector_code' => self::SEKTOR,
                'agency_code' => self::ALPHA,
            ])
            ->assertRedirect(route('analisis.index'))
            ->assertSessionHasErrors(['status_laporan', 'ringkasan_data']);

        $this->assertDatabaseMissing('analisis_inventori', ['agency_code' => self::ALPHA]);
    }

    public function test_nilai_di_luar_senarai_yang_dibenarkan_ditolak(): void
    {
        $this->actingAs($this->analyst)
            ->from(route('analisis.index'))
            ->post(route('analisis.simpan'), [
                'sector_code' => self::SEKTOR,
                'agency_code' => self::ALPHA,
                'status_laporan' => 'Status Direka Sendiri',
                'ringkasan_data' => 'entah-apa',
                'tarikh_laporan' => 'bukan-tarikh',
            ])
            ->assertSessionHasErrors(['status_laporan', 'ringkasan_data', 'tarikh_laporan']);

        $this->assertDatabaseMissing('analisis_inventori', ['agency_code' => self::ALPHA]);
    }

    public function test_entiti_yang_tidak_wujud_dalam_sektor_ditolak(): void
    {
        // Pentadbir: melepasi kedua-dua gate `manage-analysis` dan kawalan
        // akses entiti, supaya yang diuji ialah pengesahan senarai induk.
        $this->actingAs(User::factory()->create(['role' => User::ROLE_ADMINISTRATOR]))
            ->from(route('analisis.index'))
            ->post(route('analisis.simpan'), [
                'sector_code' => self::SEKTOR,
                'agency_code' => 'KOD-TIDAK-WUJUD',
                'status_laporan' => 'Muktamad',
                'ringkasan_data' => 'lengkap',
            ])
            ->assertSessionHasErrors('agency_code');

        $this->assertDatabaseMissing('analisis_inventori', ['agency_code' => 'KOD-TIDAK-WUJUD']);
    }

    public function test_borang_analisis_memerlukan_sektor_dan_entiti(): void
    {
        $this->actingAs($this->analyst)
            ->from(route('analisis.index'))
            ->get(route('analisis.borang'))
            ->assertSessionHasErrors(['sector_code', 'agency_code']);
    }

    public function test_draf_menolak_entiti_tidak_sah_tanpa_mencipta_rekod(): void
    {
        $this->actingAs(User::factory()->create(['role' => User::ROLE_ADMINISTRATOR]))
            ->from(route('analisis.index'))
            ->post(route('analisis.draf'), [
                'sector_code' => '999',
                'agency_code' => self::ALPHA,
            ])
            ->assertSessionHasErrors('agency_code');

        $this->assertDatabaseCount('analisis_inventori', 0);
    }

    public function test_draf_json_mengembalikan_ralat_dalam_bentuk_json(): void
    {
        $pentadbir = User::factory()->create(['role' => User::ROLE_ADMINISTRATOR]);

        $this->actingAs($pentadbir)
            ->postJson(route('analisis.draf'), [
                'sector_code' => '999',
                'agency_code' => self::ALPHA,
            ])
            ->assertStatus(422)
            ->assertJson(['berjaya' => false]);
    }

    /*
    |--------------------------------------------------------------------------
    | Peralihan peringkat tidak sah (spesifikasi bahagian 12)
    |--------------------------------------------------------------------------
    */

    private function daftarkanWorkflow(): WorkflowStatus
    {
        $this->actingAs($this->penyelaras)->post(route('workflow.mula', self::ALPHA));

        return WorkflowStatus::where('agency_code', self::ALPHA)->firstOrFail();
    }

    public function test_lompatan_peringkat_ditolak_dan_peringkat_kekal(): void
    {
        $this->daftarkanWorkflow();

        $this->actingAs($this->penyelaras)
            ->from(route('workflow.show', self::ALPHA))
            ->post(route('workflow.peringkat', self::ALPHA), ['to_stage' => 5])
            ->assertRedirect(route('workflow.show', self::ALPHA))
            ->assertSessionHasErrors('to_stage');

        $this->assertSame(1, WorkflowStatus::where('agency_code', self::ALPHA)->first()->current_stage);
    }

    public function test_peringkat_di_luar_julat_ditolak_oleh_pengesahan(): void
    {
        $this->daftarkanWorkflow();

        foreach ([0, 8, -3, 'dua'] as $tidakSah) {
            $this->actingAs($this->penyelaras)
                ->from(route('workflow.show', self::ALPHA))
                ->post(route('workflow.peringkat', self::ALPHA), ['to_stage' => $tidakSah])
                ->assertSessionHasErrors('to_stage');
        }

        $this->assertSame(1, WorkflowStatus::where('agency_code', self::ALPHA)->first()->current_stage);
    }

    public function test_pengunduran_tanpa_sebab_ditolak(): void
    {
        $this->daftarkanWorkflow();

        $this->actingAs($this->penyelaras)
            ->post(route('workflow.peringkat', self::ALPHA), ['to_stage' => 2]);

        $this->actingAs($this->penyelaras)
            ->from(route('workflow.show', self::ALPHA))
            ->post(route('workflow.peringkat', self::ALPHA), ['to_stage' => 1])
            ->assertSessionHasErrors('to_stage');

        $this->assertSame(2, WorkflowStatus::where('agency_code', self::ALPHA)->first()->current_stage);

        // Percubaan yang gagal tidak mencemari jejak audit.
        $this->assertSame(
            1,
            ActivityLog::where('agency_code', self::ALPHA)
                ->where('action', 'workflow_stage_changed')
                ->count(),
        );
    }

    public function test_peralihan_ke_peringkat_yang_sama_ditolak(): void
    {
        $this->daftarkanWorkflow();

        $this->actingAs($this->penyelaras)
            ->from(route('workflow.show', self::ALPHA))
            ->post(route('workflow.peringkat', self::ALPHA), ['to_stage' => 1])
            ->assertSessionHasErrors('to_stage');
    }

    public function test_status_peringkat_di_luar_kitaran_ditolak(): void
    {
        $this->daftarkanWorkflow();

        $this->actingAs($this->penyelaras)
            ->from(route('workflow.show', self::ALPHA))
            ->post(route('workflow.status', self::ALPHA), ['status' => 'Diluluskan'])
            ->assertSessionHasErrors('status');

        $this->assertSame(
            WorkflowStatus::DEFAULT_STATUS,
            WorkflowStatus::where('agency_code', self::ALPHA)->first()->status,
        );
    }

    public function test_perubahan_peringkat_bagi_entiti_belum_didaftar_memberi_404(): void
    {
        $this->actingAs($this->penyelaras)
            ->post(route('workflow.peringkat', self::BETA), ['to_stage' => 2])
            ->assertNotFound();

        $this->actingAs($this->penyelaras)
            ->post(route('workflow.status', self::BETA), ['status' => 'Siap'])
            ->assertNotFound();
    }

    public function test_entiti_di_luar_senarai_induk_memberi_404_bukan_ralat_pelayan(): void
    {
        $this->actingAs($this->penyelaras)
            ->get(route('entiti.show', 'KOD-TIADA'))
            ->assertNotFound();

        $this->actingAs($this->penyelaras)
            ->get(route('workflow.show', 'KOD-TIADA'))
            ->assertNotFound();

        $this->actingAs($this->penyelaras)
            ->get(route('penugasan.show', 'KOD-TIADA'))
            ->assertNotFound();
    }

    /*
    |--------------------------------------------------------------------------
    | Peraturan penugasan (spesifikasi bahagian 8)
    |--------------------------------------------------------------------------
    */

    public function test_penugasan_kepada_bukan_pegawai_analisis_ditolak(): void
    {
        $ketua = User::factory()->create(['role' => User::ROLE_KETUA_BAHAGIAN]);

        $this->actingAs($this->penyelaras)
            ->from(route('penugasan.show', self::BETA))
            ->post(route('penugasan.simpan', self::BETA), ['assigned_to_user_id' => $ketua->id])
            ->assertSessionHasErrors('assigned_to_user_id');

        $this->assertDatabaseMissing('entiti_assignment', ['agency_code' => self::BETA]);
    }

    public function test_penugasan_pendua_kepada_pegawai_yang_sama_ditolak(): void
    {
        $this->actingAs($this->penyelaras)
            ->from(route('penugasan.show', self::ALPHA))
            ->post(route('penugasan.simpan', self::ALPHA), ['assigned_to_user_id' => $this->analyst->id])
            ->assertSessionHasErrors('assigned_to_user_id');

        $this->assertSame(
            1,
            EntitiAssignment::where('agency_code', self::ALPHA)->count(),
        );
    }

    public function test_penugasan_kepada_pengguna_tidak_wujud_ditolak(): void
    {
        $this->actingAs($this->penyelaras)
            ->from(route('penugasan.show', self::BETA))
            ->post(route('penugasan.simpan', self::BETA), ['assigned_to_user_id' => 999999])
            ->assertSessionHasErrors('assigned_to_user_id');
    }

    public function test_penarikan_penugasan_yang_tiada_ditolak_dengan_mesej(): void
    {
        $this->actingAs($this->penyelaras)
            ->from(route('penugasan.show', self::BETA))
            ->post(route('penugasan.tarik', self::BETA))
            ->assertSessionHasErrors('assigned_to_user_id');
    }

    public function test_satu_entiti_hanya_ada_satu_penugasan_aktif(): void
    {
        $analystB = User::factory()->create(['role' => User::ROLE_ANALYST]);

        $this->actingAs($this->penyelaras)
            ->post(route('penugasan.simpan', self::ALPHA), ['assigned_to_user_id' => $analystB->id])
            ->assertRedirect();

        $this->assertSame(
            1,
            EntitiAssignment::where('agency_code', self::ALPHA)->active()->count(),
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Penapis dan parameter query yang tidak sempurna
    |--------------------------------------------------------------------------
    */

    /**
     * Borang penapis jejak audit menghantar nilai kosong apabila pilihan
     * "Semua entiti" digunakan. Nilai kosong bermakna TIADA penapis —
     * ia tidak boleh dilayan sebagai entiti yang tidak dibenarkan.
     */
    public function test_penapis_jejak_audit_kosong_bermakna_semua_entiti(): void
    {
        $this->actingAs($this->penyelaras)
            ->get(route('audit.index', [
                'agency_code' => '',
                'action' => '',
                'user_id' => '',
                'dari' => '',
                'hingga' => '',
            ]))
            ->assertOk();
    }

    public function test_penapis_jejak_audit_ke_entiti_tidak_dibenarkan_ditolak(): void
    {
        $this->actingAs($this->analyst)
            ->get(route('audit.index', ['agency_code' => self::BETA]))
            ->assertForbidden();
    }

    public function test_penapis_dashboard_yang_tidak_sah_diabaikan_dengan_selamat(): void
    {
        $this->actingAs($this->penyelaras)
            ->get(route('dashboard', [
                'sector_code' => 'TIADA',
                'dari' => 'bukan-tarikh',
                'hingga' => '',
            ]))
            ->assertOk()
            ->assertViewHas('penapis', fn (array $penapis) => $penapis['sector_code'] === null
                && $penapis['dari'] === null
                && $penapis['hingga'] === null);
    }

    public function test_julat_tarikh_terbalik_dibetulkan_bukan_menyebabkan_ralat(): void
    {
        $this->actingAs($this->penyelaras)
            ->get(route('dashboard', ['dari' => '2026-12-31', 'hingga' => '2026-01-01']))
            ->assertOk()
            ->assertViewHas('penapis', fn (array $penapis) => $penapis['dari'] === '2026-01-01'
                && $penapis['hingga'] === '2026-12-31');
    }

    public function test_halaman_pagination_di_luar_julat_tidak_menyebabkan_ralat(): void
    {
        $this->actingAs($this->penyelaras)
            ->get(route('workflow.index', ['page' => 9999]))
            ->assertOk();

        $this->actingAs($this->penyelaras)
            ->get(route('audit.index', ['page' => 9999]))
            ->assertOk();
    }

    /*
    |--------------------------------------------------------------------------
    | Kebolehpercayaan jejak audit
    |--------------------------------------------------------------------------
    */

    public function test_rekod_jejak_audit_tidak_boleh_diubah_atau_dipadam(): void
    {
        $this->daftarkanWorkflow();

        $log = ActivityLog::where('agency_code', self::ALPHA)
            ->where('action', 'workflow_initialized')
            ->firstOrFail();

        try {
            $log->update(['action' => 'diubah']);
            $this->fail('Rekod jejak audit sepatutnya tidak boleh diubah.');
        } catch (ImmutableAuditLogException) {
            // dijangka
        }

        try {
            $log->delete();
            $this->fail('Rekod jejak audit sepatutnya tidak boleh dipadam.');
        } catch (ImmutableAuditLogException) {
            // dijangka
        }

        $this->assertDatabaseHas('activity_log', [
            'id' => $log->id,
            'action' => 'workflow_initialized',
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Halaman ralat tidak mendedahkan maklumat dalaman
    |--------------------------------------------------------------------------
    */

    public function test_url_tidak_dikenali_memberi_404(): void
    {
        $this->actingAs($this->penyelaras)
            ->get('/laluan-yang-tidak-wujud')
            ->assertNotFound();
    }

    public function test_rekod_laporan_yang_telah_dipadam_memberi_404(): void
    {
        $analisis = AnalisisInventori::factory()->create(
            SektorDirectory::cariEntiti(self::ALPHA) + ['user_id' => $this->analyst->id]
        );

        $id = $analisis->id;
        $analisis->delete();

        $this->actingAs($this->penyelaras)
            ->get(route('laporan.inventori', $id))
            ->assertNotFound();
    }

    public function test_penolakan_akses_tidak_mendedahkan_maklumat_entiti(): void
    {
        $beta = AnalisisInventori::factory()->create(
            SektorDirectory::cariEntiti(self::BETA) + [
                'kod_rujukan' => 'RAHSIA/BETA/001',
                'user_id' => $this->penyelaras->id,
            ]
        );

        $respons = $this->actingAs($this->analyst)->get(route('laporan.inventori', $beta));

        $respons->assertForbidden();
        $respons->assertDontSee('RAHSIA/BETA/001');
        $respons->assertDontSee('A010102');
    }
}
