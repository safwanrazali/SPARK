<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * FASA 8 — jejak audit.
 *
 * Satu-satunya laluan tulis ke activity_log. Perkhidmatan Fasa 2, 3, 6 dan
 * modul laporan semuanya menulis melalui kelas ini supaya:
 *
 * - tiada sistem log pendua diperkenalkan (satu jadual, satu bentuk rekod)
 * - penapisan data sensitif berlaku di satu tempat sahaja
 * - setiap rekod sentiasa mempunyai entiti, tindakan, nilai lama/baharu,
 *   pengguna, cap masa dan metadata
 *
 * Rekod audit bersifat append-only; ActivityLog menghalang sebarang
 * kemas kini atau pemadaman.
 */
class AuditTrailService
{
    /**
     * Kunci metadata yang TIDAK boleh sekali-kali dicatat.
     *
     * Jejak audit merekod perubahan, bukan kandungan. Dapatan analisis,
     * kata laluan dan token tidak mempunyai nilai audit tetapi meningkatkan
     * risiko jika disimpan berulang kali.
     */
    public const KUNCI_TERLARANG = [
        'password',
        'password_confirmation',
        'remember_token',
        '_token',
        'token',
        'api_key',
        'secret',
        'data',
        'section_data',
        'borang',
    ];

    /**
     * Panjang maksimum nilai teks yang dicatat.
     */
    public const HAD_TEKS = 500;

    /**
     * Rekodkan satu perubahan penting.
     *
     * @param  array<string, string|null>  $entiti  agency_code + agency_name
     * @param  array<string, mixed>  $metadata
     */
    public function rekod(
        array $entiti,
        string $action,
        ?string $lama,
        ?string $baharu,
        ?User $pengguna,
        array $metadata = [],
    ): ActivityLog {
        return ActivityLog::create([
            'agency_code' => $entiti['agency_code'],
            'agency_name' => $entiti['agency_name'] ?? null,
            'action' => $action,
            'old_value' => $this->potong($lama),
            'new_value' => $this->potong($baharu),
            'changed_by_user_id' => $pengguna?->id,
            'changed_at' => now(),
            'metadata' => $this->tapis($metadata),
        ]);
    }

    /**
     * Query jejak audit dengan penapis, sentiasa tertakluk kepada kawalan
     * akses entiti (Fasa 4).
     *
     * @param  array<string, mixed>  $penapis
     */
    public function query(User $pengguna, array $penapis = []): Builder
    {
        return ActivityLog::query()
            ->accessibleBy($pengguna)
            ->with('changedBy')
            ->when(
                $penapis['agency_code'] ?? null,
                fn (Builder $q, $kod) => $q->where('agency_code', $kod)
            )
            ->when(
                $penapis['action'] ?? null,
                fn (Builder $q, $action) => $q->where('action', $action)
            )
            ->when(
                $penapis['user_id'] ?? null,
                fn (Builder $q, $id) => $q->where('changed_by_user_id', $id)
            )
            ->when(
                $penapis['dari'] ?? null,
                fn (Builder $q, $dari) => $q->where('changed_at', '>=', $dari)
            )
            ->when(
                $penapis['hingga'] ?? null,
                fn (Builder $q, $hingga) => $q->where('changed_at', '<=', $hingga)
            )
            ->orderByDesc('changed_at')
            ->orderByDesc('id');
    }

    /**
     * @param  array<string, mixed>  $penapis
     */
    public function halaman(User $pengguna, array $penapis = [], int $setiapMuka = 25): LengthAwarePaginator
    {
        return $this->query($pengguna, $penapis)->paginate($setiapMuka)->withQueryString();
    }

    /**
     * Sejarah bagi satu entiti.
     *
     * @return Collection<int, ActivityLog>
     */
    public function untukEntiti(User $pengguna, string $agencyCode, int $had = 20): Collection
    {
        return $this->query($pengguna, ['agency_code' => $agencyCode])->limit($had)->get();
    }

    /**
     * Buang kunci sensitif dan potong nilai yang terlalu panjang.
     *
     * @param  array<string, mixed>  $metadata
     * @return array<string, mixed>
     */
    public function tapis(array $metadata): array
    {
        $bersih = [];

        foreach ($metadata as $kunci => $nilai) {
            if (in_array(strtolower((string) $kunci), self::KUNCI_TERLARANG, true)) {
                continue;
            }

            $bersih[$kunci] = match (true) {
                is_string($nilai) => $this->potong($nilai),
                is_array($nilai) => $this->tapis($nilai),
                default => $nilai,
            };
        }

        return $bersih;
    }

    private function potong(?string $nilai): ?string
    {
        if ($nilai === null) {
            return null;
        }

        return mb_strlen($nilai) > self::HAD_TEKS
            ? mb_substr($nilai, 0, self::HAD_TEKS).'…'
            : $nilai;
    }
}
