<?php

namespace Tests\Unit;

use App\Support\BorangAnalisis;
use Illuminate\Http\Request;
use Tests\TestCase;

/**
 * FASA 12 — ujian unit pemetaan borang input berstruktur.
 *
 * Fokus: peraturan checkbox algoritma (spesifikasi bahagian 17) dan
 * pemetaan borang → model yang dikongsi antara simpanan draf dan
 * simpanan muktamad (Fasa 6).
 */
class AnalysisFormMappingTest extends TestCase
{
    /**
     * @param  array<string, mixed>  $input
     */
    private function borang(array $input): array
    {
        return BorangAnalisis::daripadaRequest(
            Request::create('/analisis', 'POST', $input)
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function algoritma(string $id, bool $dipilih, string $bilangan = '', string $nota = ''): array
    {
        $medan = ['id' => $id, 'bilangan' => $bilangan, 'nota' => $nota];

        return $dipilih
            ? [md5($id) => $medan + ['dipilih' => '1']]
            : [md5($id) => $medan];
    }

    /*
    |--------------------------------------------------------------------------
    | Checkbox algoritma — spesifikasi bahagian 17
    |--------------------------------------------------------------------------
    */

    public function test_checkbox_ditanda_bermakna_algoritma_digunakan(): void
    {
        $borang = $this->borang([
            'algoritma' => $this->algoritma('Simetrik Blok|AES', true, '12', 'TLS'),
        ]);

        $this->assertArrayHasKey('Simetrik Blok|AES', $borang['algoritma']);
        $this->assertSame('12', $borang['algoritma']['Simetrik Blok|AES']['bilangan']);
        $this->assertSame('TLS', $borang['algoritma']['Simetrik Blok|AES']['nota']);
    }

    public function test_checkbox_tidak_ditanda_bermakna_algoritma_tidak_digunakan(): void
    {
        // Medan bilangan/nota tetap dihantar oleh borang walaupun checkbox
        // tidak ditanda — ia TIDAK boleh menyebabkan algoritma direkodkan.
        $borang = $this->borang([
            'algoritma' => $this->algoritma('Fungsi Cincang|MD5', false, '99', 'nota lama'),
        ]);

        $this->assertSame([], $borang['algoritma']);
    }

    public function test_hanya_algoritma_ditanda_disimpan_apabila_bercampur(): void
    {
        $borang = $this->borang([
            'algoritma' => $this->algoritma('Simetrik Blok|AES', true)
                + $this->algoritma('Simetrik Blok|3DES', false)
                + $this->algoritma('Asimetrik (Penyulitan)|RSA', true),
        ]);

        $this->assertSame(
            ['Simetrik Blok|AES', 'Asimetrik (Penyulitan)|RSA'],
            array_keys($borang['algoritma']),
        );
    }

    public function test_algoritma_tanpa_pengenal_diabaikan(): void
    {
        $borang = $this->borang([
            'algoritma' => [md5('x') => ['dipilih' => '1', 'bilangan' => '3']],
        ]);

        $this->assertSame([], $borang['algoritma']);
    }

    public function test_medan_algoritma_lain_kekal_sebagai_teks_bebas_tambahan(): void
    {
        $borang = $this->borang(['algoritma_lain' => '  SNOW 3G  ']);

        $this->assertSame('SNOW 3G', $borang['algoritma_lain']);
    }

    /*
    |--------------------------------------------------------------------------
    | Baris dinamik — protokol / pustaka / vendor
    |--------------------------------------------------------------------------
    */

    public function test_baris_kosong_dibuang_daripada_senarai_dinamik(): void
    {
        $borang = $this->borang([
            'protokol' => [
                ['nama' => 'TLS', 'versi' => '1.2', 'bilangan' => '4', 'nota' => ''],
                ['nama' => '', 'versi' => '', 'bilangan' => '', 'nota' => ''],
            ],
        ]);

        $this->assertCount(1, $borang['protokol']);
        $this->assertSame('TLS', $borang['protokol'][0]['nama']);
    }

    public function test_baris_dinamik_hanya_menyimpan_kolum_yang_ditakrifkan(): void
    {
        $borang = $this->borang([
            'vendor' => [['nama' => 'Vendor A', 'produk' => 'HSM', 'suntikan' => 'x']],
        ]);

        $this->assertSame(
            ['nama', 'produk', 'versi', 'bilangan', 'nota'],
            array_keys($borang['vendor'][0]),
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Kesimpulan & profil
    |--------------------------------------------------------------------------
    */

    public function test_kesimpulan_terhad_kepada_bank_ayat_rasmi(): void
    {
        $borang = $this->borang([
            'kesimpulan' => ['umum', 'kesimpulan-direka-sendiri', 'legasi'],
        ]);

        $this->assertSame(['umum', 'legasi'], $borang['kesimpulan']);
    }

    public function test_profil_meliputi_setiap_kategori_dengan_nilai_lalai_sifar(): void
    {
        $borang = $this->borang([
            'profil' => [md5('Pelayan') => ['jumlah' => '7', 'nota' => 'kluster']],
        ]);

        $this->assertSame(
            config('kriptografi.kategori_profil'),
            array_keys($borang['profil']),
        );

        $this->assertSame(7, $borang['profil']['Pelayan']['jumlah']);
        $this->assertSame(0, $borang['profil']['Sistem/Aplikasi']['jumlah']);
    }

    public function test_status_data_meliputi_ketiga_tiga_jadual(): void
    {
        $borang = $this->borang([
            'data_status' => ['j0' => ['penerimaan' => 'Diterima']],
        ]);

        $this->assertSame(['j0', 'j1', 'j2'], array_keys($borang['data_status']));
        $this->assertSame('Diterima', $borang['data_status']['j0']['penerimaan']);
        $this->assertSame('Tiada', $borang['data_status']['j1']['penerimaan']);
    }

    /*
    |--------------------------------------------------------------------------
    | Pemetaan borang → model
    |--------------------------------------------------------------------------
    */

    public function test_medan_lajur_diasingkan_daripada_json_data(): void
    {
        ['lajur' => $lajur, 'data' => $data] = BorangAnalisis::kepadaModel($this->borang([
            'tarikh_laporan' => '2026-08-16',
            'kod_rujukan' => 'PTPKM/INV/2026/001',
            'status_laporan' => 'Muktamad dengan Catatan',
            'ringkasan_data' => 'catatan',
        ]));

        $this->assertSame(
            ['tarikh_laporan', 'kod_rujukan', 'status_laporan'],
            array_keys($lajur),
        );

        $this->assertSame('PTPKM/INV/2026/001', $lajur['kod_rujukan']);
        $this->assertSame('catatan', $data['ringkasan_data']);

        foreach (BorangAnalisis::MEDAN_LAJUR as $medan) {
            $this->assertArrayNotHasKey($medan, $data);
        }
    }

    public function test_nilai_lalai_hanya_dikenakan_pada_simpanan_muktamad(): void
    {
        $borang = $this->borang([]);

        // Draf: apa yang pegawai belum isi kekal kosong.
        $this->assertNull($borang['status_laporan']);
        $this->assertNull($borang['ringkasan_data']);

        // Muktamad: nilai lalai dikenakan supaya laporan boleh dijana.
        ['lajur' => $lajur, 'data' => $data] = BorangAnalisis::kepadaModel($borang);

        $this->assertSame('Muktamad', $lajur['status_laporan']);
        $this->assertSame('lengkap', $data['ringkasan_data']);
    }

    public function test_borang_daripada_model_kosong_apabila_tiada_rekod(): void
    {
        $this->assertSame([], BorangAnalisis::daripadaModel(null));
    }
}
