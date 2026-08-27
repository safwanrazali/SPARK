<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/*
 * Templat rasmi memaparkan SATU status bagi setiap Jadual 0-2, iaitu
 * "Lengkap / Tidak Lengkap" di bawah lajur STATUS KEBOLEHGUNAAN.
 *
 * Medan `kebolehgunaan` sebelum ini menyimpan empat nilai. Nilai tersebut
 * dipetakan kepada dua: apa-apa yang masih boleh digunakan menjadi 'Lengkap',
 * manakala yang memerlukan pengesahan atau tidak boleh digunakan menjadi
 * 'Tidak Lengkap'.
 *
 * Nilai ini disimpan DALAM JSON, bukan lajur tersendiri, jadi kedua-dua tempat
 * perlu ditulis semula:
 *   - analisis_inventori.data          (dapatan muktamad)
 *   - analisis_draft_history.section_data (draf yang belum dimuktamadkan)
 *
 * Tanpa migrasi ini, rekod lama akan memaparkan pilihan yang tidak lagi wujud
 * dalam borang; <select> akan jatuh kepada pilihan pertama dan menukar data
 * pegawai secara senyap pada simpanan berikutnya.
 */
return new class extends Migration
{
    private const PETAAN = [
        'Boleh Digunakan' => 'Lengkap',
        'Boleh Digunakan dengan Catatan' => 'Lengkap',
        'Memerlukan Pengesahan' => 'Tidak Lengkap',
        'Tidak Boleh Digunakan' => 'Tidak Lengkap',
    ];

    public function up(): void
    {
        $this->petakan(self::PETAAN);
    }

    public function down(): void
    {
        // LOSSY: empat nilai asal telah bergabung menjadi dua, jadi hanya
        // pemetaan wakil yang dapat dipulihkan.
        $this->petakan([
            'Lengkap' => 'Boleh Digunakan',
            'Tidak Lengkap' => 'Tidak Boleh Digunakan',
        ]);
    }

    /**
     * @param  array<string, string>  $petaan
     */
    private function petakan(array $petaan): void
    {
        $this->tulisSemula('analisis_inventori', 'data', $petaan);

        // Draf menyimpan keadaan satu seksyen sahaja, jadi `data_status`
        // hadir hanya pada draf seksyen berkenaan; yang lain dilangkau.
        $this->tulisSemula('analisis_draft_history', 'section_data', $petaan);
    }

    /**
     * @param  array<string, string>  $petaan
     */
    private function tulisSemula(string $jadual, string $lajur, array $petaan): void
    {
        DB::table($jadual)->orderBy('id')->chunkById(200, function ($baris) use ($jadual, $lajur, $petaan) {
            foreach ($baris as $satu) {
                $data = json_decode((string) $satu->{$lajur}, true);

                if (! is_array($data)) {
                    continue;
                }

                if (! isset($data['data_status']) || ! is_array($data['data_status'])) {
                    continue;
                }

                $berubah = false;

                foreach ($data['data_status'] as $kunci => $nilai) {
                    $lama = $nilai['kebolehgunaan'] ?? null;

                    if (is_string($lama) && isset($petaan[$lama])) {
                        $data['data_status'][$kunci]['kebolehgunaan'] = $petaan[$lama];
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
