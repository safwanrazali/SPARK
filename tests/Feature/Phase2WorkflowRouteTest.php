<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\WorkflowStatus;
use App\Services\KemajuanAnalisisService;
use App\Services\WorkflowTransitionService;
use App\Support\AliranKerja;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\MelaluiAliranKerja;
use Tests\TestCase;

/**
 * FASA 2 — route, kebenaran dan paparan stepper workflow.
 */
class Phase2WorkflowRouteTest extends TestCase
{
    use MelaluiAliranKerja, RefreshDatabase;

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
     * Bawa entiti sehingga peringkat $kunci (eksklusif) melalui aliran sebenar.
     *
     * Baris `workflow_status` sahaja TIDAK memadai: aplikasi sentiasa
     * mencipta baris peringkat serentak dengannya (lihat
     * KemajuanAnalisisService::sediakan), dan senarai menguji peringkat 1.1
     * Selesai untuk memutuskan sama ada entiti berada dalam aliran kerja.
     */
    private function workflowPada(string $kunci): WorkflowStatus
    {
        $this->lengkapkanHingga(self::ENTITI, $kunci, $this->coordinator());

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
        $this->workflowPada(AliranKerja::PENYEDIAAN_DATA);

        // Entiti disenaraikan mengikut sektor: senarai hanya wujud setelah
        // satu sektor dipilih.
        $this->actingAs($this->coordinator())
            ->get(route('workflow.index', ['sector_code' => '001']))
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
        $this->workflowPada(AliranKerja::PENYEDIAAN_DATA);

        $ppr = User::factory()->create(['role' => User::ROLE_PENYELARAS_REKOD]);

        $this->actingAs($ppr)
            ->get(route('workflow.index', ['sector_code' => '001']))
            ->assertOk()
            ->assertSee(self::ENTITI)
            ->assertDontSee('Tindakan')
            ->assertDontSee(route('workflow.show', self::ENTITI));

        // Peranan lain kekal mempunyai pautan butiran.
        $this->actingAs($this->coordinator())
            ->get(route('workflow.index', ['sector_code' => '001']))
            ->assertOk()
            ->assertSee('Tindakan')
            ->assertSee(route('workflow.show', self::ENTITI));
    }

    public function test_senarai_boleh_ditapis_mengikut_sektor(): void
    {
        // Entiti sektor 001 mempunyai rekod workflow; penapis sektor 010
        // hendaklah memaparkan entiti sektor tersebut sahaja.
        $this->workflowPada(AliranKerja::PENYEDIAAN_DATA);

        $response = $this->actingAs($this->coordinator())
            ->get(route('workflow.index', ['sector_code' => '010']));

        $response->assertOk()
            ->assertSee('A100102')
            ->assertSee('A100101')
            ->assertSee('Belum Didaftarkan')
            ->assertDontSee('A010101');
    }

    public function test_halaman_entiti_memaparkan_stepper_lima_peringkat_utama(): void
    {
        $this->workflowPada(AliranKerja::SEMAKAN_AWAL_DATA);

        $response = $this->actingAs($this->coordinator())
            ->get(route('workflow.show', self::ENTITI));

        $response->assertOk();

        foreach (WorkflowStatus::WORKFLOW_STAGES as $nama) {
            $response->assertSee($nama);
        }

        // Sub-peringkat berada DI DALAM kumpulan peringkat utamanya.
        $response->assertSee('workflow-utama', false);
        $response->assertSee('workflow-step--semasa', false);
        $response->assertSee('workflow-step--selesai', false);

        foreach (AliranKerja::kekunci() as $kunci) {
            $response->assertSee(AliranKerja::label($kunci));
        }
    }

    /**
     * Entiti yang belum memasuki aliran kerja menerangkan langkah seterusnya
     * DAN menawarkannya: peringkat 1.1 ialah pintu masuk, dan PPA memilikinya.
     */
    public function test_entiti_belum_didaftar_menawarkan_peringkat_pertama(): void
    {
        // Peringkat 1.1 milik KB dan PPA. `coordinator()` di dalam fail ini
        // menghasilkan Pentadbir Sistem (nama warisan fasa terdahulu), jadi
        // PPA sebenar dicipta di sini.
        $ppa = User::factory()->create(['role' => User::ROLE_COORDINATOR]);

        $this->actingAs($ppa)
            ->get(route('workflow.show', self::ENTITI))
            ->assertOk()
            ->assertSee('Belum Memasuki Aliran Kerja')
            ->assertSee('1.1 Penerimaan Data')
            ->assertSee('Tiada perubahan peringkat')
            // Borang peringkat 1.1 tersedia di sini — itulah gantian kepada
            // penandaan pukal skrin Penetapan Entiti yang telah dibuang.
            // Peringkat berderivasi disiapkan melalui `simpan`, bukan `selesai`.
            ->assertSee(route('kemajuan.simpan', [self::ENTITI, AliranKerja::PENERIMAAN_DATA]), false)
            ->assertSee('Tarikh Terima');

        // Membuka halaman sahaja TIDAK memasukkan entiti ke dalam aliran.
        $this->assertDatabaseMissing('workflow_status', ['agency_code' => self::ENTITI]);
    }

    /**
     * Peranan yang tidak memiliki peringkat 1.1 tidak ditawarkan borangnya.
     */
    public function test_entiti_belum_didaftar_tiada_borang_bagi_peranan_lain(): void
    {
        // PPR tidak memiliki peringkat 1.1, dan Pentadbir Sistem pun tidak.
        $ppr = User::factory()->create(['role' => User::ROLE_PENYELARAS_REKOD]);

        $this->actingAs($ppr)
            ->get(route('workflow.show', self::ENTITI))
            ->assertOk()
            ->assertSee('Belum Memasuki Aliran Kerja')
            ->assertDontSee(route('kemajuan.simpan', [self::ENTITI, AliranKerja::PENERIMAAN_DATA]), false)
            // No. Rujukan pun belum berkenaan: ia direkod pada baris peringkat,
            // yang belum wujud.
            ->assertDontSee(route('kemajuan.rujukan', [self::ENTITI, AliranKerja::PENERIMAAN_DATA]), false);
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
