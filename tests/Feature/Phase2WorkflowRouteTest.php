<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\WorkflowStatus;
use App\Services\EntityAssignmentService;
use App\Services\WorkflowTransitionService;
use App\Support\SektorDirectory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * FASA 2 — route, kebenaran dan paparan stepper workflow.
 */
class Phase2WorkflowRouteTest extends TestCase
{
    use RefreshDatabase;

    private const ENTITI = 'A010101';

    private function coordinator(): User
    {
        return User::factory()->create(['role' => User::ROLE_COORDINATOR]);
    }

    private function analyst(): User
    {
        return User::factory()->create(['role' => User::ROLE_ANALYST]);
    }

    private function workflowPada(int $stage): WorkflowStatus
    {
        return WorkflowStatus::factory()->onStage($stage)->create([
            'agency_code' => self::ENTITI,
            'agency_name' => 'Suruhanjaya Pilihan Raya (SPR)',
            'updated_by_user_id' => null,
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Akses
    |--------------------------------------------------------------------------
    */

    public function test_tetamu_dialihkan_ke_log_masuk(): void
    {
        $this->get(route('workflow.index'))->assertRedirect(route('login'));
        $this->get(route('workflow.show', self::ENTITI))->assertRedirect(route('login'));
    }

    public function test_senarai_workflow_dipaparkan(): void
    {
        $this->workflowPada(3);

        $this->actingAs($this->coordinator())
            ->get(route('workflow.index'))
            ->assertOk()
            ->assertSee('A010101')
            ->assertSee('Penyediaan &amp; Pengesahan Data', false);
    }

    public function test_senarai_boleh_ditapis_mengikut_sektor(): void
    {
        // Entiti sektor 001 mempunyai rekod workflow; penapis sektor 010
        // hendaklah memaparkan entiti sektor tersebut sahaja.
        $this->workflowPada(3);

        $response = $this->actingAs($this->coordinator())
            ->get(route('workflow.index', ['sector_code' => '010']));

        $response->assertOk()
            ->assertSee('A100102')
            ->assertSee('A100101')
            ->assertSee('Belum Didaftarkan')
            ->assertDontSee('A010101');
    }

    public function test_halaman_entiti_memaparkan_stepper_tujuh_peringkat(): void
    {
        $this->workflowPada(2);

        $response = $this->actingAs($this->coordinator())
            ->get(route('workflow.show', self::ENTITI));

        $response->assertOk();

        foreach (WorkflowStatus::WORKFLOW_STAGES as $nama) {
            $response->assertSee($nama);
        }

        $response->assertSee('workflow-step--semasa', false);
        $response->assertSee('workflow-step--selesai', false);
    }

    public function test_entiti_belum_didaftar_memaparkan_pilihan_pendaftaran(): void
    {
        $this->actingAs($this->coordinator())
            ->get(route('workflow.show', self::ENTITI))
            ->assertOk()
            ->assertSee('belum didaftarkan dalam workflow', false)
            ->assertSee('Daftar Dalam Workflow')
            ->assertSee('Tiada perubahan peringkat');

        $this->assertDatabaseMissing('workflow_status', ['agency_code' => self::ENTITI]);
    }

    public function test_entiti_di_luar_senarai_induk_menghasilkan_404(): void
    {
        $this->actingAs($this->coordinator())
            ->get(route('workflow.show', 'ZZZ9999'))
            ->assertNotFound();
    }

    /*
    |--------------------------------------------------------------------------
    | Kebenaran peranan (gate manage-workflow)
    |--------------------------------------------------------------------------
    */

    public function test_pegawai_analisis_tidak_boleh_menukar_peringkat(): void
    {
        $this->workflowPada(1);

        $this->actingAs($this->analyst())
            ->post(route('workflow.peringkat', self::ENTITI), ['to_stage' => 2])
            ->assertForbidden();

        $this->assertDatabaseHas('workflow_status', [
            'agency_code' => self::ENTITI,
            'current_stage' => 1,
        ]);
    }

    /**
     * Sejak Fasa 4, Pegawai Analisis hanya boleh membuka entiti yang
     * ditugaskan kepadanya. Entiti ditugaskan dahulu supaya ujian ini kekal
     * menguji perkara asalnya: borang kemas kini peringkat tidak dipaparkan
     * kepada peranan tanpa gate `manage-workflow`.
     */
    public function test_pegawai_analisis_tidak_melihat_borang_kemas_kini(): void
    {
        $this->workflowPada(1);
        $analyst = $this->analyst();

        app(EntityAssignmentService::class)->assign(
            SektorDirectory::cariEntiti(self::ENTITI),
            $analyst,
            $this->coordinator(),
        );

        $this->actingAs($analyst)
            ->get(route('workflow.show', self::ENTITI))
            ->assertOk()
            ->assertDontSee('Majukan Peringkat');
    }

    /*
    |--------------------------------------------------------------------------
    | Tindakan workflow melalui HTTP
    |--------------------------------------------------------------------------
    */

    public function test_entiti_boleh_didaftarkan_melalui_http(): void
    {
        $this->actingAs($this->coordinator())
            ->post(route('workflow.mula', self::ENTITI))
            ->assertRedirect(route('workflow.show', self::ENTITI));

        $this->assertDatabaseHas('workflow_status', [
            'agency_code' => self::ENTITI,
            'current_stage' => 1,
            'status' => WorkflowStatus::DEFAULT_STATUS,
        ]);
    }

    public function test_peringkat_dimajukan_melalui_http(): void
    {
        $this->workflowPada(4);
        $coordinator = $this->coordinator();

        $this->actingAs($coordinator)
            ->from(route('workflow.show', self::ENTITI))
            ->post(route('workflow.peringkat', self::ENTITI), [
                'to_stage' => 5,
                'status' => 'Dalam Proses',
            ])
            ->assertRedirect(route('workflow.show', self::ENTITI))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('workflow_status', [
            'agency_code' => self::ENTITI,
            'current_stage' => 5,
            'stage_name' => 'Jana Laporan',
            'status' => 'Dalam Proses',
            'updated_by_user_id' => $coordinator->id,
        ]);
    }

    public function test_lompatan_peringkat_melalui_http_ditolak(): void
    {
        $this->workflowPada(2);

        $this->actingAs($this->coordinator())
            ->from(route('workflow.show', self::ENTITI))
            ->post(route('workflow.peringkat', self::ENTITI), ['to_stage' => 5])
            ->assertRedirect(route('workflow.show', self::ENTITI))
            ->assertSessionHasErrors('to_stage');

        $this->assertDatabaseHas('workflow_status', [
            'agency_code' => self::ENTITI,
            'current_stage' => 2,
        ]);
    }

    public function test_peringkat_di_luar_julat_ditolak_oleh_validation(): void
    {
        $this->workflowPada(7);

        $this->actingAs($this->coordinator())
            ->from(route('workflow.show', self::ENTITI))
            ->post(route('workflow.peringkat', self::ENTITI), ['to_stage' => 8])
            ->assertSessionHasErrors('to_stage');

        $this->assertDatabaseHas('workflow_status', [
            'agency_code' => self::ENTITI,
            'current_stage' => 7,
        ]);
    }

    public function test_pengunduran_tanpa_sebab_ditolak_melalui_http(): void
    {
        $this->workflowPada(6);

        $this->actingAs($this->coordinator())
            ->from(route('workflow.show', self::ENTITI))
            ->post(route('workflow.peringkat', self::ENTITI), ['to_stage' => 5])
            ->assertSessionHasErrors('to_stage');

        $this->assertDatabaseHas('workflow_status', [
            'agency_code' => self::ENTITI,
            'current_stage' => 6,
        ]);
    }

    public function test_pengunduran_dengan_sebab_diterima_melalui_http(): void
    {
        $this->workflowPada(6);

        $this->actingAs($this->coordinator())
            ->from(route('workflow.show', self::ENTITI))
            ->post(route('workflow.peringkat', self::ENTITI), [
                'to_stage' => 5,
                'reason' => 'Laporan perlu dibetulkan',
            ])
            ->assertSessionHas('success');

        $this->assertDatabaseHas('workflow_status', [
            'agency_code' => self::ENTITI,
            'current_stage' => 5,
            'notes' => 'Laporan perlu dibetulkan',
        ]);

        $this->assertDatabaseHas('activity_log', [
            'agency_code' => self::ENTITI,
            'action' => WorkflowTransitionService::ACTION_STAGE_CHANGED,
            'old_value' => '6',
            'new_value' => '5',
        ]);
    }

    public function test_status_peringkat_dikemas_kini_melalui_http(): void
    {
        $this->workflowPada(3);

        $this->actingAs($this->coordinator())
            ->from(route('workflow.show', self::ENTITI))
            ->post(route('workflow.status', self::ENTITI), ['status' => 'Siap'])
            ->assertSessionHas('success');

        $this->assertDatabaseHas('workflow_status', [
            'agency_code' => self::ENTITI,
            'current_stage' => 3,
            'status' => 'Siap',
        ]);
    }

    public function test_status_tidak_sah_ditolak_melalui_http(): void
    {
        $this->workflowPada(3);

        $this->actingAs($this->coordinator())
            ->from(route('workflow.show', self::ENTITI))
            ->post(route('workflow.status', self::ENTITI), ['status' => 'Ditutup'])
            ->assertSessionHasErrors('status');
    }

    public function test_tindakan_pada_entiti_belum_didaftar_menghasilkan_404(): void
    {
        $this->actingAs($this->coordinator())
            ->post(route('workflow.peringkat', self::ENTITI), ['to_stage' => 2])
            ->assertNotFound();
    }

    public function test_sejarah_peringkat_dipaparkan_pada_halaman_entiti(): void
    {
        $workflow = $this->workflowPada(1);
        $coordinator = $this->coordinator();

        app(WorkflowTransitionService::class)->advance($workflow, $coordinator);

        $this->actingAs($coordinator)
            ->get(route('workflow.show', self::ENTITI))
            ->assertOk()
            ->assertSee('Sejarah Peringkat')
            ->assertSee('Peringkat Workflow Berubah')
            ->assertSee($coordinator->name);
    }
}
