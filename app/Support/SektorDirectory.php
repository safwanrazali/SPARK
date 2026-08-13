<?php

namespace App\Support;

use Illuminate\Support\Collection;

/**
 * Rujukan senarai induk sektor dan entiti daripada config/sektor.php.
 *
 * Struktur sektor/entiti sedia ada tidak diubah — kelas ini hanya
 * memudahkan carian entiti (kod → sektor + nama) untuk modul workflow.
 */
class SektorDirectory
{
    /**
     * Cache rata: agency_code => ['sector_code', 'sector_name', 'agency_code', 'agency_name'].
     *
     * @var array<string, array<string, string>>|null
     */
    protected static ?array $indeks = null;

    /**
     * Semua sektor seperti dalam config.
     *
     * @return array<string, array<string, mixed>>
     */
    public static function sektor(): array
    {
        return config('sektor', []);
    }

    /**
     * Adakah kod sektor wujud?
     */
    public static function sektorWujud(?string $sectorCode): bool
    {
        return $sectorCode !== null && array_key_exists($sectorCode, self::sektor());
    }

    /**
     * Senarai rata semua entiti dalam senarai induk.
     *
     * @return Collection<int, array<string, string>>
     */
    public static function semuaEntiti(): Collection
    {
        return collect(self::indeks())->values();
    }

    /**
     * Semua entiti dalam satu sektor.
     *
     * @return Collection<int, array<string, string>>
     */
    public static function entitiDalamSektor(string $sectorCode): Collection
    {
        return self::semuaEntiti()
            ->where('sector_code', $sectorCode)
            ->values();
    }

    /**
     * Cari satu entiti berdasarkan kod agensi.
     *
     * @return array<string, string>|null
     */
    public static function cariEntiti(?string $agencyCode): ?array
    {
        if ($agencyCode === null) {
            return null;
        }

        return self::indeks()[$agencyCode] ?? null;
    }

    /**
     * Kosongkan cache — diperlukan dalam ujian yang mengubah config.
     */
    public static function flush(): void
    {
        self::$indeks = null;
    }

    /**
     * @return array<string, array<string, string>>
     */
    protected static function indeks(): array
    {
        if (self::$indeks !== null) {
            return self::$indeks;
        }

        $indeks = [];

        foreach (self::sektor() as $sectorCode => $sektor) {
            foreach ($sektor['agencies'] ?? [] as $agensi) {
                $indeks[$agensi['code']] = [
                    'sector_code' => (string) $sectorCode,
                    'sector_name' => $sektor['name'],
                    'agency_code' => $agensi['code'],
                    'agency_name' => $agensi['name'],
                ];
            }
        }

        return self::$indeks = $indeks;
    }
}
