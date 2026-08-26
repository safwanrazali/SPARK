<?php

namespace Tests\Unit;

use App\Models\StatusLaporan;
use App\Support\SektorDirectory;
use Tests\TestCase;

/**
 * FASA 12 — ujian unit senarai induk sektor/entiti (spesifikasi bahagian 7)
 * dan kitaran status laporan (spesifikasi bahagian 22).
 */
class SectorDirectoryAndStatusTest extends TestCase
{
    /*
    |--------------------------------------------------------------------------
    | Sektor → Entiti
    |--------------------------------------------------------------------------
    */

    public function test_setiap_entiti_dipetakan_kepada_sektor_induknya(): void
    {
        $entiti = SektorDirectory::cariEntiti('A010101');

        $this->assertSame([
            'sector_code' => '001',
            'sector_name' => 'Kerajaan',
            'agency_code' => 'A010101',
            'agency_name' => 'Suruhanjaya Pilihan Raya (SPR)',
        ], $entiti);
    }

    public function test_entiti_dalam_sektor_hanya_mengandungi_sektor_tersebut(): void
    {
        $dalamSektor = SektorDirectory::entitiDalamSektor('001');

        $this->assertNotEmpty($dalamSektor);
        $this->assertSame(
            ['001'],
            $dalamSektor->pluck('sector_code')->unique()->values()->all(),
        );
    }

    public function test_kod_entiti_dalam_senarai_induk_adalah_unik(): void
    {
        $semua = SektorDirectory::semuaEntiti()->pluck('agency_code');

        $this->assertSame(
            $semua->count(),
            $semua->unique()->count(),
            'Kod entiti berganda dalam config/sektor.php.',
        );
    }

    public function test_carian_entiti_tidak_dikenali_mengembalikan_null(): void
    {
        $this->assertNull(SektorDirectory::cariEntiti('TIADA-KOD'));
        $this->assertNull(SektorDirectory::cariEntiti(null));
    }

    public function test_semakan_kewujudan_sektor(): void
    {
        $this->assertTrue(SektorDirectory::sektorWujud('001'));
        $this->assertFalse(SektorDirectory::sektorWujud('999'));
        $this->assertFalse(SektorDirectory::sektorWujud(''));
        $this->assertFalse(SektorDirectory::sektorWujud(null));
    }

    /*
    |--------------------------------------------------------------------------
    | Perbendaharaan paparan status laporan
    |--------------------------------------------------------------------------
    */

    public function test_perbendaharaan_paparan_status_laporan(): void
    {
        $this->assertSame(
            ['Belum Bermula', 'Dalam Proses', 'Dalam Semakan', 'Selesai'],
            StatusLaporan::PAPARAN,
        );
    }

    public function test_hanya_selesai_bertukar_hijau(): void
    {
        $this->assertSame('status-rendah', StatusLaporan::badgePaparan(StatusLaporan::PAPARAN_SELESAI));
        $this->assertSame('status-sederhana', StatusLaporan::badgePaparan(StatusLaporan::PAPARAN_DALAM_SEMAKAN));
        $this->assertSame('status-sederhana', StatusLaporan::badgePaparan(StatusLaporan::PAPARAN_DALAM_PROSES));
        $this->assertSame('status-tinggi', StatusLaporan::badgePaparan(StatusLaporan::PAPARAN_BELUM_BERMULA));
        $this->assertSame('status-tinggi', StatusLaporan::badgePaparan('Entah Apa'));
    }

    public function test_tiga_jenis_laporan_ditakrifkan(): void
    {
        $this->assertSame(
            ['inventori', 'risiko', 'kesiapsiagaan'],
            array_keys(StatusLaporan::JENIS),
        );
    }
}
