<?php

namespace Tests\Feature;

use App\Exceptions\InvalidWorkflowTransitionException;
use App\Models\ActivityLog;
use App\Models\User;
use App\Models\WorkflowStatus;
use App\Services\WorkflowTransitionService;
use App\Support\SektorDirectory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * FASA 2 — workflow 7 peringkat.
 *
 * Meliputi peralihan sah (1→2 … 6→7), peralihan tidak sah, penyimpanan
 * tarikh status, pegawai yang mengemas kini, dan rekod untuk jejak audit.
 */
class Phase2WorkflowTest extends TestCase
{
    use RefreshDatabase;

    private WorkflowTransitionService $service;

    private User $coordinator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(WorkflowTransitionService::class);
        $this->coordinator = User::factory()->create(['role' => User::ROLE_COORDINATOR]);
    }

    private function workflowPada(int $stage): WorkflowStatus
    {
        return WorkflowStatus::factory()
            ->onStage($stage)
            ->create([
                'agency_code' => 'A010101',
                'agency_name' => 'Suruhanjaya Pilihan Raya (SPR)',
                'updated_by_user_id' => null,
            ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Definisi 7 peringkat
    |--------------------------------------------------------------------------
    */

    public function test_tujuh_peringkat_workflow_ditakrifkan_mengikut_spesifikasi(): void
    {
        $this->assertSame([
            1 => 'Penerimaan & Pendaftaran Data',
            2 => 'Semakan Awal Data',
            3 => 'Penyediaan & Pengesahan Data',
            4 => 'Pelaksanaan Analisis',
            5 => 'Penjanaan Laporan',
            6 => 'Semakan & Kelulusan',
            7 => 'Penyerahan & Penutupan',
        ], WorkflowStatus::WORKFLOW_STAGES);

        $this->assertSame(1, WorkflowStatus::FIRST_STAGE);
        $this->assertSame(7, WorkflowStatus::LAST_STAGE);
    }

    public function test_entiti_didaftarkan_bermula_pada_peringkat_satu(): void
    {
        $entiti = SektorDirectory::cariEntiti('A010101');

        $workflow = $this->service->initialize($entiti, $this->coordinator);

        $this->assertDatabaseHas('workflow_status', [
            'agency_code' => 'A010101',
            'current_stage' => 1,
            'stage_name' => 'Penerimaan & Pendaftaran Data',
            'status' => 'Belum Bermula',
            'updated_by_user_id' => $this->coordinator->id,
        ]);

        $this->assertNotNull($workflow->status_since);
        $this->assertSame('001', $workflow->sector_code);
    }

    public function test_pendaftaran_semula_tidak_menghasilkan_rekod_pendua(): void
    {
        $entiti = SektorDirectory::cariEntiti('A010101');

        $pertama = $this->service->initialize($entiti, $this->coordinator);
        $kedua = $this->service->initialize($entiti, $this->coordinator);

        $this->assertSame($pertama->id, $kedua->id);
        $this->assertSame(1, WorkflowStatus::where('agency_code', 'A010101')->count());
    }

    /*
    |--------------------------------------------------------------------------
    | Peralihan sah: 1→2, 2→3, 3→4, 4→5, 5→6, 6→7
    |--------------------------------------------------------------------------
    */

    #[DataProvider('peralihanBerturutan')]
    public function test_peralihan_berturutan_dibenarkan(int $dari, int $kepada): void
    {
        $workflow = $this->workflowPada($dari);

        $this->service->transitionTo($workflow, $kepada, $this->coordinator);

        $this->assertSame($kepada, $workflow->fresh()->current_stage);
        $this->assertSame(
            WorkflowStatus::WORKFLOW_STAGES[$kepada],
            $workflow->fresh()->stage_name,
        );

        $this->assertDatabaseHas('workflow_status', [
            'agency_code' => 'A010101',
            'current_stage' => $kepada,
            'stage_name' => WorkflowStatus::WORKFLOW_STAGES[$kepada],
            'updated_by_user_id' => $this->coordinator->id,
        ]);
    }

    public static function peralihanBerturutan(): array
    {
        return [
            'peringkat 1 ke 2' => [1, 2],
            'peringkat 2 ke 3' => [2, 3],
            'peringkat 3 ke 4' => [3, 4],
            'peringkat 4 ke 5' => [4, 5],
            'peringkat 5 ke 6' => [5, 6],
            'peringkat 6 ke 7' => [6, 7],
        ];
    }

    public function test_entiti_boleh_melalui_kesemua_tujuh_peringkat_secara_berturutan(): void
    {
        $workflow = $this->workflowPada(1);

        for ($peringkat = 2; $peringkat <= 7; $peringkat++) {
            $this->service->advance($workflow, $this->coordinator);

            $this->assertSame($peringkat, $workflow->current_stage);
        }

        $this->assertTrue($workflow->isComplete());
        $this->assertSame(100, $workflow->progressPercentage());

        // Satu rekod bagi setiap peralihan 1→2 … 6→7.
        $this->assertSame(6, ActivityLog::where('agency_code', 'A010101')
            ->where('action', WorkflowTransitionService::ACTION_STAGE_CHANGED)
            ->count());
    }

    public function test_peringkat_baharu_bermula_pada_status_lalai(): void
    {
        $workflow = $this->workflowPada(2);
        $this->service->updateStatus($workflow, 'Siap', $this->coordinator);

        $this->service->advance($workflow, $this->coordinator);

        $this->assertSame(WorkflowStatus::DEFAULT_STATUS, $workflow->fresh()->status);
    }

    public function test_status_boleh_ditetapkan_semasa_peralihan(): void
    {
        $workflow = $this->workflowPada(3);

        $this->service->advance($workflow, $this->coordinator, 'Dalam Proses');

        $this->assertSame(4, $workflow->fresh()->current_stage);
        $this->assertSame('Dalam Proses', $workflow->fresh()->status);
    }

    /*
    |--------------------------------------------------------------------------
    | Peralihan tidak sah
    |--------------------------------------------------------------------------
    */

    public function test_lompatan_peringkat_ke_hadapan_ditolak(): void
    {
        $workflow = $this->workflowPada(1);

        $this->expectException(InvalidWorkflowTransitionException::class);

        $this->service->transitionTo($workflow, 3, $this->coordinator);
    }

    public function test_lompatan_peringkat_tidak_mengubah_rekod(): void
    {
        $workflow = $this->workflowPada(2);

        try {
            $this->service->transitionTo($workflow, 6, $this->coordinator);
        } catch (InvalidWorkflowTransitionException) {
            // dijangka
        }

        $this->assertSame(2, $workflow->fresh()->current_stage);
        $this->assertDatabaseMissing('activity_log', [
            'agency_code' => 'A010101',
            'action' => WorkflowTransitionService::ACTION_STAGE_CHANGED,
        ]);
    }

    public function test_peringkat_melebihi_tujuh_ditolak(): void
    {
        $workflow = $this->workflowPada(7);

        $this->expectException(InvalidWorkflowTransitionException::class);

        $this->service->advance($workflow, $this->coordinator);
    }

    public function test_peringkat_di_bawah_satu_ditolak(): void
    {
        $workflow = $this->workflowPada(2);

        $this->expectException(InvalidWorkflowTransitionException::class);

        $this->service->transitionTo($workflow, 0, $this->coordinator, 'Sebab diberikan');
    }

    public function test_peralihan_ke_peringkat_yang_sama_ditolak(): void
    {
        $workflow = $this->workflowPada(3);

        $this->expectException(InvalidWorkflowTransitionException::class);

        $this->service->transitionTo($workflow, 3, $this->coordinator);
    }

    public function test_status_tidak_sah_ditolak(): void
    {
        $workflow = $this->workflowPada(2);

        $this->expectException(InvalidWorkflowTransitionException::class);

        $this->service->updateStatus($workflow, 'Selesai Sepenuhnya', $this->coordinator);
    }

    public function test_semakan_peraturan_peralihan_pada_model(): void
    {
        $workflow = $this->workflowPada(3);

        $this->assertTrue($workflow->canTransitionTo(4));   // maju satu peringkat
        $this->assertFalse($workflow->canTransitionTo(5));  // lompatan
        $this->assertFalse($workflow->canTransitionTo(3));  // peringkat sama
        $this->assertFalse($workflow->canTransitionTo(8));  // di luar julat
        $this->assertFalse($workflow->canTransitionTo(0));  // di luar julat
        $this->assertTrue($workflow->canTransitionTo(2));   // undur (sebab dikuatkuasakan oleh service)

        $this->assertTrue($workflow->requiresReason(2));
        $this->assertFalse($workflow->requiresReason(4));
    }

    /*
    |--------------------------------------------------------------------------
    | Pengunduran peringkat — mesti ada sebab
    |--------------------------------------------------------------------------
    */

    public function test_pengunduran_tanpa_sebab_ditolak(): void
    {
        $workflow = $this->workflowPada(5);

        $this->expectException(InvalidWorkflowTransitionException::class);

        $this->service->transitionTo($workflow, 4, $this->coordinator);
    }

    public function test_pengunduran_dengan_sebab_direkodkan(): void
    {
        $workflow = $this->workflowPada(5);

        $this->service->transitionTo($workflow, 4, $this->coordinator, 'Data perlu disemak semula');

        $this->assertSame(4, $workflow->fresh()->current_stage);
        $this->assertSame('Data perlu disemak semula', $workflow->fresh()->notes);

        $log = ActivityLog::where('agency_code', 'A010101')
            ->where('action', WorkflowTransitionService::ACTION_STAGE_CHANGED)
            ->firstOrFail();

        $this->assertSame('backward', $log->metadata['direction']);
        $this->assertSame('Data perlu disemak semula', $log->metadata['reason']);
        $this->assertSame('5', $log->old_value);
        $this->assertSame('4', $log->new_value);
    }

    public function test_pengunduran_dengan_sebab_kosong_ditolak(): void
    {
        $workflow = $this->workflowPada(5);

        $this->expectException(InvalidWorkflowTransitionException::class);

        $this->service->transitionTo($workflow, 4, $this->coordinator, '   ');
    }

    /*
    |--------------------------------------------------------------------------
    | Tarikh status & pegawai yang mengemas kini
    |--------------------------------------------------------------------------
    */

    public function test_tarikh_status_disimpan_ketika_peringkat_berubah(): void
    {
        Carbon::setTestNow('2026-08-14 09:00:00');
        $workflow = $this->workflowPada(1);
        $tarikhAsal = $workflow->status_since;

        Carbon::setTestNow('2026-08-20 14:30:00');
        $this->service->advance($workflow, $this->coordinator);

        $segar = $workflow->fresh();

        $this->assertTrue($segar->status_since->greaterThan($tarikhAsal));
        $this->assertSame('2026-08-20 14:30:00', $segar->status_since->format('Y-m-d H:i:s'));

        $this->assertDatabaseHas('workflow_status', [
            'agency_code' => 'A010101',
            'current_stage' => 2,
        ]);

        Carbon::setTestNow();
    }

    public function test_tarikh_status_disimpan_ketika_status_dikemas_kini(): void
    {
        Carbon::setTestNow('2026-08-14 09:00:00');
        $workflow = $this->workflowPada(2);

        Carbon::setTestNow('2026-08-15 11:15:00');
        $this->service->updateStatus($workflow, 'Dalam Proses', $this->coordinator);

        $segar = $workflow->fresh();

        $this->assertSame('Dalam Proses', $segar->status);
        $this->assertSame('2026-08-15 11:15:00', $segar->status_since->format('Y-m-d H:i:s'));
        $this->assertSame($this->coordinator->id, $segar->updated_by_user_id);

        Carbon::setTestNow();
    }

    public function test_tarikh_status_kekal_selepas_dibaca_semula_daripada_database(): void
    {
        Carbon::setTestNow('2026-08-14 08:00:00');
        $workflow = $this->workflowPada(1);

        Carbon::setTestNow('2026-08-18 16:45:00');
        $this->service->advance($workflow, $this->coordinator);

        Carbon::setTestNow();

        $dariDatabase = WorkflowStatus::where('agency_code', 'A010101')->firstOrFail();

        $this->assertSame('2026-08-18 16:45:00', $dariDatabase->status_since->format('Y-m-d H:i:s'));
        $this->assertSame(2, $dariDatabase->current_stage);
        $this->assertSame('Semakan Awal Data', $dariDatabase->stage_name);
        $this->assertSame($this->coordinator->id, $dariDatabase->updated_by_user_id);
    }

    public function test_pegawai_yang_mengemas_kini_direkodkan(): void
    {
        $workflow = $this->workflowPada(1);
        $pentadbir = User::factory()->create(['role' => User::ROLE_ADMINISTRATOR]);

        $this->service->advance($workflow, $this->coordinator);
        $this->assertSame($this->coordinator->id, $workflow->fresh()->updated_by_user_id);

        $this->service->advance($workflow, $pentadbir);
        $this->assertSame($pentadbir->id, $workflow->fresh()->updated_by_user_id);
        $this->assertSame($pentadbir->name, $workflow->fresh()->updatedBy->name);
    }

    /*
    |--------------------------------------------------------------------------
    | Rekod untuk jejak audit (Fasa 8)
    |--------------------------------------------------------------------------
    */

    public function test_setiap_perubahan_peringkat_dicatat_untuk_jejak_audit(): void
    {
        Carbon::setTestNow('2026-08-20 14:30:00');

        $workflow = $this->workflowPada(1);
        $this->service->advance($workflow, $this->coordinator);

        $log = ActivityLog::where('agency_code', 'A010101')
            ->where('action', WorkflowTransitionService::ACTION_STAGE_CHANGED)
            ->firstOrFail();

        $this->assertSame('1', $log->old_value);
        $this->assertSame('2', $log->new_value);
        $this->assertSame($this->coordinator->id, $log->changed_by_user_id);
        $this->assertSame('2026-08-20 14:30:00', $log->changed_at->format('Y-m-d H:i:s'));
        $this->assertSame('Penerimaan & Pendaftaran Data', $log->metadata['from_stage_name']);
        $this->assertSame('Semakan Awal Data', $log->metadata['to_stage_name']);
        $this->assertSame('forward', $log->metadata['direction']);
        $this->assertSame('Peringkat Workflow Berubah', $log->getActionLabel());

        Carbon::setTestNow();
    }

    public function test_kemas_kini_status_dicatat_untuk_jejak_audit(): void
    {
        $workflow = $this->workflowPada(4);

        $this->service->updateStatus($workflow, 'Dalam Proses', $this->coordinator);

        $log = ActivityLog::where('agency_code', 'A010101')
            ->where('action', WorkflowTransitionService::ACTION_STATUS_UPDATED)
            ->firstOrFail();

        $this->assertSame('Belum Bermula', $log->old_value);
        $this->assertSame('Dalam Proses', $log->new_value);
        $this->assertSame(4, $log->metadata['stage']);
    }

    public function test_sejarah_peringkat_boleh_dibaca_mengikut_urutan_terkini(): void
    {
        $entiti = SektorDirectory::cariEntiti('A010101');
        $workflow = $this->service->initialize($entiti, $this->coordinator);

        $this->service->advance($workflow, $this->coordinator);
        $this->service->advance($workflow, $this->coordinator);
        $this->service->transitionTo($workflow, 2, $this->coordinator, 'Perlu semakan semula');

        $sejarah = $this->service->history('A010101');

        // pendaftaran + 2 peralihan maju + 1 pengunduran
        $this->assertCount(4, $sejarah);
        $this->assertSame(WorkflowTransitionService::ACTION_STAGE_CHANGED, $sejarah->first()->action);
        $this->assertSame('Perlu semakan semula', $sejarah->first()->metadata['reason']);
        $this->assertSame(WorkflowTransitionService::ACTION_INITIALIZED, $sejarah->last()->action);
    }

    public function test_relasi_sejarah_pada_model_workflow(): void
    {
        $workflow = $this->workflowPada(1);

        $this->service->advance($workflow, $this->coordinator);

        $this->assertCount(1, $workflow->stageHistory()->get());
    }
}
