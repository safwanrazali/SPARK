<?php

namespace App\Support;

use App\Models\WorkflowStageStatus;

/**
 * Peraturan "medan mana yang masih tiada" bagi satu baris peringkat.
 *
 * Dipisahkan daripada App\Services\KemajuanAnalisisService kerana kesemuanya
 * TULEN: satu baris peringkat masuk, senarai nama lajur keluar. Tiada query,
 * tiada audit, tiada transaksi — jadi ia boleh dibaca dan diuji tanpa
 * menyentuh keadaan aliran kerja.
 *
 * Peraturan di sini TIDAK berubah semasa pemisahan. KemajuanAnalisisService
 * kekal sebagai pintu masuk awam (medanBelumLengkap(), rujukanTersedia() dan
 * seterusnya masih kaedahnya) dan hanya meneruskan panggilan ke sini, supaya
 * setiap pemanggil sedia ada — controller, paparan dan ujian — tidak berubah.
 */
final class SyaratPeringkat
{
    /**
     * Medan dianggap "tiada" apabila null atau rentetan kosong.
     */
    public static function kosong(mixed $nilai): bool
    {
        return $nilai === null || (is_string($nilai) && trim($nilai) === '');
    }

    /**
     * Medan `syarat_selesai` yang MASIH TIADA pada peringkat ini.
     *
     * @return array<int, string>
     */
    public static function medanBelumLengkap(?WorkflowStageStatus $rekod, string $stage): array
    {
        return self::tapis(AliranKerja::syaratSelesai($stage), $rekod);
    }

    /**
     * Medan `syarat_lanjut` peringkat ini yang MASIH TIADA.
     *
     * "Syarat lanjut" ialah medan yang mesti ADA sebelum peringkat SETERUSNYA
     * boleh dimulakan — lebih longgar daripada `syarat_selesai`, kerana No.
     * Rujukan milik PPR tidak sepatutnya menahan kerja peringkat berikutnya.
     *
     * Papan pemuka bertanya soalan yang SAMA secara pukal: "entiti mana yang
     * telah merekodkan medan peringkat ini?". Menyalin peraturannya ke dalam
     * DashboardStatistikService akan mewujudkan takrifan kedua yang boleh
     * terpesong daripada yang menguatkuasakan aliran kerja.
     *
     * @return array<int, string>
     */
    public static function medanLanjutBelumDirekod(?WorkflowStageStatus $rekod, string $stage): array
    {
        return self::tapis(AliranKerja::syaratLanjut($stage), $rekod);
    }

    /**
     * Medan peringkat yang MASIH TIADA sebelum No. Rujukannya boleh direkod.
     *
     * Peraturannya seragam merentas peringkat: No. Rujukan sesuatu borang
     * hanya bermakna setelah borang itu sendiri direkod. PPR merekod nombor
     * rujukan borang FIZIKAL — dan borang itu belum wujud sehingga pegawai
     * peringkat berkenaan mengisi medannya.
     *
     * Medan yang dikira ialah medan tangkapan peringkat itu; No. Rujukan
     * sendiri tidak termasuk (ia bukan syarat kepada dirinya).
     *
     * @return array<int, string>
     */
    public static function medanSebelumRujukan(?WorkflowStageStatus $rekod, string $stage): array
    {
        if (AliranKerja::labelRujukan($stage) === null) {
            return [];
        }

        $medan = array_keys(AliranKerja::medan($stage));

        if ($rekod === null) {
            return $medan;
        }

        return array_values(array_filter(
            $medan,
            fn (string $lajur) => self::kosong($rekod->{$lajur}),
        ));
    }

    /**
     * Bolehkah No. Rujukan peringkat ini direkod sekarang?
     */
    public static function rujukanTersedia(?WorkflowStageStatus $rekod, string $stage): bool
    {
        return AliranKerja::labelRujukan($stage) !== null
            && self::medanSebelumRujukan($rekod, $stage) === [];
    }

    /**
     * Baki senarai syarat yang masih belum berisi pada baris peringkat.
     *
     * Baris yang TIADA langsung bermakna tiada satu pun syarat dipenuhi, jadi
     * senarai dikembalikan seadanya — bukan senarai kosong.
     *
     * @param  array<int, string>  $syarat
     * @return array<int, string>
     */
    private static function tapis(array $syarat, ?WorkflowStageStatus $rekod): array
    {
        if ($syarat === [] || $rekod === null) {
            return $syarat;
        }

        return array_values(array_filter(
            $syarat,
            fn (string $lajur) => self::kosong($rekod->{$lajur}),
        ));
    }
}
