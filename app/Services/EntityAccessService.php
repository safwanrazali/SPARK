<?php

namespace App\Services;

use App\Models\User;
use App\Support\SektorDirectory;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Collection;

/**
 * FASA 4 — sumber tunggal kebenaran bagi soalan:
 * "Bolehkah pengguna ini mengakses entiti ini?"
 *
 * Peraturan (spesifikasi bahagian 9 dan 26):
 * - Pentadbir Sistem            : semua entiti
 * - Pegawai Penyelaras Analisis : semua entiti
 * - Pegawai Analisis            : HANYA entiti yang ditugaskan secara aktif
 * - Peranan lain                : tiada akses sehingga kebenaran disahkan
 *
 * Penapisan dikuatkuasakan pada query (bukan sekadar UI) supaya akses melalui
 * URL langsung, permintaan JSON atau panggilan API tidak boleh memintasnya.
 */
class EntityAccessService
{
    /**
     * Adakah pengguna ini tertakluk kepada penapisan entiti?
     */
    public function isRestricted(User $user): bool
    {
        return $this->accessibleCodes($user) !== null;
    }

    /**
     * Senarai kod entiti yang boleh diakses.
     *
     * NULL bermaksud tiada penapisan (akses penuh), bukan "tiada akses".
     * Array kosong bermaksud tiada satu pun entiti boleh diakses.
     *
     * @return array<int, string>|null
     */
    public function accessibleCodes(User $user): ?array
    {
        return $user->getAccessibleEntities();
    }

    /**
     * Bolehkah pengguna mengakses entiti tertentu?
     */
    public function canAccess(User $user, ?string $agencyCode): bool
    {
        if ($agencyCode === null || $agencyCode === '') {
            return false;
        }

        $dibenarkan = $this->accessibleCodes($user);

        return $dibenarkan === null || in_array($agencyCode, $dibenarkan, true);
    }

    /**
     * Hentikan permintaan dengan 403 jika entiti tidak boleh diakses.
     */
    public function authorize(User $user, ?string $agencyCode): void
    {
        abort_unless(
            $this->canAccess($user, $agencyCode),
            403,
            'Anda tidak mempunyai akses kepada entiti ini.',
        );
    }

    /**
     * Hadkan sebarang query kepada entiti yang boleh diakses pengguna.
     *
     * @template TQuery of EloquentBuilder|QueryBuilder|Relation
     *
     * @param  TQuery  $query
     * @return TQuery
     */
    public function restrict($query, User $user, string $column = 'agency_code')
    {
        $dibenarkan = $this->accessibleCodes($user);

        if ($dibenarkan === null) {
            return $query;
        }

        // Array kosong menjadikan whereIn menolak semua baris — itulah tingkah
        // laku yang dikehendaki: tiada penugasan bermakna tiada entiti dilihat.
        $query->whereIn($query->qualifyColumn($column), $dibenarkan);

        return $query;
    }

    /**
     * Entiti daripada senarai induk yang boleh diakses pengguna.
     *
     * @return Collection<int, array<string, string>>
     */
    public function entitiFor(User $user): Collection
    {
        $dibenarkan = $this->accessibleCodes($user);

        if ($dibenarkan === null) {
            return SektorDirectory::semuaEntiti();
        }

        return SektorDirectory::semuaEntiti()
            ->filter(fn (array $entiti) => in_array($entiti['agency_code'], $dibenarkan, true))
            ->values();
    }

    /**
     * Entiti dalam satu sektor yang boleh diakses pengguna.
     *
     * @return Collection<int, array<string, string>>
     */
    public function entitiDalamSektorFor(User $user, string $sectorCode): Collection
    {
        return $this->entitiFor($user)
            ->where('sector_code', $sectorCode)
            ->values();
    }

    /**
     * Struktur config/sektor.php yang telah ditapis mengikut akses pengguna.
     * Sektor tanpa entiti yang boleh diakses tidak dipaparkan langsung.
     *
     * @return array<string, array<string, mixed>>
     */
    public function sektorFor(User $user): array
    {
        $dibenarkan = $this->accessibleCodes($user);

        if ($dibenarkan === null) {
            return SektorDirectory::sektor();
        }

        $ditapis = [];

        foreach (SektorDirectory::sektor() as $kod => $sektor) {
            $agensi = collect($sektor['agencies'] ?? [])
                ->filter(fn (array $a) => in_array($a['code'], $dibenarkan, true))
                ->values()
                ->all();

            if ($agensi !== []) {
                $ditapis[$kod] = ['name' => $sektor['name'], 'agencies' => $agensi];
            }
        }

        return $ditapis;
    }
}
