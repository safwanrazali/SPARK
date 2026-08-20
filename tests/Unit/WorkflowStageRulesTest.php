<?php

namespace Tests\Unit;

use App\Models\WorkflowStatus;
use Tests\TestCase;

/**
 * FASA 12 — ujian unit bagi peraturan peralihan peringkat workflow.
 *
 * Peraturan diuji secara terasing daripada pangkalan data, HTTP dan
 * kebenaran peranan: hanya logik peralihan (spesifikasi bahagian 12).
 */
class WorkflowStageRulesTest extends TestCase
{
    private function padaPeringkat(int $stage): WorkflowStatus
    {
        return new WorkflowStatus(['current_stage' => $stage]);
    }

    public function test_tujuh_peringkat_ditakrifkan_mengikut_spesifikasi(): void
    {
        $this->assertSame([
            1 => 'Penerimaan & Pendaftaran Data',
            2 => 'Semakan Awal Data',
            3 => 'Penyediaan & Pengesahan Data',
            4 => 'Analisis Data',
            5 => 'Jana Laporan',
            6 => 'Semakan & Kelulusan',
            7 => 'Penyerahan & Penutupan',
        ], WorkflowStatus::WORKFLOW_STAGES);

        $this->assertSame(1, WorkflowStatus::FIRST_STAGE);
        $this->assertSame(7, WorkflowStatus::LAST_STAGE);
    }

    public function test_peralihan_ke_hadapan_hanya_satu_peringkat(): void
    {
        $workflow = $this->padaPeringkat(3);

        $this->assertTrue($workflow->canTransitionTo(4));
        $this->assertFalse($workflow->canTransitionTo(5));
        $this->assertFalse($workflow->canTransitionTo(7));
    }

    public function test_lompatan_rawak_memberi_mesej_peringkat_seterusnya(): void
    {
        $ralat = $this->padaPeringkat(2)->transitionError(6);

        $this->assertNotNull($ralat);
        $this->assertStringContainsString('berturutan', $ralat);
        $this->assertStringContainsString('Penyediaan & Pengesahan Data', $ralat);
    }

    public function test_peralihan_ke_belakang_dibenarkan_tetapi_memerlukan_sebab(): void
    {
        $workflow = $this->padaPeringkat(5);

        $this->assertTrue($workflow->canTransitionTo(4));
        $this->assertTrue($workflow->canTransitionTo(1));

        $this->assertTrue($workflow->requiresReason(4));
        $this->assertTrue($workflow->requiresReason(1));
        $this->assertFalse($workflow->requiresReason(6));
    }

    public function test_peringkat_di_luar_julat_ditolak(): void
    {
        $workflow = $this->padaPeringkat(1);

        foreach ([0, 8, -1, 99, 'dua', null, []] as $tidakSah) {
            $this->assertFalse(
                $workflow->canTransitionTo($tidakSah),
                'Peringkat tidak sah sepatutnya ditolak: '.json_encode($tidakSah),
            );
        }

        $this->assertFalse(WorkflowStatus::isValidStage(0));
        $this->assertFalse(WorkflowStatus::isValidStage(8));
        $this->assertTrue(WorkflowStatus::isValidStage(7));
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
        $this->assertSame(14, $this->padaPeringkat(1)->progressPercentage());
        $this->assertSame(57, $this->padaPeringkat(4)->progressPercentage());
        $this->assertSame(100, $this->padaPeringkat(7)->progressPercentage());
    }

    public function test_peringkat_seterusnya_dan_penanda_selesai(): void
    {
        $this->assertSame(2, $this->padaPeringkat(1)->getNextStage());
        $this->assertNull($this->padaPeringkat(7)->getNextStage());

        $this->assertFalse($this->padaPeringkat(6)->isComplete());
        $this->assertTrue($this->padaPeringkat(7)->isComplete());
    }

    public function test_penanda_peringkat_dilalui_dan_peringkat_semasa(): void
    {
        $workflow = $this->padaPeringkat(4);

        $this->assertTrue($workflow->isStageCompleted(3));
        $this->assertFalse($workflow->isStageCompleted(4));
        $this->assertFalse($workflow->isStageCompleted(5));

        $this->assertTrue($workflow->isCurrentStage(4));
        $this->assertFalse($workflow->isCurrentStage(3));
    }

    public function test_nama_peringkat_tidak_dikenali_tidak_menyebabkan_ralat(): void
    {
        $this->assertSame('Unknown Stage', WorkflowStatus::getStageName(99));
    }
}
