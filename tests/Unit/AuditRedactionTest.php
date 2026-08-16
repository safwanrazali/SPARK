<?php

namespace Tests\Unit;

use App\Services\AuditTrailService;
use Tests\TestCase;

/**
 * FASA 12 — ujian unit penapisan metadata jejak audit (Fasa 8).
 *
 * Jejak audit merekod PERUBAHAN, bukan kandungan dapatan analisis.
 * Kunci sensitif tidak boleh sekali-kali tersimpan, termasuk apabila
 * ia bersarang di dalam metadata.
 */
class AuditRedactionTest extends TestCase
{
    private AuditTrailService $audit;

    protected function setUp(): void
    {
        parent::setUp();

        $this->audit = new AuditTrailService;
    }

    public function test_kunci_sensitif_dibuang_daripada_metadata(): void
    {
        $bersih = $this->audit->tapis([
            'version' => 3,
            'password' => 'rahsia',
            'token' => 'abc',
            'api_key' => 'xyz',
            'data' => ['dapatan' => 'analisis penuh'],
            'section_data' => ['algoritma' => []],
        ]);

        $this->assertSame(['version' => 3], $bersih);
    }

    public function test_penapisan_berlaku_pada_metadata_bersarang(): void
    {
        $bersih = $this->audit->tapis([
            'perubahan' => [
                'kod_rujukan' => 'REF-1',
                'password' => 'rahsia',
                'dalam' => ['secret' => 'x', 'versi' => 2],
            ],
        ]);

        $this->assertSame([
            'perubahan' => [
                'kod_rujukan' => 'REF-1',
                'dalam' => ['versi' => 2],
            ],
        ], $bersih);
    }

    public function test_penapisan_tidak_mengambil_kira_huruf_besar_kecil(): void
    {
        $bersih = $this->audit->tapis(['PASSWORD' => 'rahsia', 'Token' => 'abc', 'nota' => 'kekal']);

        $this->assertSame(['nota' => 'kekal'], $bersih);
    }

    public function test_nilai_teks_panjang_dipotong(): void
    {
        $panjang = str_repeat('a', AuditTrailService::HAD_TEKS + 100);

        $bersih = $this->audit->tapis(['nota' => $panjang]);

        $this->assertSame(AuditTrailService::HAD_TEKS + 1, mb_strlen($bersih['nota']));
        $this->assertStringEndsWith('…', $bersih['nota']);
    }

    public function test_nilai_bukan_teks_dikekalkan_sebagaimana_adanya(): void
    {
        $bersih = $this->audit->tapis([
            'versi' => 3,
            'selesai' => true,
            'peratus' => 57.5,
            'kosong' => null,
        ]);

        $this->assertSame([
            'versi' => 3,
            'selesai' => true,
            'peratus' => 57.5,
            'kosong' => null,
        ], $bersih);
    }
}
