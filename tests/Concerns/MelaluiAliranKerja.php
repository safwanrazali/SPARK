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
 */
trait MelaluiAliranKerja
{
    /**
     * Masukkan entiti ke dalam aliran kerja (peringkat 1.1 Selesai).
     */
    protected function masukkanKeAliran(string $agencyCode, ?User $pengguna = null): void
    {
        app(KemajuanAnalisisService::class)->lengkapkanPenerimaan(
            SektorDirectory::cariEntiti($agencyCode),
            $pengguna,
        );
    }

    /**
     * Tandakan setiap peringkat fasa semasa Selesai SEHINGGA $hingga
     * (eksklusif). Contoh: lengkapkanHingga($kod, '3.1') meninggalkan entiti
     * bersedia untuk peringkat 3.1 dengan semua peringkat sebelumnya Selesai.
     *
     * Turutan diambil daripada AliranKerja, jadi ujian tidak perlu tahu
     * berapa peringkat wujud sebelum satu-satu kedudukan.
     */
    protected function lengkapkanHingga(string $agencyCode, string $hingga, ?User $pengguna = null): void
    {
        $this->masukkanKeAliran($agencyCode, $pengguna);

        $kemajuan = app(KemajuanAnalisisService::class);

        foreach (AliranKerja::semasa() as $kunci) {
            if ($kunci === $hingga) {
                return;
            }

            $kemajuan->tandakanSelesai($agencyCode, $kunci, $pengguna);
        }
    }

    /**
     * Tandakan KESEMUA peringkat fasa semasa Selesai — entiti menjadi 'Siap'.
     */
    protected function lengkapkanFasaSemasa(string $agencyCode, ?User $pengguna = null): void
    {
        $this->lengkapkanHingga($agencyCode, 'tiada-peringkat-sedemikian', $pengguna);
    }
}
