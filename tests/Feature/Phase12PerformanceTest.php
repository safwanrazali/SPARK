<?php

namespace Tests\Feature;

use App\Models\AnalisisInventori;
use App\Models\StatusLaporan;
use App\Models\User;
use App\Models\WorkflowStatus;
use App\Services\WorkflowTransitionService;
use App\Services\EntityAccessService;
use App\Services\EntityAssignmentService;
use App\Support\SektorDirectory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * FASA 12 — ujian prestasi asas.
 *
 * Kawalan akses entiti (Fasa 4) disemak berkali-kali dalam satu permintaan:
 * middleware, gate, policy dan setiap scope accessibleBy. Ujian ini
 * memastikan kos semakan tersebut TIDAK meningkat seiring bilangan entiti
 * yang dipapar — bilangan query mesti kekal walaupun entiti bertambah.
 */
class Phase12PerformanceTest extends TestCase
{
    use RefreshDatabase;

    private User $penyelaras;

    private User $analyst;

    protected function setUp(): void
    {
        parent::setUp();

        $this->penyelaras = User::factory()->create(['role' => User::ROLE_COORDINATOR]);
        $this->analyst = User::factory()->create(['role' => User::ROLE_ANALYST]);
    }

    /** Bilangan entiti yang telah disediakan setakat ini. */
    private int $disediakan = 0;

    /**
     * Sediakan rekod pemantauan penuh bagi sejumlah entiti TAMBAHAN dan
     * tugaskan kesemuanya kepada Pegawai Analisis.
     *
     * @return array<int, string>
     */
    private function sediakanEntiti(int $bilangan): array
    {
        $assignments = app(EntityAssignmentService::class);
        $kod = [];

        $senarai = SektorDirectory::semuaEntiti()
            ->skip($this->disediakan)
            ->take($bilangan);

        $this->disediakan += $bilangan;

        foreach ($senarai as $entiti) {
            $assignments->assign($entiti, $this->analyst, $this->penyelaras);

            WorkflowStatus::factory()->create($entiti);
            AnalisisInventori::factory()->create($entiti + ['user_id' => $this->analyst->id]);
            StatusLaporan::create($entiti + [
                'jenis' => 'inventori',
                'status' => 'Dalam Proses',
                'user_id' => $this->penyelaras->id,
            ]);

            $kod[] = $entiti['agency_code'];
        }

        $this->analyst = $this->analyst->fresh();

        return $kod;
    }

    private function bilanganQuery(callable $tindakan): int
    {
        DB::flushQueryLog();
        DB::enableQueryLog();

        $tindakan();

        $bilangan = count(DB::getQueryLog());

        DB::disableQueryLog();
        DB::flushQueryLog();

        return $bilangan;
    }

    public function test_semakan_akses_berulang_tidak_mengeluarkan_query_setiap_kali(): void
    {
        $kod = $this->sediakanEntiti(10);
        $access = app(EntityAccessService::class);

        $bilangan = $this->bilanganQuery(function () use ($access, $kod) {
            foreach ($kod as $satu) {
                $access->canAccess($this->analyst, $satu);
            }
        });

        $this->assertLessThanOrEqual(
            1,
            $bilangan,
            sprintf('Semakan akses berulang mengeluarkan %d query — sepatutnya dikira sekali sahaja.', $bilangan),
        );
    }

    public function test_kos_query_senarai_pemantauan_tidak_meningkat_dengan_bilangan_entiti(): void
    {
        $this->sediakanEntiti(5);

        $kecil = $this->bilanganQuery(function () {
            $this->actingAs($this->analyst)->get(route('workflow.index'))->assertOk();
        });

        $this->sediakanEntiti(15);

        $besar = $this->bilanganQuery(function () {
            $this->actingAs($this->analyst)->get(route('workflow.index'))->assertOk();
        });

        $this->assertSame(
            $kecil,
            $besar,
            sprintf(
                'Senarai workflow menggunakan %d query bagi 5 entiti tetapi %d bagi 20 entiti (N+1).',
                $kecil,
                $besar,
            ),
        );
    }

    public function test_kos_query_pusat_maklumat_entiti_kekal_tetap(): void
    {
        $kod = $this->sediakanEntiti(5);

        $kecil = $this->bilanganQuery(function () use ($kod) {
            $this->actingAs($this->analyst)->get(route('entiti.show', $kod[0]))->assertOk();
        });

        $this->sediakanEntiti(15);

        $besar = $this->bilanganQuery(function () use ($kod) {
            $this->actingAs($this->analyst)->get(route('entiti.show', $kod[0]))->assertOk();
        });

        $this->assertSame($kecil, $besar, 'Halaman entiti mengeluarkan query tambahan mengikut bilangan entiti.');
    }

    public function test_papan_pemuka_pengurusan_tidak_membuat_query_bagi_setiap_entiti(): void
    {
        $this->sediakanEntiti(20);

        $bilangan = $this->bilanganQuery(function () {
            $this->actingAs($this->penyelaras)->get(route('dashboard'))->assertOk();
        });

        // Papan pemuka menghimpun beberapa sumber rekod; kosnya mesti kekal
        // pada bilangan sumber, bukan bilangan entiti.
        $this->assertLessThan(
            20,
            $bilangan,
            sprintf('Papan pemuka mengeluarkan %d query bagi 20 entiti.', $bilangan),
        );
    }

    public function test_senarai_jejak_audit_memuatkan_pengguna_secara_berkelompok(): void
    {
        $kod = $this->sediakanEntiti(10);

        // Jana aktiviti tambahan supaya senarai jejak audit mempunyai
        // banyak baris daripada pelbagai pengguna.
        foreach ($kod as $satu) {
            $workflow = WorkflowStatus::where('agency_code', $satu)->first();

            if ($workflow !== null) {
                app(WorkflowTransitionService::class)->advance($workflow, $this->penyelaras);
            }
        }

        $bilangan = $this->bilanganQuery(function () {
            $this->actingAs($this->penyelaras)->get(route('audit.index'))->assertOk();
        });

        $this->assertLessThan(
            10,
            $bilangan,
            sprintf('Senarai jejak audit mengeluarkan %d query (N+1 pada relasi pengguna).', $bilangan),
        );
    }

    public function test_muat_semula_pengguna_membatalkan_ingatan_kawalan_akses(): void
    {
        $kod = $this->sediakanEntiti(3);
        $access = app(EntityAccessService::class);

        $this->assertTrue($access->canAccess($this->analyst, $kod[0]));

        app(EntityAssignmentService::class)->unassign($kod[0], $this->penyelaras);

        // Instance yang sama, selepas refresh(), mesti melihat keadaan terkini.
        $this->analyst->refresh();

        $this->assertFalse($access->canAccess($this->analyst, $kod[0]));
    }
}
