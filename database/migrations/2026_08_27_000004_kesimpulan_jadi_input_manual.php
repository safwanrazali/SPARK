<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/*
 * Kesimpulan kini ditaip sepenuhnya oleh pegawai — kotak semak dan bank ayat
 * dalam config telah dibuang kerana kesimpulan berbeza bagi setiap entiti.
 *
 * Rekod sedia ada menyimpan `kesimpulan` sebagai senarai ID pilihan; teksnya
 * hanya wujud dalam config. Membuang bank tanpa migrasi akan MELENYAPKAN
 * kesimpulan yang telah dipilih pegawai daripada laporan.
 *
 * Migrasi ini menyelesaikan setiap ID kepada ayat penuhnya dan menyimpannya
 * sebagai teks dalam `kesimpulan`, digabungkan dengan `kesimpulan_lain` yang
 * sedia ada. Hasilnya boleh terus disunting dalam borang baharu.
 *
 * Entri 'lapuk' tiada ayat statik — ia dibina daripada algoritma tidak
 * disyorkan yang direkodkan rekod itu sendiri, jadi logik tersebut diulang
 * di sini supaya ayat yang tersimpan sepadan dengan yang pernah dipaparkan.
 */
return new class extends Migration
{
    public function up(): void
    {
        $bank = config('kriptografi.kesimpulan');
        $lapukRujukan = config('kriptografi.tidak_disyorkan');

        foreach ([['analisis_inventori', 'data'], ['analisis_draft_history', 'section_data']] as [$jadual, $lajur]) {
            DB::table($jadual)->orderBy('id')->chunkById(200, function ($baris) use ($jadual, $lajur, $bank, $lapukRujukan) {
                foreach ($baris as $satu) {
                    $data = json_decode((string) $satu->{$lajur}, true);

                    if (! is_array($data)) {
                        continue;
                    }

                    $pilihan = $data['kesimpulan'] ?? null;

                    // Sudah berbentuk teks (atau tiada) — tiada apa perlu dibuat.
                    if (! is_array($pilihan)) {
                        if (array_key_exists('kesimpulan_lain', $data)) {
                            $data['kesimpulan'] = trim((string) ($data['kesimpulan'] ?? ''))
                                ?: trim((string) $data['kesimpulan_lain']);
                            unset($data['kesimpulan_lain']);
                            DB::table($jadual)->where('id', $satu->id)->update([$lajur => json_encode($data)]);
                        }

                        continue;
                    }

                    $perenggan = [];

                    foreach ($pilihan as $id) {
                        if (! isset($bank[$id])) {
                            continue;
                        }

                        $perenggan[] = $id === 'lapuk'
                            ? $this->ayatLapuk($data, $lapukRujukan)
                            : ($bank[$id]['teks'] ?? '');
                    }

                    if (($lain = trim((string) ($data['kesimpulan_lain'] ?? ''))) !== '') {
                        $perenggan[] = $lain;
                    }

                    $data['kesimpulan'] = implode("\n\n", array_filter($perenggan));
                    unset($data['kesimpulan_lain']);

                    DB::table($jadual)->where('id', $satu->id)->update([$lajur => json_encode($data)]);
                }
            });
        }
    }

    public function down(): void
    {
        // Tidak boleh dipulihkan: teks yang telah digabungkan tidak lagi
        // mempunyai penanda yang memisahkan ayat bank daripada taipan pegawai.
    }

    /**
     * Ulangan AnalisisInventori::algoritmaLapuk() + ayat dinamiknya, tanpa
     * bergantung pada model supaya migrasi kekal sah walaupun model berubah.
     *
     * @param  array<string, mixed>  $data
     * @param  list<string>  $rujukan
     */
    private function ayatLapuk(array $data, array $rujukan): string
    {
        $nama = array_map(
            fn ($k) => explode('|', (string) $k)[1] ?? (string) $k,
            array_keys($data['algoritma'] ?? []),
        );

        foreach ($data['algoritma_lain'] ?? [] as $entri) {
            $nama[] = is_array($entri) ? (string) ($entri['nama'] ?? '') : (string) $entri;
        }

        $indeks = [];

        foreach ($nama as $satu) {
            $indeks[mb_strtolower(trim($satu))] = true;
        }

        $lapuk = array_values(array_filter($rujukan, fn ($r) => isset($indeks[mb_strtolower($r)])));

        return sprintf(
            'Hasil analisis mengenal pasti penggunaan algoritma atau fungsi kriptografi yang mempunyai kelemahan keselamatan yang diketahui atau tidak lagi disyorkan%s. Walaupun kelemahan tersebut tidak semestinya berkaitan secara langsung dengan ancaman pengkomputeran kuantum, penggunaannya boleh meningkatkan risiko keselamatan dan menjejaskan tahap perlindungan sistem. Oleh itu, algoritma berkenaan perlu diberi perhatian untuk digantikan dengan mekanisme yang lebih selamat sebagai sebahagian daripada usaha pemodenan kriptografi dan persediaan migrasi PQC.',
            $lapuk ? ', iaitu '.implode(', ', $lapuk) : '',
        );
    }
};
