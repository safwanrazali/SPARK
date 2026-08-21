<?php

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

/**
 * Peraturan penomboran halaman yang dikongsi oleh semua jadual senarai.
 *
 * Setiap jadual dalam sistem memaparkan paling banyak SETIAP_MUKA baris;
 * selebihnya dipindahkan ke halaman seterusnya. Nilai ini disimpan di satu
 * tempat supaya jadual baharu tidak terpesong daripada peraturan yang sama.
 *
 * Laporan rasmi (pratonton cetakan dan PDF) TIDAK menggunakan kelas ini —
 * dokumen mesti memaparkan keseluruhan kandungan dalam satu aliran.
 */
class Halaman
{
    /**
     * Bilangan baris maksimum bagi satu halaman jadual.
     */
    public const SETIAP_MUKA = 10;

    /**
     * Nomborkan koleksi yang dibina dalam ingatan (bukan query Eloquent).
     *
     * `$namaMuka` diperlukan apabila satu skrin memaparkan lebih daripada
     * satu jadual bernombor, supaya setiap jadual mempunyai parameter
     * halamannya sendiri.
     *
     * @template TValue
     *
     * @param  Collection<int, TValue>  $senarai
     * @return LengthAwarePaginator<int, TValue>
     */
    public static function daripada(
        Request $request,
        Collection $senarai,
        string $namaMuka = 'page',
    ): LengthAwarePaginator {
        $muka = LengthAwarePaginator::resolveCurrentPage($namaMuka);

        return new LengthAwarePaginator(
            $senarai->forPage($muka, self::SETIAP_MUKA)->values(),
            $senarai->count(),
            self::SETIAP_MUKA,
            $muka,
            ['path' => $request->url(), 'query' => $request->query(), 'pageName' => $namaMuka],
        );
    }
}
