<?php

namespace Tests\Concerns;

use App\Models\User;
use App\Services\KemajuanAnalisisService;
use App\Support\AliranKerja;
use App\Support\SektorDirectory;

/**
 * Pembantu fikstur: gerakkan entiti melalui aliran kerja lima peringkat.
 *
 * Digunakan oleh ujian yang memerlukan entiti berada pada satu kedudukan
 * tertentu tetapi TIDAK menguji aliran itu sendiri. Ia memanggil servis
 * secara terus dan bukan melalui HTTP, jadi kebenaran peranan tidak disemak
 * di sini — ujian yang menguji kebenaran mesti menggunakan route sebenar.
 *
 * Peringkat BERDERIVASI (status diterbitkan daripada data, seperti 1.1)
 * tidak boleh "ditanda" Selesai: ia disiapkan dengan merekod medannya.
 * Pembantu di bawah mengendalikan kedua-dua jenis peringkat, jadi ujian
 * tidak perlu tahu yang mana satu.
 */
trait MelaluiAliranKerja
{
    /**
     * Data lengkap bagi satu peringkat — nilai fikstur yang memenuhi setiap
     * medannya.
     *
     * @return array<string, string>
     */
    protected function dataPeringkat(string $kunci): array
    {
        $data = [];

        foreach (array_keys(AliranKerja::medan($kunci)) as $lajur) {
            $data[$lajur] = in_array($lajur, AliranKerja::MEDAN_TARIKH, true)
                ? '2026-08-14'
                : ($lajur === AliranKerja::MEDAN_STATUS_BORANG ? 'Selesai' : 'fikstur');
        }

        return $data;
    }

    /**
     * Siapkan satu peringkat — mengikut caranya sendiri.
     *
     * Peringkat berderivasi disiapkan dengan merekod medannya (termasuk No.
     * Rujukan apabila ia salah satu syarat Selesai); peringkat lain
     * ditandakan Selesai secara terus.
     */
    protected function siapkanPeringkat(string $agencyCode, string $kunci, ?User $pengguna = null): void
    {
        $kemajuan = app(KemajuanAnalisisService::class);

        if (! AliranKerja::statusDiterbitkan($kunci)) {
            $kemajuan->tandakanSelesai($agencyCode, $kunci, $pengguna);

            return;
        }

        $kemajuan->simpanData($agencyCode, $kunci, $this->dataPeringkat($kunci), $pengguna);

        if (in_array(AliranKerja::MEDAN_NO_RUJUKAN, AliranKerja::syaratSelesai($kunci), true)) {
            $kemajuan->simpanRujukan($agencyCode, $kunci, 'FIKSTUR/'.$kunci, $pengguna);
        }
    }

    /**
     * Masukkan entiti ke dalam aliran kerja dengan peringkat 1.1 Selesai.
     */
    protected function masukkanKeAliran(string $agencyCode, ?User $pengguna = null): void
    {
        app(KemajuanAnalisisService::class)->lengkapkanPenerimaan(
            SektorDirectory::cariEntiti($agencyCode),
            $pengguna,
            $this->dataPeringkat(AliranKerja::PERTAMA)
                + [AliranKerja::MEDAN_NO_RUJUKAN => 'FIKSTUR/'.AliranKerja::PERTAMA],
        );
    }

    /**
     * Masukkan entiti ke dalam aliran kerja TANPA menyiapkan peringkat 1.1 —
     * baris peringkat wujud, tetapi 1.1 kekal Belum Mula.
     */
    protected function masukkanKeAliranTanpaSiap(string $agencyCode, ?User $pengguna = null): void
    {
        app(KemajuanAnalisisService::class)->lengkapkanPenerimaan(
            SektorDirectory::cariEntiti($agencyCode),
            $pengguna,
        );
    }

    /**
     * Siapkan setiap peringkat fasa semasa SEHINGGA $hingga (eksklusif).
     *
     * Contoh: lengkapkanHingga($kod, '3.1') meninggalkan entiti bersedia untuk
     * peringkat 3.1 dengan semua peringkat sebelumnya Selesai.
     */
    protected function lengkapkanHingga(string $agencyCode, string $hingga, ?User $pengguna = null): void
    {
        $this->masukkanKeAliranTanpaSiap($agencyCode, $pengguna);

        foreach (AliranKerja::semasa() as $kunci) {
            if ($kunci === $hingga) {
                return;
            }

            $this->siapkanPeringkat($agencyCode, $kunci, $pengguna);
        }
    }

    /**
     * Siapkan KESEMUA peringkat fasa semasa — entiti menjadi 'Siap'.
     */
    protected function lengkapkanFasaSemasa(string $agencyCode, ?User $pengguna = null): void
    {
        $this->lengkapkanHingga($agencyCode, 'tiada-peringkat-sedemikian', $pengguna);
    }
}
