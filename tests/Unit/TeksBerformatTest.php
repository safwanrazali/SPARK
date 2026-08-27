<?php

namespace Tests\Unit;

use App\Support\TeksBerformat;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Medan "Ulasan" ditaip bebas oleh pegawai; kelas ini yang menentukan
 * bagaimana ia dipaparkan dalam laporan rasmi.
 */
class TeksBerformatTest extends TestCase
{
    public static function kosong(): array
    {
        return [
            'null' => [null],
            'rentetan kosong' => [''],
            'ruang sahaja' => ["   \n\n  \t "],
        ];
    }

    #[DataProvider('kosong')]
    public function test_teks_kosong_tiada_blok(?string $teks): void
    {
        $this->assertSame([], TeksBerformat::blok($teks));
    }

    public function test_baris_kosong_memisahkan_perenggan(): void
    {
        $blok = TeksBerformat::blok("Perenggan pertama.\n\nPerenggan kedua.");

        $this->assertCount(2, $blok);
        $this->assertSame('perenggan', $blok[0]['jenis']);
        $this->assertSame('Perenggan pertama.', $blok[0]['isi']);
        $this->assertSame('Perenggan kedua.', $blok[1]['isi']);
    }

    public function test_baris_tunggal_dalam_perenggan_digabungkan(): void
    {
        // Teks laporan dijustifikasikan; pemisah baris manual akan
        // meninggalkan baris pendek di tengah perenggan.
        $blok = TeksBerformat::blok("Ayat dipecahkan\noleh pegawai.");

        $this->assertCount(1, $blok);
        $this->assertSame('Ayat dipecahkan oleh pegawai.', $blok[0]['isi']);
    }

    public function test_penanda_nombor_menjadi_senarai_bernombor(): void
    {
        $blok = TeksBerformat::blok("1. Perkara pertama\n2. Perkara kedua\n3) Perkara ketiga");

        $this->assertCount(1, $blok);
        $this->assertSame('senarai', $blok[0]['jenis']);
        $this->assertTrue($blok[0]['bernombor']);

        // Penanda dibuang supaya tidak berganda dengan nombor CSS.
        $this->assertSame(
            ['Perkara pertama', 'Perkara kedua', 'Perkara ketiga'],
            $blok[0]['isi'],
        );
    }

    public function test_penanda_sengkang_menjadi_senarai_tidak_bernombor(): void
    {
        $blok = TeksBerformat::blok("- Perkara pertama\n- Perkara kedua");

        $this->assertSame('senarai', $blok[0]['jenis']);
        $this->assertFalse($blok[0]['bernombor']);
        $this->assertSame(['Perkara pertama', 'Perkara kedua'], $blok[0]['isi']);
    }

    public function test_perenggan_dan_senarai_boleh_bercampur(): void
    {
        $blok = TeksBerformat::blok(
            "Pengenalan ulasan.\n\n1. Dapatan pertama\n2. Dapatan kedua\n\nPenutup ulasan."
        );

        $this->assertSame(
            ['perenggan', 'senarai', 'perenggan'],
            array_column($blok, 'jenis'),
        );
    }

    public function test_hanya_sebahagian_baris_berpenanda_kekal_perenggan(): void
    {
        // "2026 merupakan..." tidak boleh bertukar menjadi senarai hanya
        // kerana ia bermula dengan digit.
        $blok = TeksBerformat::blok("2026 merupakan tahun rujukan.\nMaklumat lanjut menyusul.");

        $this->assertCount(1, $blok);
        $this->assertSame('perenggan', $blok[0]['jenis']);
    }

    public function test_penanda_tanpa_isi_kekal_perenggan(): void
    {
        $blok = TeksBerformat::blok("1.\n2.");

        $this->assertSame('perenggan', $blok[0]['jenis']);
    }
}
