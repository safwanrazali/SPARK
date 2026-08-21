<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\WorkflowStatus;
use App\Services\EntityAssignmentService;
use App\Services\KemajuanAnalisisService;
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

    /**
     * Kawalan penyeliaan peringkat (mula/peringkat/status) kini dihadkan
     * kepada Pentadbir Sistem, jadi pelakon ujian ini ialah PS.
     */
    private function coordinator(): User
    {
        return User::factory()->create(['role' => User::ROLE_ADMINISTRATOR]);
    }

    private function analyst(): User
    {
        return User::factory()->create(['role' => User::ROLE_ANALYST]);
    }

    /**
     * Bawa entiti ke peringkat $stage melalui pendaftaran sebenar.
     *
     * Baris `workflow_status` sahaja TIDAK memadai: aplikasi sentiasa
     * mencipta baris peringkat serentak dengannya (lihat
     * KemajuanAnalisisService::sediakan), dan senarai menguji peringkat 01
     * Selesai untuk memutuskan sama ada entiti berada dalam aliran kerja.
     */
    private function workflowPada(int $stage): WorkflowStatus
    {
        $kemajuan = app(KemajuanAnalisisService::class);

        $kemajuan->lengkapkanPendaftaran(SektorDirectory::cariEntiti(self::ENTITI), $this->coordinator());

        for ($peringkat = WorkflowStatus::FIRST_STAGE + 1; $peringkat < $stage; $peringkat++) {
            $kemajuan->tandakanSelesai(self::ENTITI, $peringkat);
        }

        return WorkflowStatus::where('agency_code', self::ENTITI)->firstOrFail();
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

    /**
     * Pegawai Penyelaras Rekod memerhati sahaja pada skrin ini, jadi lajur
     * Tindakan tidak dipaparkan langsung kepadanya.
     */
    public function test_ppr_tidak_melihat_lajur_tindakan(): void
    {
        $this->workflowPada(3);

        $ppr = User::factory()->create(['role' => User::ROLE_PENYELARAS_REKOD]);

        $this->actingAs($ppr)
            ->get(route('workflow.index'))
            ->assertOk()
            ->assertSee(self::ENTITI)
            ->assertDontSee('Tindakan')
            ->assertDontSee(route('workflow.show', self::ENTITI));

        // Peranan lain kekal mempunyai pautan butiran.
        $this->actingAs($this->coordinator())
            ->get(route('workflow.index'))
            ->assertOk()
            ->assertSee('Tindakan')
            ->assertSee(route('workflow.show', self::ENTITI));
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

    public function test_entiti_belum_didaftar_menerangkan_langkah_seterusnya(): void
    {
        // Tiada butang pendaftaran manual lagi: halaman ini kini menerangkan
        // bahawa Pegawai Penyelaras Rekod perlu menandakannya pada skrin
        // Penetapan Entiti.
        $this->actingAs($this->coordinator())
            ->get(route('workflow.show', self::ENTITI))
            ->assertOk()
            ->assertSee('belum didaftarkan dalam workflow', false)
            ->assertSee('Penetapan Entiti')
            ->assertSee('Tiada perubahan peringkat')
            ->assertDontSee('Daftar Dalam Workflow');

        $this->assertDatabaseMissing('workflow_status', ['agency_code' => self::ENTITI]);
    }

    public function test_entiti_di_luar_senarai_induk_menghasilkan_404(): void
    {
        $this->actingAs($this->coordinator())
            ->get(route('workflow.show', 'ZZZ9999'))
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
