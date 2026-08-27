<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/*
 * Kumpulan "Legasi / Luar Senarai AKSA MySEAL" telah dibuang daripada katalog;
 * katalog kini mengandungi 12 kategori AKSA MySEAL (Approved) sahaja.
 *
 * Algoritma yang sebelum ini ditanda di bawah kumpulan tersebut tidak lagi
 * mempunyai kategori yang sepadan. Jika dibiarkan, ia akan menjadi rekod yatim:
 * hilang daripada jadual laporan dan tidak lagi dikesan sebagai lapuk atau
 * berisiko kuantum.
 *
 * Nama algoritma dipindahkan ke medan "Lain-lain" (`algoritma_lain`), iaitu
 * tempat rasminya sekarang. AnalisisInventori::algoritmaLapuk() dan
 * algoritmaKuantum() mengimbas medan itu, jadi penandaan kekal berfungsi.
 */
return new class extends Migration
{
    private const AWALAN = 'Legasi / Luar Senarai AKSA MySEAL|';

    public function up(): void
    {
        foreach ([['analisis_inventori', 'data'], ['analisis_draft_history', 'section_data']] as [$jadual, $lajur]) {
            DB::table($jadual)->orderBy('id')->chunkById(200, function ($baris) use ($jadual, $lajur) {
                foreach ($baris as $satu) {
                    $data = json_decode((string) $satu->{$lajur}, true);

                    if (! is_array($data) || empty($data['algoritma']) || ! is_array($data['algoritma'])) {
                        continue;
                    }

                    // Nilai lama mungkin rentetan tunggal atau senarai.
                    $lain = $data['algoritma_lain'] ?? [];
                    $lain = is_array($lain) ? $lain : [$lain];
                    $lain = array_values(array_filter(array_map('trim', $lain), fn ($n) => $n !== ''));

                    $kekal = [];
                    $berubah = false;

                    foreach ($data['algoritma'] as $kunci => $nilai) {
                        if (! str_starts_with($kunci, self::AWALAN)) {
                            $kekal[$kunci] = $nilai;

                            continue;
                        }

                        $nama = substr($kunci, strlen(self::AWALAN));

                        if ($nama !== '' && ! in_array($nama, $lain, true)) {
                            $lain[] = $nama;
                        }

                        $berubah = true;
                    }

                    if (! $berubah) {
                        continue;
                    }

                    $data['algoritma'] = $kekal;
                    $data['algoritma_lain'] = $lain;

                    DB::table($jadual)->where('id', $satu->id)->update([$lajur => json_encode($data)]);
                }
            });
        }
    }

    public function down(): void
    {
        // Tidak boleh dipulihkan: setelah digabungkan ke dalam "Lain-lain",
        // tiada penanda yang membezakan entri asal daripada entri yang ditaip
        // sendiri oleh pegawai.
    }
};
