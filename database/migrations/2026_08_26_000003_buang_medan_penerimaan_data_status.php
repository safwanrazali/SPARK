<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/*
 * Medan `data_status.*.penerimaan` telah dibuang daripada borang.
 *
 * Selepas `kebolehgunaan` diselaraskan kepada dua pilihan (Lengkap / Tidak
 * Lengkap) oleh migrasi 2026_08_26_000002, kedua-dua medan menanyakan soalan
 * yang sama, dan hanya `kebolehgunaan` dipaparkan dalam laporan.
 *
 * Kunci lama dibuang daripada JSON supaya tiada data mati tertinggal dalam
 * rekod yang tidak disimpan semula. Tiada kod membacanya lagi — pembuangan ini
 * kemasan, bukan keperluan fungsi.
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
        // Tidak boleh dipulihkan: nilai asal telah dibuang dan tiada sumber
        // lain menyimpannya. Borang tidak lagi mempunyai medan ini, jadi
        // ketiadaannya adalah keadaan yang betul selepas rollback.
    }

    private function buangKunci(string $jadual, string $lajur): void
    {
        DB::table($jadual)->orderBy('id')->chunkById(200, function ($baris) use ($jadual, $lajur) {
            foreach ($baris as $satu) {
                $data = json_decode((string) $satu->{$lajur}, true);

                if (! is_array($data) || ! isset($data['data_status']) || ! is_array($data['data_status'])) {
                    continue;
                }

                $berubah = false;

                foreach ($data['data_status'] as $kunci => $nilai) {
                    if (is_array($nilai) && array_key_exists('penerimaan', $nilai)) {
                        unset($data['data_status'][$kunci]['penerimaan']);
                        $berubah = true;
                    }
                }

                if ($berubah) {
                    DB::table($jadual)->where('id', $satu->id)->update([
                        $lajur => json_encode($data),
                    ]);
                }
            }
        });
    }
};
