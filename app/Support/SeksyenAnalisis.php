<?php

namespace App\Support;

/**
 * FASA 6 — pembahagian borang analisis kepada seksyen.
 *
 * Seksyen di sini sepadan dengan tajuk yang telah wujud dalam borang input
 * (1 · Maklumat Laporan sehingga 9 · Kesimpulan). Tiada seksyen baharu
 * direka; pembahagian ini hanya membolehkan draf disimpan dan disambung
 * semula seksyen demi seksyen.
 */
class SeksyenAnalisis
{
    /**
     * Kunci seksyen => label paparan + medan borang yang dimiliki.
     */
    public const SEKSYEN = [
        'maklumat' => [
            'label' => '1 · Maklumat Laporan',
            'medan' => ['tarikh_laporan', 'kod_rujukan', 'status_laporan'],
        ],
        'data_status' => [
            'label' => '2 · Status Data Diterima',
            'medan' => ['data_status', 'ringkasan_data'],
        ],
        'profil' => [
            'label' => '3 · Profil Sistem dan Aset',
            'medan' => ['profil'],
        ],
        'algoritma' => [
            'label' => '4 · Algoritma Kriptografi',
            'medan' => ['algoritma', 'algoritma_lain'],
        ],
        'protokol' => [
            'label' => '5 · Protokol Kriptografi',
            'medan' => ['protokol'],
        ],
        'pustaka' => [
            'label' => '6 · Pustaka dan Modul',
            'medan' => ['pustaka'],
        ],
        'vendor' => [
            'label' => '7 · Maklumat Vendor',
            'medan' => ['vendor'],
        ],
        'tindakan' => [
            'label' => '8 · Cadangan Tindakan Susulan',
            'medan' => ['tindakan', 'tindakan_lain'],
        ],
        'kesimpulan' => [
            'label' => '9 · Kesimpulan',
            'medan' => ['kesimpulan', 'kesimpulan_lain'],
        ],
    ];

    /**
     * @return array<int, string>
     */
    public static function kunci(): array
    {
        return array_keys(self::SEKSYEN);
    }

    public static function wujud(?string $seksyen): bool
    {
        return $seksyen !== null && array_key_exists($seksyen, self::SEKSYEN);
    }

    public static function label(string $seksyen): string
    {
        return self::SEKSYEN[$seksyen]['label'] ?? $seksyen;
    }

    /**
     * Pecahkan keadaan borang penuh kepada seksyen.
     *
     * @param  array<string, mixed>  $borang
     * @return array<string, array<string, mixed>>
     */
    public static function pecahkan(array $borang): array
    {
        $seksyen = [];

        foreach (self::SEKSYEN as $kunci => $takrif) {
            $nilai = [];

            foreach ($takrif['medan'] as $medan) {
                $nilai[$medan] = $borang[$medan] ?? null;
            }

            $seksyen[$kunci] = $nilai;
        }

        return $seksyen;
    }

    /**
     * Gabungkan semula seksyen kepada satu keadaan borang.
     *
     * @param  array<string, array<string, mixed>>  $seksyen
     * @return array<string, mixed>
     */
    public static function gabung(array $seksyen): array
    {
        $borang = [];

        foreach (self::SEKSYEN as $kunci => $takrif) {
            foreach ($takrif['medan'] as $medan) {
                if (array_key_exists($medan, $seksyen[$kunci] ?? [])) {
                    $borang[$medan] = $seksyen[$kunci][$medan];
                }
            }
        }

        return $borang;
    }

    /**
     * Adakah pegawai telah memasukkan sebarang maklumat dalam seksyen ini?
     *
     * Ini penunjuk kemajuan untuk UI sahaja — bukan peraturan pengesahan
     * laporan. Pengesahan sebenar kekal pada penyimpanan muktamad.
     *
     * @param  array<string, mixed>  $nilai
     */
    public static function adaKandungan(string $seksyen, array $nilai): bool
    {
        return match ($seksyen) {
            'maklumat' => self::adaTeks($nilai['tarikh_laporan'] ?? null)
                || self::adaTeks($nilai['kod_rujukan'] ?? null),

            'data_status' => self::adaTeks($nilai['ringkasan_data'] ?? null)
                || collect($nilai['data_status'] ?? [])->contains(fn ($j) => self::adaTeks($j['nota'] ?? null)),

            'profil' => collect($nilai['profil'] ?? [])->contains(
                fn ($p) => (int) ($p['jumlah'] ?? 0) > 0 || self::adaTeks($p['nota'] ?? null)
            ),

            'algoritma' => ! empty($nilai['algoritma']) || self::adaTeks($nilai['algoritma_lain'] ?? null),

            'protokol', 'pustaka', 'vendor' => ! empty($nilai[$seksyen]),

            'tindakan' => ! empty($nilai['tindakan']) || self::adaTeks($nilai['tindakan_lain'] ?? null),

            'kesimpulan' => ! empty($nilai['kesimpulan']) || self::adaTeks($nilai['kesimpulan_lain'] ?? null),

            default => false,
        };
    }

    private static function adaTeks(mixed $nilai): bool
    {
        return is_scalar($nilai) && trim((string) $nilai) !== '';
    }
}
