<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/*
 * Medan `ringkasan_data` telah dibuang daripada borang.
 *
 * Templat rasmi meletakkan HANYA nota "Catatan:" di bawah jadual seksyen
 * Status Penerimaan dan Kebolehgunaan Data, jadi ayat ringkasan piawai tidak
 * lagi dipaparkan di mana-mana dalam laporan. Medan tersebut menjadi input
 * wajib yang tidak menghasilkan apa-apa, lalu dibuang sepenuhnya bersama
 * peraturan pengesahan dan bank ayatnya dalam config/kriptografi.php.
 *
 * Kunci lama dibuang daripada JSON supaya tiada data mati tertinggal dalam
 * rekod yang tidak disimpan semula. Tiada kod membacanya lagi.
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->buangKunci('analisis_inventori', 'data');
        $this->buangKunci('analisis_draft_history', 'section_data');
    }

    public function down(): void
    {
        // Tidak boleh dipulihkan: nilai asal telah dibuang dan borang tidak
        // lagi mempunyai medan ini, jadi ketiadaannya betul selepas rollback.
    }

    private function buangKunci(string $jadual, string $lajur): void
    {
        DB::table($jadual)->orderBy('id')->chunkById(200, function ($baris) use ($jadual, $lajur) {
            foreach ($baris as $satu) {
                $data = json_decode((string) $satu->{$lajur}, true);

                if (! is_array($data) || ! array_key_exists('ringkasan_data', $data)) {
                    continue;
                }

                unset($data['ringkasan_data']);

                DB::table($jadual)->where('id', $satu->id)->update([
                    $lajur => json_encode($data),
                ]);
            }
        });
    }
};
