<?php

namespace App\Support;

/**
 * Paparan peratusan papan pemuka.
 *
 * Peratusan disimpan sebagai integer bulat, dan pembundaran boleh menipu pada
 * kedua-dua hujung skala:
 *
 *   1 daripada 252   = 0.4%  → "0%"   terbaca sebagai "tiada satu pun"
 *   251 daripada 252 = 99.6% → "100%" terbaca sebagai "kesemuanya"
 *
 * Kedua-duanya dipaparkan sebagai "<1" dan ">99" supaya hujung skala kekal
 * jujur tanpa menambah tempat perpuluhan pada setiap kad dan legenda.
 */
class Peratus
{
    /**
     * @param  int|null  $jumlah  penyebut, jika diketahui — diperlukan untuk
     *                            membezakan 100% yang sebenar daripada 100%
     *                            hasil pembundaran
     */
    public static function paparan(int $bilangan, int $peratus, ?int $jumlah = null): string
    {
        if ($bilangan > 0 && $peratus === 0) {
            return '<1';
        }

        if ($jumlah !== null && $peratus === 100 && $bilangan < $jumlah) {
            return '>99';
        }

        return (string) $peratus;
    }
}
