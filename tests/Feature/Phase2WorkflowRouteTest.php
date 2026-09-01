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
     * Pautan "Entiti" dan "Kemajuan" ialah navigasi, bukan tindakan: SETIAP
     * peranan yang boleh membuka skrin ini mendapatnya, termasuk Pegawai
     * Penyelaras Rekod.
     *
     * Kebenaran sebenar tetap dikuatkuasakan pada halaman yang dituju —
     * PPR hanya melihat entiti yang telah memulakan Penerimaan Data.
     */
    public function test_setiap_peranan_melihat_pautan_entiti_dan_kemajuan(): void
    {
        $this->workflowPada(AliranKerja::PENYEDIAAN_DATA);

        $ppr = User::factory()->create(['role' => User::ROLE_PENYELARAS_REKOD]);

        foreach ([$ppr, $this->coordinator()] as $pengguna) {
            $this->actingAs($pengguna->fresh())
                ->get(route('workflow.index', ['sector_code' => '001']))
                ->assertOk()
                ->assertSee(self::ENTITI)
                ->assertSee('Tindakan')
                ->assertSee(route('entiti.show', self::ENTITI), false)
                ->assertSee(route('workflow.show', self::ENTITI), false);
        }
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
        // Pentadbir Sistem melihat entiti tetapi tidak memiliki peringkat 1.1.
        $this->actingAs($this->coordinator())
            ->get(route('workflow.show', self::ENTITI))
            ->assertOk()
            ->assertSee('Belum Memasuki Aliran Kerja')
            ->assertDontSee(route('kemajuan.simpan', [self::ENTITI, AliranKerja::PENERIMAAN_DATA]), false)
            ->assertDontSee(route('kemajuan.rujukan', [self::ENTITI, AliranKerja::PENERIMAAN_DATA]), false);
    }

    /**
     * PPR tidak melihat entiti yang belum memulakan Penerimaan Data langsung —
     * bukan sekadar tanpa borang.
     */
    public function test_ppr_tidak_melihat_entiti_yang_belum_bermula(): void
    {
        $ppr = User::factory()->create(['role' => User::ROLE_PENYELARAS_REKOD]);

        $this->actingAs($ppr)
            ->get(route('workflow.show', self::ENTITI))
            ->assertForbidden();
    }

    public function test_entiti_di_luar_senarai_induk_menghasilkan_404(): void
    {
        $this->actingAs($this->coordinator())
            ->get(route('workflow.show', 'ZZZ9999'))
            ->assertNotFound();
    }

    /**
     * Kad "Sejarah Peringkat" telah dibuang daripada halaman Kemajuan kerana
     * ia mengulang "Maklumat Peringkat".
     *
     * Jejaknya TIDAK hilang: setiap perubahan terus direkodkan dalam
     * activity_log dan dibaca melalui modul Log Audit.
     */
    public function test_halaman_kemajuan_tiada_kad_sejarah_peringkat(): void
    {
        $workflow = $this->workflowPada(AliranKerja::PENDAFTARAN_DATA);
        $coordinator = $this->coordinator();

        app(WorkflowTransitionService::class)->advance($workflow, $coordinator);

        $this->actingAs($coordinator)
            ->get(route('workflow.show', self::ENTITI))
            ->assertOk()
            ->assertSee('Maklumat Peringkat')
            ->assertDontSee('Sejarah Peringkat');

        // Rekod jejak audit kekal wujud.
        $this->assertDatabaseHas('activity_log', [
            'agency_code' => self::ENTITI,
            'action' => 'workflow_stage_changed',
        ]);
    }
}
