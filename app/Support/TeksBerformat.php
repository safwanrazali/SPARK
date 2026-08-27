<?php

namespace App\Support;

/**
 * Menukar teks bebas daripada borang kepada blok perenggan dan senarai.
 *
 * Medan "Ulasan" ditaip sendiri oleh pegawai dalam satu textarea, tetapi
 * templat laporan rasmi memaparkannya sebagai perenggan berjustifikasi DAN
 * senarai bernombor. Kelas ini menterjemah konvensyen menaip biasa kepada
 * struktur tersebut, supaya pegawai tidak perlu belajar sebarang markup:
 *
 *   - Baris kosong memisahkan blok (perenggan baharu).
 *   - Blok yang SETIAP barisnya bermula dengan penanda senarai
 *     (`1.`, `2)`, `-`, `*`, `•`) menjadi senarai; penanda dibuang supaya
 *     nombor tidak berganda dengan nombor yang dijana oleh CSS.
 *   - Blok lain menjadi satu perenggan; baris tunggal di dalamnya
 *     digabungkan dengan ruang kerana teks laporan dijustifikasikan dan
 *     pemisah baris manual akan merosakkan aliran.
 *
 * Sengaja TIDAK menyokong markup lain (tebal, italik, pautan): laporan ini
 * dokumen rasmi dan kandungannya dipaparkan sebagai teks terlepas (escaped).
 */
class TeksBerformat
{
    /** Penanda senarai yang diterima pada permulaan baris. */
    private const PENANDA = '/^(?<penanda>\d+[.)]|[-*•])\s+(?<isi>.*)$/u';

    /**
     * @return list<array{jenis: string, bernombor: bool, isi: string|list<string>}>
     */
    public static function blok(?string $teks): array
    {
        $teks = trim(str_replace(["\r\n", "\r"], "\n", (string) $teks));

        if ($teks === '') {
            return [];
        }

        $blok = [];

        foreach (preg_split('/\n\s*\n/u', $teks) as $bahagian) {
            $baris = array_values(array_filter(
                array_map('trim', explode("\n", $bahagian)),
                fn ($b) => $b !== '',
            ));

            if ($baris === []) {
                continue;
            }

            $senarai = self::senarai($baris);

            $blok[] = $senarai ?? [
                'jenis' => 'perenggan',
                'bernombor' => false,
                'isi' => implode(' ', $baris),
            ];
        }

        return $blok;
    }

    /**
     * Kembalikan blok senarai jika SETIAP baris mempunyai penanda; jika
     * sebahagian sahaja, blok itu dianggap perenggan biasa supaya teks yang
     * kebetulan bermula dengan nombor (cth. "2026 merupakan...") tidak
     * bertukar menjadi senarai secara tidak sengaja.
     *
     * @param  list<string>  $baris
     * @return array{jenis: string, bernombor: bool, isi: list<string>}|null
     */
    private static function senarai(array $baris): ?array
    {
        $isi = [];
        $bernombor = null;

        foreach ($baris as $satu) {
            if (! preg_match(self::PENANDA, $satu, $padanan)) {
                return null;
            }

            $bernombor ??= (bool) preg_match('/^\d/', $padanan['penanda']);

            $bersih = trim($padanan['isi']);

            if ($bersih === '') {
                return null;
            }

            $isi[] = $bersih;
        }

        return [
            'jenis' => 'senarai',
            'bernombor' => (bool) $bernombor,
            'isi' => $isi,
        ];
    }
}
