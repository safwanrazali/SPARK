<?php

namespace App\Models\Concerns;

use App\Models\User;
use App\Services\EntityAccessService;
use Illuminate\Database\Eloquent\Builder;

/**
 * FASA 4 — penapisan entiti pada peringkat query.
 *
 * Model yang menggunakan trait ini mendapat scope `accessibleBy()` supaya
 * setiap senarai hanya memaparkan entiti yang dibenarkan untuk pengguna.
 * Penapisan pada query ialah lapisan keselamatan sebenar; menyembunyikan
 * butang pada UI sahaja tidak mencukupi.
 */
trait FiltersByEntityAccess
{
    /**
     * Hadkan query kepada entiti yang boleh diakses oleh pengguna.
     * Tanpa pengguna yang sah, tiada baris dikembalikan.
     */
    public function scopeAccessibleBy(Builder $query, ?User $user = null): Builder
    {
        $user ??= auth()->user();

        if (! $user instanceof User) {
            return $query->whereRaw('1 = 0');
        }

        return app(EntityAccessService::class)->restrict($query, $user, $this->entityAccessColumn());
    }

    /**
     * Lajur yang memegang kod entiti bagi model ini.
     */
    protected function entityAccessColumn(): string
    {
        return 'agency_code';
    }
}
