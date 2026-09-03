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

    /**
     * Peratusan kad ringkasan entiti — satu tempat perpuluhan, dan hanya
     * apabila ia bermakna.
     *
     * Kad corong entiti diukur terhadap keseluruhan 252 entiti, di mana
     * pembundaran integer memusnahkan hujung bawah skala:
     *
     *     2 daripada 252 = 0.79%  -> "1"    memberi lebih daripada yang ada
     *
     * Satu tempat perpuluhan mengekalkan angka itu jujur tanpa menjadikan
     * setiap nilai bulat kelihatan palsu tepat: ".0" digugurkan, jadi 40.0
     * kekal "40" dan 100.0 kekal "100".
     */
    public static function kad(float $peratus): string
    {
        $satuPerpuluhan = round($peratus, 1);

        return rtrim(rtrim(number_format($satuPerpuluhan, 1, '.', ''), '0'), '.') ?: '0';
    }
}
