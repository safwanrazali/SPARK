<?php

namespace Tests\Unit;

use App\Support\SeksyenAnalisis;
use Tests\TestCase;

/**
 * FASA 12 — ujian unit pembahagian borang kepada seksyen (Fasa 6).
 *
 * Seksyen menentukan unit simpanan draf dan penunjuk kemajuan pada UI.
 * Pembahagian mesti kekal lengkap: setiap medan borang dimiliki oleh
 * tepat satu seksyen, dan gabungan semula tidak boleh kehilangan data.
 */
class AnalysisSectionTest extends TestCase
{
    public function test_sembilan_seksyen_mengikut_templat_laporan(): void
    {
        $this->assertSame([
            'maklumat', 'data_status', 'profil', 'algoritma', 'protokol',
            'pustaka', 'vendor', 'tindakan', 'kesimpulan',
        ], SeksyenAnalisis::kunci());
    }

    public function test_setiap_medan_dimiliki_oleh_tepat_satu_seksyen(): void
    {
        $medan = [];

        foreach (SeksyenAnalisis::SEKSYEN as $seksyen => $takrif) {
            foreach ($takrif['medan'] as $satu) {
                $this->assertArrayNotHasKey(
                    $satu,
                    $medan,
                    "Medan [{$satu}] dimiliki oleh lebih daripada satu seksyen.",
                );

                $medan[$satu] = $seksyen;
            }
        }

        $this->assertNotEmpty($medan);
    }

    public function test_pecahkan_dan_gabung_mengekalkan_keadaan_borang(): void
    {
        $borang = [
            'tarikh_laporan' => '2026-08-16',
            'kod_rujukan' => 'PTPKM/INV/2026/001',
            'status_laporan' => 'Muktamad',
            'ringkasan_data' => 'lengkap',
            'data_status' => ['j0' => ['penerimaan' => 'Diterima']],
            'profil' => ['Pelayan' => ['jumlah' => 3, 'nota' => '']],
            'algoritma' => ['Simetrik Blok|AES' => ['bilangan' => '3', 'nota' => '']],
            'algoritma_lain' => '',
            'protokol' => [],
            'pustaka' => [],
            'vendor' => [],
            'tindakan' => [1],
            'tindakan_lain' => '',
            'kesimpulan' => ['umum'],
            'kesimpulan_lain' => '',
        ];

        $digabung = SeksyenAnalisis::gabung(SeksyenAnalisis::pecahkan($borang));

        $this->assertEquals($borang, $digabung);
    }

    public function test_medan_yang_belum_diisi_kekal_null_selepas_dipecahkan(): void
    {
        $seksyen = SeksyenAnalisis::pecahkan(['kod_rujukan' => 'REF-1']);

        $this->assertSame('REF-1', $seksyen['maklumat']['kod_rujukan']);
        $this->assertNull($seksyen['maklumat']['tarikh_laporan']);
        $this->assertNull($seksyen['algoritma']['algoritma']);
    }

    public function test_seksyen_dikira_ada_kandungan_hanya_apabila_diisi(): void
    {
        $kosong = SeksyenAnalisis::pecahkan([]);

        foreach (SeksyenAnalisis::kunci() as $seksyen) {
            $this->assertFalse(
                SeksyenAnalisis::adaKandungan($seksyen, $kosong[$seksyen]),
                "Seksyen [{$seksyen}] tidak sepatutnya dikira selesai ketika kosong.",
            );
        }
    }

    public function test_penunjuk_kandungan_bagi_setiap_jenis_seksyen(): void
    {
        $this->assertTrue(SeksyenAnalisis::adaKandungan('maklumat', ['kod_rujukan' => 'REF-1']));
        $this->assertFalse(SeksyenAnalisis::adaKandungan('maklumat', ['kod_rujukan' => '   ']));

        $this->assertTrue(SeksyenAnalisis::adaKandungan('data_status', ['ringkasan_data' => 'lengkap']));
        $this->assertTrue(SeksyenAnalisis::adaKandungan('data_status', [
            'data_status' => ['j0' => ['nota' => 'perlu pengesahan']],
        ]));

        $this->assertTrue(SeksyenAnalisis::adaKandungan('profil', ['profil' => ['Pelayan' => ['jumlah' => 2]]]));
        $this->assertFalse(SeksyenAnalisis::adaKandungan('profil', ['profil' => ['Pelayan' => ['jumlah' => 0, 'nota' => '']]]));

        $this->assertTrue(SeksyenAnalisis::adaKandungan('algoritma', ['algoritma' => ['Simetrik Blok|AES' => []]]));
        $this->assertTrue(SeksyenAnalisis::adaKandungan('protokol', ['protokol' => [['nama' => 'TLS']]]));
        $this->assertTrue(SeksyenAnalisis::adaKandungan('tindakan', ['tindakan' => [0]]));
        $this->assertTrue(SeksyenAnalisis::adaKandungan('kesimpulan', ['kesimpulan_lain' => 'nota']));
    }

    public function test_seksyen_tidak_dikenali_tidak_menyebabkan_ralat(): void
    {
        $this->assertFalse(SeksyenAnalisis::wujud('tiada-seksyen-ini'));
        $this->assertFalse(SeksyenAnalisis::wujud(null));
        $this->assertFalse(SeksyenAnalisis::adaKandungan('tiada-seksyen-ini', ['apa' => 'pun']));
        $this->assertSame('tiada-seksyen-ini', SeksyenAnalisis::label('tiada-seksyen-ini'));
    }
}
