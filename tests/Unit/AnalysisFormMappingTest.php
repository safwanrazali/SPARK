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
    private function algoritma(string $id, bool $dipilih, string $bilangan = ''): array
    {
        $medan = ['id' => $id, 'bilangan' => $bilangan];

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
            'algoritma' => $this->algoritma('Sifer Blok|AES', true, '12'),
        ]);

        $this->assertArrayHasKey('Sifer Blok|AES', $borang['algoritma']);
        $this->assertSame('12', $borang['algoritma']['Sifer Blok|AES']['bilangan']);

        // Medan "Pemerhatian" telah dibuang daripada seksyen algoritma.
        $this->assertArrayNotHasKey('nota', $borang['algoritma']['Sifer Blok|AES']);
    }

    public function test_checkbox_tidak_ditanda_bermakna_algoritma_tidak_digunakan(): void
    {
        // Medan bilangan tetap dihantar oleh borang walaupun checkbox
        // tidak ditanda — ia TIDAK boleh menyebabkan algoritma direkodkan.
        $borang = $this->borang([
            'algoritma' => $this->algoritma('Legasi / Luar Senarai AKSA MySEAL|MD5', false, '99'),
        ]);

        $this->assertSame([], $borang['algoritma']);
    }

    public function test_hanya_algoritma_ditanda_disimpan_apabila_bercampur(): void
    {
        $borang = $this->borang([
            'algoritma' => $this->algoritma('Sifer Blok|AES', true)
                + $this->algoritma('Legasi / Luar Senarai AKSA MySEAL|3DES', false)
                + $this->algoritma('Legasi / Luar Senarai AKSA MySEAL|RSA', true),
        ]);

        $this->assertSame(
            ['Sifer Blok|AES', 'Legasi / Luar Senarai AKSA MySEAL|RSA'],
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
        // Rentetan tunggal (borang lama) dinormalkan kepada satu pasangan;
        // bilangan kekal kosong kerana ia tidak pernah direkodkan dahulu.
        $borang = $this->borang(['algoritma_lain' => '  SNOW 3G  ']);

        $this->assertSame(
            [['nama' => 'SNOW 3G', 'bilangan' => '']],
            $borang['algoritma_lain'],
        );
    }

    public function test_medan_algoritma_lain_menerima_beberapa_algoritma(): void
    {
        // Katalog checkbox mengandungi AKSA MySEAL (Approved) sahaja, jadi
        // algoritma lapuk/klasik direkodkan di sini — selalunya lebih daripada
        // satu, masing-masing dengan bilangan sistem/aset tersendiri.
        $borang = $this->borang(['algoritma_lain' => [
            ['nama' => '3DES', 'bilangan' => '3'],
            ['nama' => '  RC4  ', 'bilangan' => ''],
            ['nama' => '', 'bilangan' => '9'],
            ['nama' => 'MD5', 'bilangan' => '12'],
        ]]);

        // Baris tanpa nama digugurkan walaupun bilangannya diisi.
        $this->assertSame([
            ['nama' => '3DES', 'bilangan' => '3'],
            ['nama' => 'RC4', 'bilangan' => ''],
            ['nama' => 'MD5', 'bilangan' => '12'],
        ], $borang['algoritma_lain']);
    }

    public function test_senarai_rentetan_lama_algoritma_lain_masih_terbaca(): void
    {
        // Bentuk perantaraan (senarai rentetan) sebelum medan bilangan wujud.
        $borang = $this->borang(['algoritma_lain' => ['3DES', 'RC4']]);

        $this->assertSame([
            ['nama' => '3DES', 'bilangan' => ''],
            ['nama' => 'RC4', 'bilangan' => ''],
        ], $borang['algoritma_lain']);
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
                ['nama' => 'TLS', 'versi' => '1.2', 'bilangan' => '4'],
                ['nama' => '', 'versi' => '', 'bilangan' => ''],
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

        // Kunci luar takrifan ('suntikan') digugurkan; medan 'nota' telah
        // dibuang daripada seksyen vendor mengikut templat rasmi.
        $this->assertSame(
            ['nama', 'produk', 'bilangan'],
            array_keys($borang['vendor'][0]),
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Kesimpulan & profil
    |--------------------------------------------------------------------------
    */

    public function test_kesimpulan_disimpan_sebagai_teks_bebas(): void
    {
        // Bank ayat dan kotak semak telah dibuang: kesimpulan berbeza bagi
        // setiap entiti, jadi ia ditaip sepenuhnya oleh pegawai.
        $borang = $this->borang([
            'kesimpulan' => '  Perenggan pertama.

Perenggan kedua.  ',
        ]);

        $this->assertSame('Perenggan pertama.

Perenggan kedua.', $borang['kesimpulan']);
    }

    public function test_kesimpulan_daripada_borang_lama_tidak_meruntuhkan_simpanan(): void
    {
        // Tab lama masih menghantar senarai ID kotak semak; ID itu tiada makna
        // sekarang dan mesti diabaikan tanpa ralat.
        $borang = $this->borang(['kesimpulan' => ['umum', 'legasi']]);

        $this->assertSame('', $borang['kesimpulan']);
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
            'data_status' => ['j0' => ['kebolehgunaan' => 'Lengkap']],
        ]);

        $this->assertSame(['j0', 'j1', 'j2'], array_keys($borang['data_status']));
        $this->assertSame('Lengkap', $borang['data_status']['j0']['kebolehgunaan']);

        // Jadual yang tidak disentuh tidak boleh dianggap lengkap secara senyap.
        $this->assertSame('Tidak Lengkap', $borang['data_status']['j1']['kebolehgunaan']);

        // Medan "Penerimaan" telah dibuang: ia bertindih dengan kebolehgunaan.
        $this->assertArrayNotHasKey('penerimaan', $borang['data_status']['j0']);
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
            'status_laporan' => 'Memerlukan Tindakan Susulan',
        ]));

        $this->assertSame(
            ['tarikh_laporan', 'kod_rujukan', 'status_laporan'],
            array_keys($lajur),
        );

        $this->assertSame('PTPKM/INV/2026/001', $lajur['kod_rujukan']);

        foreach (BorangAnalisis::MEDAN_LAJUR as $medan) {
            $this->assertArrayNotHasKey($medan, $data);
        }
    }

    public function test_nilai_lalai_hanya_dikenakan_pada_simpanan_muktamad(): void
    {
        $borang = $this->borang([]);

        // Draf: apa yang pegawai belum isi kekal kosong.
        $this->assertNull($borang['status_laporan']);

        // Simpanan muktamad: nilai lalai dikenakan supaya laporan boleh dijana.
        ['lajur' => $lajur, 'data' => $data] = BorangAnalisis::kepadaModel($borang);

        $this->assertSame('Selesai', $lajur['status_laporan']);

        // Medan "Ringkasan Status Data" telah dibuang sepenuhnya: ia tidak
        // lagi dipaparkan dalam laporan, jadi tiada nilai lalai dikenakan.
        $this->assertArrayNotHasKey('ringkasan_data', $data);
    }

    public function test_borang_daripada_model_kosong_apabila_tiada_rekod(): void
    {
        $this->assertSame([], BorangAnalisis::daripadaModel(null));
    }
}
