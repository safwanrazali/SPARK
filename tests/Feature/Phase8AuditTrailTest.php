<?php

namespace Tests\Feature;

use App\Exceptions\ImmutableAuditLogException;
use App\Models\ActivityLog;
use App\Models\User;
use App\Models\WorkflowStatus;
use App\Services\AuditTrailService;
use App\Services\EntityAssignmentService;
use App\Services\WorkflowTransitionService;
use App\Support\SektorDirectory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * FASA 8 — jejak audit dan sejarah.
 *
 * Menguji bahawa perubahan penting menghasilkan rekod audit, rekod tersebut
 * lengkap (entiti, tindakan, nilai lama/baharu, pengguna, cap masa, metadata),
 * tidak boleh diubah, dan hanya boleh dilihat oleh peranan yang dibenarkan.
 */
class Phase8AuditTrailTest extends TestCase
{
    use RefreshDatabase;

    private const ALPHA = 'A010101';

    private const BETA = 'A010102';

    private User $admin;

    private User $coordinator;

    private User $analyst;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['role' => User::ROLE_ADMINISTRATOR, 'name' => 'Pentadbir']);
        $this->coordinator = User::factory()->create(['role' => User::ROLE_COORDINATOR, 'name' => 'Penyelaras']);
        $this->analyst = User::factory()->create(['role' => User::ROLE_ANALYST, 'name' => 'Pegawai A']);
    }

    private function workflow(string $agencyCode, int $peringkat = 1): WorkflowStatus
    {
        return WorkflowStatus::factory()->onStage($peringkat)->create(
            SektorDirectory::cariEntiti($agencyCode) + ['updated_by_user_id' => $this->coordinator->id]
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Perubahan status workflow menghasilkan rekod audit
    |--------------------------------------------------------------------------
    */

    public function test_perubahan_peringkat_workflow_menghasilkan_rekod_audit(): void
    {
        Carbon::setTestNow('2026-08-20 14:30:00');

        $workflow = $this->workflow(self::ALPHA, 1);
        app(WorkflowTransitionService::class)->advance($workflow, $this->coordinator);

        Carbon::setTestNow();

        $log = ActivityLog::where('action', 'workflow_stage_changed')->firstOrFail();

        // Rekod menyokong: entiti, tindakan, nilai lama, nilai baharu,
        // pengguna, cap masa dan metadata.
        $this->assertSame(self::ALPHA, $log->agency_code);
        $this->assertSame('Suruhanjaya Pilihan Raya (SPR)', $log->agency_name);
        $this->assertSame('workflow_stage_changed', $log->action);
        $this->assertSame('1', $log->old_value);
        $this->assertSame('2', $log->new_value);
        $this->assertSame($this->coordinator->id, $log->changed_by_user_id);
        $this->assertSame('2026-08-20 14:30:00', $log->changed_at->format('Y-m-d H:i:s'));
        $this->assertSame('Semakan Awal Data', $log->metadata['to_stage_name']);
    }

    public function test_perubahan_status_dalam_peringkat_menghasilkan_rekod_audit(): void
    {
        $workflow = $this->workflow(self::ALPHA, 3);

        app(WorkflowTransitionService::class)->updateStatus($workflow, 'Dalam Proses', $this->coordinator);

        $this->assertDatabaseHas('activity_log', [
            'agency_code' => self::ALPHA,
            'action' => 'workflow_status_updated',
            'old_value' => 'Belum Bermula',
            'new_value' => 'Dalam Proses',
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Perubahan penugasan menghasilkan rekod audit
    |--------------------------------------------------------------------------
    */

    public function test_penugasan_menghasilkan_rekod_audit(): void
    {
        app(EntityAssignmentService::class)->assign(
            SektorDirectory::cariEntiti(self::ALPHA),
            $this->analyst,
            $this->coordinator,
        );

        $log = ActivityLog::where('action', 'assignment_created')->firstOrFail();

        $this->assertSame(self::ALPHA, $log->agency_code);
        $this->assertNull($log->old_value);
        $this->assertSame('Pegawai A', $log->new_value);
        $this->assertSame($this->coordinator->id, $log->changed_by_user_id);
        $this->assertNotNull($log->changed_at);
    }

    public function test_penukaran_dan_penarikan_penugasan_menghasilkan_rekod_audit(): void
    {
        $lain = User::factory()->create(['role' => User::ROLE_ANALYST, 'name' => 'Pegawai B']);
        $assignments = app(EntityAssignmentService::class);
        $entiti = SektorDirectory::cariEntiti(self::ALPHA);

        $assignments->assign($entiti, $this->analyst, $this->coordinator);
        $assignments->reassign($entiti, $lain, $this->coordinator);
        $assignments->unassign(self::ALPHA, $this->coordinator, 'Entiti ditangguhkan');

        $this->assertDatabaseHas('activity_log', [
            'action' => 'assignment_updated',
            'old_value' => 'Pegawai A',
            'new_value' => 'Pegawai B',
        ]);

        $this->assertDatabaseHas('activity_log', [
            'action' => 'assignment_removed',
            'old_value' => 'Pegawai B',
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Perubahan status laporan menghasilkan rekod audit
    |--------------------------------------------------------------------------
    */

    public function test_perubahan_status_laporan_menghasilkan_rekod_audit(): void
    {
        $entiti = SektorDirectory::cariEntiti(self::ALPHA);

        $hantar = fn () => $this->actingAs($this->coordinator)->post(route('status.kitar'), [
            'sector_code' => $entiti['sector_code'],
            'sector_name' => $entiti['sector_name'],
            'agency_code' => $entiti['agency_code'],
            'agency_name' => $entiti['agency_name'],
            'jenis' => 'inventori',
        ]);

        $hantar();

        $pertama = ActivityLog::where('action', 'report_status_changed')->firstOrFail();
        $this->assertNull($pertama->old_value);
        $this->assertSame('Dalam Proses', $pertama->new_value);
        $this->assertSame('inventori', $pertama->metadata['jenis']);
        $this->assertSame($this->coordinator->id, $pertama->changed_by_user_id);

        // Kitaran seterusnya merekod peralihan status sebenar.
        $hantar();

        $this->assertDatabaseHas('activity_log', [
            'action' => 'report_status_changed',
            'old_value' => 'Dalam Proses',
            'new_value' => 'Siap',
        ]);
    }

    public function test_simpanan_analisis_muktamad_menghasilkan_rekod_audit(): void
    {
        app(EntityAssignmentService::class)->assign(
            SektorDirectory::cariEntiti(self::ALPHA),
            $this->analyst,
            $this->coordinator,
        );

        $this->actingAs($this->analyst)->post(route('analisis.simpan'), [
            'sector_code' => '001',
            'agency_code' => self::ALPHA,
            'status_laporan' => 'Muktamad',
            'ringkasan_data' => 'lengkap',
            'kod_rujukan' => 'PTPKM/INV/2026/001',
            'selesai' => '1',
        ]);

        $log = ActivityLog::where('action', 'analysis_saved')->firstOrFail();

        $this->assertSame(self::ALPHA, $log->agency_code);
        $this->assertSame('Selesai', $log->new_value);
        $this->assertSame($this->analyst->id, $log->changed_by_user_id);
        $this->assertSame('PTPKM/INV/2026/001', $log->metadata['kod_rujukan']);
    }

    /*
    |--------------------------------------------------------------------------
    | Ketidakbolehubahan rekod audit
    |--------------------------------------------------------------------------
    */

    public function test_rekod_audit_tidak_boleh_diubah(): void
    {
        $workflow = $this->workflow(self::ALPHA, 1);
        app(WorkflowTransitionService::class)->advance($workflow, $this->coordinator);

        $log = ActivityLog::firstOrFail();

        $this->expectException(ImmutableAuditLogException::class);

        $log->update(['new_value' => '7']);
    }

    public function test_rekod_audit_tidak_boleh_dipadam(): void
    {
        $workflow = $this->workflow(self::ALPHA, 1);
        app(WorkflowTransitionService::class)->advance($workflow, $this->coordinator);

        $log = ActivityLog::firstOrFail();

        try {
            $log->delete();
            $this->fail('Rekod jejak audit sepatutnya tidak boleh dipadam.');
        } catch (ImmutableAuditLogException) {
            // dijangka
        }

        $this->assertDatabaseHas('activity_log', ['id' => $log->id]);
    }

    public function test_nilai_asal_rekod_audit_kekal_selepas_percubaan_pengubahan(): void
    {
        $workflow = $this->workflow(self::ALPHA, 1);
        app(WorkflowTransitionService::class)->advance($workflow, $this->coordinator);

        $log = ActivityLog::firstOrFail();

        try {
            $log->update(['new_value' => 'DIPALSUKAN']);
        } catch (ImmutableAuditLogException) {
            // dijangka
        }

        $this->assertSame('2', DB::table('activity_log')->where('id', $log->id)->value('new_value'));
    }

    public function test_tiada_route_yang_mengubah_atau_memadam_jejak_audit(): void
    {
        foreach (app('router')->getRoutes() as $route) {
            if (! str_contains($route->uri(), 'jejak-audit')) {
                continue;
            }

            $this->assertSame(
                ['GET', 'HEAD'],
                $route->methods(),
                "Route [{$route->uri()}] tidak sepatutnya membenarkan penulisan.",
            );
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Kebenaran paparan
    |--------------------------------------------------------------------------
    */

    public function test_tetamu_tidak_boleh_melihat_jejak_audit(): void
    {
        $this->get(route('audit.index'))->assertRedirect(route('login'));
    }

    public function test_pegawai_analisis_tidak_boleh_melihat_jejak_audit_berpusat(): void
    {
        $this->actingAs($this->analyst)->get(route('audit.index'))->assertForbidden();
    }

    public function test_peranan_pengurusan_boleh_melihat_jejak_audit(): void
    {
        $this->actingAs($this->coordinator)->get(route('audit.index'))->assertOk();
        $this->actingAs($this->admin)->get(route('audit.index'))->assertOk();
    }

    public function test_pautan_jejak_audit_disembunyikan_daripada_pegawai_analisis(): void
    {
        app(EntityAssignmentService::class)->assign(
            SektorDirectory::cariEntiti(self::ALPHA),
            $this->analyst,
            $this->coordinator,
        );

        $this->actingAs($this->analyst)
            ->get(route('entiti.show', self::ALPHA))
            ->assertOk()
            ->assertDontSee('Jejak Audit');

        $this->actingAs($this->coordinator)
            ->get(route('entiti.show', self::ALPHA))
            ->assertOk()
            ->assertSee('Jejak Audit');
    }

    /*
    |--------------------------------------------------------------------------
    | Paparan sejarah
    |--------------------------------------------------------------------------
    */

    public function test_jejak_audit_memaparkan_rekod_yang_boleh_dibaca(): void
    {
        $workflow = $this->workflow(self::ALPHA, 1);
        app(WorkflowTransitionService::class)->advance($workflow, $this->coordinator);

        $this->actingAs($this->coordinator)
            ->get(route('audit.index'))
            ->assertOk()
            ->assertSee('Peringkat Workflow Berubah')
            ->assertSee('Suruhanjaya Pilihan Raya (SPR)')
            ->assertSee('Penyelaras');
    }

    /**
     * Penapis disahkan melalui data yang dikembalikan, bukan teks halaman —
     * senarai pilihan penapis memang mengandungi semua label tindakan
     * dan nama entiti.
     *
     * @return Collection<int, ActivityLog>
     */
    private function rekodDipapar(array $pertanyaan)
    {
        $response = $this->actingAs($this->coordinator)
            ->get(route('audit.index', $pertanyaan))
            ->assertOk();

        return collect($response->viewData('rekod')->items());
    }

    public function test_jejak_audit_boleh_ditapis_mengikut_entiti(): void
    {
        app(WorkflowTransitionService::class)->advance($this->workflow(self::ALPHA, 1), $this->coordinator);
        app(WorkflowTransitionService::class)->advance($this->workflow(self::BETA, 1), $this->coordinator);

        $rekod = $this->rekodDipapar(['agency_code' => self::ALPHA]);

        $this->assertCount(1, $rekod);
        $this->assertSame(self::ALPHA, $rekod->first()->agency_code);
    }

    public function test_jejak_audit_boleh_ditapis_mengikut_tindakan_dan_pengguna(): void
    {
        app(WorkflowTransitionService::class)->advance($this->workflow(self::ALPHA, 1), $this->coordinator);
        app(EntityAssignmentService::class)->assign(
            SektorDirectory::cariEntiti(self::BETA),
            $this->analyst,
            $this->admin,
        );

        $ikutTindakan = $this->rekodDipapar(['action' => 'assignment_created']);
        $this->assertCount(1, $ikutTindakan);
        $this->assertSame('assignment_created', $ikutTindakan->first()->action);

        $ikutPengguna = $this->rekodDipapar(['user_id' => $this->admin->id]);
        $this->assertCount(1, $ikutPengguna);
        $this->assertSame($this->admin->id, $ikutPengguna->first()->changed_by_user_id);
    }

    public function test_jejak_audit_boleh_ditapis_mengikut_julat_tarikh(): void
    {
        Carbon::setTestNow('2026-08-01 10:00:00');
        app(WorkflowTransitionService::class)->advance($this->workflow(self::ALPHA, 1), $this->coordinator);

        Carbon::setTestNow('2026-08-20 10:00:00');
        app(EntityAssignmentService::class)->assign(
            SektorDirectory::cariEntiti(self::BETA),
            $this->analyst,
            $this->coordinator,
        );

        Carbon::setTestNow();

        $rekod = $this->rekodDipapar(['dari' => '2026-08-15', 'hingga' => '2026-08-31']);

        $this->assertCount(1, $rekod);
        $this->assertSame('assignment_created', $rekod->first()->action);
    }

    /*
    |--------------------------------------------------------------------------
    | Data sensitif tidak dicatat
    |--------------------------------------------------------------------------
    */

    public function test_kunci_sensitif_ditapis_daripada_metadata(): void
    {
        $log = app(AuditTrailService::class)->rekod(
            ['agency_code' => self::ALPHA, 'agency_name' => 'SPR'],
            'workflow_stage_changed',
            '1',
            '2',
            $this->coordinator,
            [
                'reason' => 'Sebab sah',
                'password' => 'rahsia123',
                'remember_token' => 'abc',
                'data' => ['dapatan' => 'kandungan penuh laporan'],
                'section_data' => ['banyak' => 'kandungan'],
            ],
        );

        $this->assertArrayHasKey('reason', $log->metadata);
        $this->assertArrayNotHasKey('password', $log->metadata);
        $this->assertArrayNotHasKey('remember_token', $log->metadata);
        $this->assertArrayNotHasKey('data', $log->metadata);
        $this->assertArrayNotHasKey('section_data', $log->metadata);
    }

    public function test_nilai_terlalu_panjang_dipotong(): void
    {
        $panjang = str_repeat('a', 900);

        $log = app(AuditTrailService::class)->rekod(
            ['agency_code' => self::ALPHA, 'agency_name' => 'SPR'],
            'workflow_stage_changed',
            null,
            $panjang,
            $this->coordinator,
            ['reason' => $panjang],
        );

        $this->assertLessThanOrEqual(AuditTrailService::HAD_TEKS + 1, mb_strlen($log->new_value));
        $this->assertLessThanOrEqual(AuditTrailService::HAD_TEKS + 1, mb_strlen($log->metadata['reason']));
    }

    public function test_draf_analisis_tidak_mencatat_kandungan_dapatan(): void
    {
        app(EntityAssignmentService::class)->assign(
            SektorDirectory::cariEntiti(self::ALPHA),
            $this->analyst,
            $this->coordinator,
        );

        $this->actingAs($this->analyst)->post(route('analisis.draf'), [
            'sector_code' => '001',
            'agency_code' => self::ALPHA,
            'kod_rujukan' => 'PTPKM/INV/2026/001',
            'kesimpulan_lain' => 'Kandungan dapatan sulit yang tidak sepatutnya dicatat.',
        ]);

        $log = ActivityLog::where('action', 'draft_created')->firstOrFail();

        $this->assertStringNotContainsString('Kandungan dapatan sulit', json_encode($log->metadata));
        $this->assertSame(1, $log->metadata['version']);
    }

    /*
    |--------------------------------------------------------------------------
    | Satu sistem log sahaja
    |--------------------------------------------------------------------------
    */

    public function test_semua_perubahan_penting_menggunakan_jadual_log_yang_sama(): void
    {
        $workflow = $this->workflow(self::ALPHA, 1);
        app(WorkflowTransitionService::class)->advance($workflow, $this->coordinator);
        app(EntityAssignmentService::class)->assign(
            SektorDirectory::cariEntiti(self::ALPHA),
            $this->analyst,
            $this->coordinator,
        );

        $tindakan = ActivityLog::where('agency_code', self::ALPHA)->pluck('action');

        $this->assertContains('workflow_stage_changed', $tindakan);
        $this->assertContains('assignment_created', $tindakan);
        $this->assertSame(2, $tindakan->count());
    }
}
