<?php

namespace Tests\Unit;

use App\Models\WorkflowStatus;
use App\Support\AliranKerja;
use Tests\TestCase;

/**
 * Ujian unit bagi peraturan peralihan antara peringkat UTAMA.
 *
 * Peraturan diuji secara terasing daripada pangkalan data, HTTP dan
 * kebenaran peranan. Struktur bersarang (sub-peringkat) diuji berasingan
 * dalam AliranKerjaTest — di sini hanya peralihan peringkat utama.
 */
class WorkflowStageRulesTest extends TestCase
{
    private function padaPeringkat(int $stage): WorkflowStatus
    {
        return new WorkflowStatus(['current_stage' => $stage]);
    }

    public function test_lima_peringkat_utama_ditakrifkan_mengikut_spesifikasi(): void
    {
        $this->assertSame([
            1 => 'Penerimaan & Semakan Awal Data',
            2 => 'Penyediaan & Pengesahan Data',
            3 => 'Analisis Data',
            4 => 'Penjanaan Laporan',
            5 => 'Semakan, Kelulusan & Penyerahan Laporan',
        ], WorkflowStatus::WORKFLOW_STAGES);

        $this->assertSame(1, WorkflowStatus::FIRST_STAGE);
        $this->assertSame(5, WorkflowStatus::LAST_STAGE);
    }

    public function test_peralihan_ke_hadapan_hanya_satu_peringkat(): void
    {
        $workflow = $this->padaPeringkat(2);

        $this->assertTrue($workflow->canTransitionTo(3));
        $this->assertFalse($workflow->canTransitionTo(4));
        $this->assertFalse($workflow->canTransitionTo(5));
    }

    public function test_lompatan_rawak_memberi_mesej_peringkat_seterusnya(): void
    {
        $ralat = $this->padaPeringkat(1)->transitionError(4);

        $this->assertNotNull($ralat);
        $this->assertStringContainsString('berturutan', $ralat);
        $this->assertStringContainsString('Penyediaan & Pengesahan Data', $ralat);
    }

    public function test_peralihan_ke_belakang_dibenarkan_tetapi_memerlukan_sebab(): void
    {
        $workflow = $this->padaPeringkat(4);

        $this->assertTrue($workflow->canTransitionTo(3));
        $this->assertTrue($workflow->canTransitionTo(1));

        $this->assertTrue($workflow->requiresReason(3));
        $this->assertTrue($workflow->requiresReason(1));
        $this->assertFalse($workflow->requiresReason(5));
    }

    public function test_peringkat_di_luar_julat_ditolak(): void
    {
        $workflow = $this->padaPeringkat(1);

        foreach ([0, 6, -1, 99, 'dua', null, []] as $tidakSah) {
            $this->assertFalse(
                $workflow->canTransitionTo($tidakSah),
                'Peringkat tidak sah sepatutnya ditolak: '.json_encode($tidakSah),
            );
        }

        $this->assertFalse(WorkflowStatus::isValidStage(0));
        $this->assertFalse(WorkflowStatus::isValidStage(6));
        $this->assertTrue(WorkflowStatus::isValidStage(5));
    }

    public function test_peralihan_ke_peringkat_yang_sama_ditolak(): void
    {
        $ralat = $this->padaPeringkat(3)->transitionError(3);

        $this->assertNotNull($ralat);
        $this->assertStringContainsString('kemas kini status', $ralat);
    }

    public function test_status_kerja_menggunakan_semula_kitaran_status_laporan(): void
    {
        $this->assertSame(['Belum Bermula', 'Dalam Proses', 'Siap'], WorkflowStatus::STATUSES);
        $this->assertTrue(WorkflowStatus::isValidStatus('Dalam Proses'));
        $this->assertFalse(WorkflowStatus::isValidStatus('Diluluskan'));
        $this->assertFalse(WorkflowStatus::isValidStatus(''));
    }

    public function test_kemajuan_dikira_daripada_peringkat_bukan_nilai_manual(): void
    {
        $this->assertSame(20, $this->padaPeringkat(1)->progressPercentage());
        $this->assertSame(60, $this->padaPeringkat(3)->progressPercentage());
        $this->assertSame(100, $this->padaPeringkat(5)->progressPercentage());
    }

    public function test_peringkat_seterusnya_dan_penanda_selesai(): void
    {
        $this->assertSame(2, $this->padaPeringkat(1)->getNextStage());
        $this->assertNull($this->padaPeringkat(5)->getNextStage());

        $this->assertFalse($this->padaPeringkat(4)->isComplete());
        $this->assertTrue($this->padaPeringkat(5)->isComplete());
    }

    public function test_penanda_peringkat_dilalui_dan_peringkat_semasa(): void
    {
        $workflow = $this->padaPeringkat(3);

        $this->assertTrue($workflow->isStageCompleted(2));
        $this->assertFalse($workflow->isStageCompleted(3));
        $this->assertFalse($workflow->isStageCompleted(4));

        $this->assertTrue($workflow->isCurrentStage(3));
        $this->assertFalse($workflow->isCurrentStage(2));
    }

    /**
     * Kedudukan semasa dipaparkan dengan sub-peringkatnya apabila ada —
     * "1.2 Pendaftaran Data", bukan sekadar "1".
     */
    public function test_label_kedudukan_semasa_membawa_sub_peringkat(): void
    {
        $workflow = new WorkflowStatus([
            'current_stage' => 1,
            'current_stage_key' => AliranKerja::PENDAFTARAN_DATA,
        ]);

        $this->assertSame('1.2 Pendaftaran Data', $workflow->currentStageLabel());
    }

    /**
     * Peringkat utama tanpa sub-peringkat kekal dipaparkan sebagai satu
     * nombor sahaja — tiada "2.1" direka untuk proses tunggal.
     */
    public function test_peringkat_tanpa_sub_peringkat_tidak_diberi_nombor_sub(): void
    {
        $workflow = new WorkflowStatus([
            'current_stage' => 2,
            'current_stage_key' => AliranKerja::PENYEDIAAN_DATA,
        ]);

        $this->assertSame('2 Penyediaan & Pengesahan Data', $workflow->currentStageLabel());
    }

    public function test_nama_peringkat_tidak_dikenali_tidak_menyebabkan_ralat(): void
    {
        $this->assertSame('Peringkat Tidak Dikenali', WorkflowStatus::getStageName(99));
    }
}
