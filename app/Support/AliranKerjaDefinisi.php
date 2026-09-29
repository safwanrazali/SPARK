<?php

namespace App\Support;

use App\Models\User;

/**
 * Jadual takrifan peringkat aliran kerja — DATA SAHAJA.
 *
 * Dipisahkan daripada App\Support\AliranKerja semata-mata kerana saiz fail:
 * jadual ini ialah satu-satunya sumber kebenaran struktur peringkat, manakala
 * AliranKerja menyediakan pengaksesnya. TIADA nilai di sini yang berubah
 * semasa pemisahan — AliranKerja::semua() mengembalikan jadual ini seadanya,
 * jadi setiap pemanggil sedia ada terus membacanya melalui AliranKerja
 * seperti sebelum ini.
 *
 * Pemalar (PENERIMAAN_DATA, MEDAN_*, FASA_*) kekal pada AliranKerja dan
 * dirujuk dari sini; jangan menduakannya dalam kelas ini.
 */
final class AliranKerjaDefinisi
{
    /**
     * Takrifan penuh setiap peringkat, mengikut turutan aliran.
     *
     * `peranan`      siapa boleh melaksanakan peringkat ini
     * `gate`         gate kebenaran yang melindungi tindakannya
     * `medan`        lajur data yang ditangkap, dengan labelnya
     * `rujukan`      label No. Rujukan bagi peringkat ini, jika ada
     *
     * `syarat_selesai` medan yang mesti ADA sebelum peringkat ini Selesai
     * `syarat_lanjut`  medan yang mesti ADA sebelum peringkat SETERUSNYA boleh
     *                  dimulakan
     *
     * Kedua-duanya SENGAJA berasingan, dan senarai kedua boleh lebih pendek
     * daripada yang pertama. Pada peringkat 1.1, No. Rujukan diperlukan untuk
     * Selesai tetapi TIDAK untuk meneruskan kerja — kerana nombor itu
     * dimasukkan oleh PKD, dan kerja peringkat 1.2 tidak sepatutnya tertahan
     * menunggu pegawai lain.
     *
     * Peringkat dengan `syarat_selesai` kosong kekal ditandakan Selesai secara
     * eksplisit oleh pegawainya (butang "Selesai"), dan peringkat seterusnya
     * hanya terbuka setelah ia benar-benar Selesai.
     *
     * SIAPA memasukkan No. Rujukan tidak ditakrifkan di sini: setiap No.
     * Rujukan dimasukkan oleh PKD tanpa mengira siapa memiliki peringkatnya
     * (lihat AliranKerja::PERANAN_RUJUKAN).
     *
     * @return array<string, array<string, mixed>>
     */
    public static function semua(): array
    {
        return [
            AliranKerja::PENERIMAAN_DATA => [
                'utama' => 1,
                'label' => 'Penerimaan Data',
                'fasa' => AliranKerja::FASA_SEMASA,
                'peranan' => [User::ROLE_KETUA_BAHAGIAN, User::ROLE_COORDINATOR],
                'gate' => 'manage-stage-penerimaan',
                'medan' => [
                    AliranKerja::MEDAN_TARIKH_TERIMA => 'Tarikh Terima',
                    AliranKerja::MEDAN_STATUS_BORANG => 'Status Borang Penerimaan Data',
                ],
                'rujukan' => 'No. Rujukan Borang Penerimaan Data',

                // Selesai menuntut ketiga-tiganya; meneruskan ke peringkat 1.2
                // menuntut dua sahaja — No. Rujukan milik PKD dan tidak
                // sepatutnya menahan kerja peringkat berikutnya.
                'syarat_selesai' => [
                    AliranKerja::MEDAN_TARIKH_TERIMA,
                    AliranKerja::MEDAN_STATUS_BORANG,
                    AliranKerja::MEDAN_NO_RUJUKAN,
                ],
                'syarat_lanjut' => [
                    AliranKerja::MEDAN_TARIKH_TERIMA,
                    AliranKerja::MEDAN_STATUS_BORANG,
                ],
            ],

            AliranKerja::PENDAFTARAN_DATA => [
                'utama' => 1,
                'label' => 'Pendaftaran Data',
                'fasa' => AliranKerja::FASA_SEMASA,
                'peranan' => [User::ROLE_COORDINATOR],
                'gate' => 'manage-stage-pendaftaran',
                'medan' => [
                    AliranKerja::MEDAN_TARIKH_DAFTAR => 'Tarikh Daftar',
                    AliranKerja::MEDAN_STATUS_BORANG => 'Status Borang Pendaftaran Data',
                ],
                'rujukan' => 'No. Rujukan Borang Pendaftaran Data',

                // Tiada butang "Selesai": peringkat ini Selesai apabila
                // kedua-dua medannya direkod. No. Rujukan TIDAK disenaraikan —
                // ia milik PKD, dan menuntutnya akan menahan peringkat 1.3
                // menunggu pegawai lain.
                'syarat_selesai' => [
                    AliranKerja::MEDAN_TARIKH_DAFTAR,
                    AliranKerja::MEDAN_STATUS_BORANG,
                    AliranKerja::MEDAN_NO_RUJUKAN,
                ],
                'syarat_lanjut' => [
                    AliranKerja::MEDAN_TARIKH_DAFTAR,
                    AliranKerja::MEDAN_STATUS_BORANG,
                ],

                // Seorang Pegawai Analisis mesti ditugaskan — untuk Selesai
                // DAN untuk membuka peringkat seterusnya. Peringkat 1.3 ialah
                // kerja PA; tanpa pegawai yang ditugaskan, tiada sesiapa yang
                // boleh membukanya.
                'selesai_perlu_penugasan' => true,
                'lanjut_perlu_penugasan' => true,
            ],

            AliranKerja::SEMAKAN_AWAL_DATA => [
                'utama' => 1,
                'label' => 'Semakan Awal Data',
                'fasa' => AliranKerja::FASA_SEMASA,
                'peranan' => [User::ROLE_ANALYST],
                'gate' => 'advance-analysis-stage',
                'medan' => [
                    AliranKerja::MEDAN_TARIKH_SEMAKAN => 'Tarikh Semakan',
                    AliranKerja::MEDAN_STATUS_BORANG => 'Status Borang Semakan Awal Data',
                ],
                'rujukan' => 'No. Rujukan Borang Semakan Awal Data',

                'syarat_selesai' => [
                    AliranKerja::MEDAN_TARIKH_SEMAKAN,
                    AliranKerja::MEDAN_STATUS_BORANG,
                    AliranKerja::MEDAN_NO_RUJUKAN,
                ],

                // No. Rujukan milik PKD — ia tidak menahan peringkat 2.
                'syarat_lanjut' => [
                    AliranKerja::MEDAN_TARIKH_SEMAKAN,
                    AliranKerja::MEDAN_STATUS_BORANG,
                ],
            ],

            AliranKerja::PENYEDIAAN_DATA => [
                'utama' => 2,
                'label' => 'Penyediaan & Pengesahan Data',
                'fasa' => AliranKerja::FASA_SEMASA,
                'peranan' => [User::ROLE_ANALYST],
                'gate' => 'advance-analysis-stage',
                'medan' => [
                    AliranKerja::MEDAN_TARIKH_MULA => 'Tarikh Mula',
                    AliranKerja::MEDAN_TARIKH_TAMAT => 'Tarikh Tamat',
                    AliranKerja::MEDAN_STATUS_BORANG => 'Status Mastertable',
                    AliranKerja::MEDAN_NAMA_FAIL => 'Nama Fail',
                ],
                'rujukan' => null,

                // Tiada No. Rujukan pada peringkat ini, jadi syarat Selesai
                // dan syarat lanjut memang sama.
                'syarat_selesai' => [
                    AliranKerja::MEDAN_TARIKH_MULA,
                    AliranKerja::MEDAN_TARIKH_TAMAT,
                    AliranKerja::MEDAN_STATUS_BORANG,
                    AliranKerja::MEDAN_NAMA_FAIL,
                ],
            ],

            AliranKerja::ANALISIS_INVENTORI => [
                'utama' => 3,
                'label' => 'Analisis Inventori Kriptografi',
                'fasa' => AliranKerja::FASA_SEMASA,
                'peranan' => [User::ROLE_ANALYST],
                'gate' => 'advance-analysis-stage',
                'medan' => [
                    AliranKerja::MEDAN_TARIKH_MULA => 'Tarikh Mula',
                    AliranKerja::MEDAN_TARIKH_TAMAT => 'Tarikh Tamat',
                    AliranKerja::MEDAN_STATUS_BORANG => 'Status Laporan Inventori Kriptografi',
                ],
                'rujukan' => 'No. Rujukan Laporan',

                'syarat_selesai' => [
                    AliranKerja::MEDAN_TARIKH_MULA,
                    AliranKerja::MEDAN_TARIKH_TAMAT,
                    AliranKerja::MEDAN_STATUS_BORANG,
                    AliranKerja::MEDAN_NO_RUJUKAN,
                ],

                // Status Laporan Inventori Kriptografi hanya boleh ditetapkan
                // "Selesai" setelah Borang Input Analisis Inventori
                // Kriptografi dimuktamadkan.
                //
                // Ini prasyarat pada SATU NILAI, bukan pada medan itu: nilai
                // lain (Dalam Proses, Tidak Berkaitan, …) kekal boleh direkod
                // sepanjang kerja berjalan.
                'status_selesai_perlu_borang' => true,

                // Peringkat terakhir fasa semasa; syarat lanjut dikekalkan
                // selari dengan peringkat lain — No. Rujukan tidak menahan.
                'syarat_lanjut' => [
                    AliranKerja::MEDAN_TARIKH_MULA,
                    AliranKerja::MEDAN_TARIKH_TAMAT,
                    AliranKerja::MEDAN_STATUS_BORANG,
                ],
            ],

            AliranKerja::ANALISIS_RISIKO_PQC => [
                'utama' => 3,
                'label' => 'Analisis Risiko Migrasi PQC',
                'fasa' => AliranKerja::FASA_AKAN_DATANG,
                'peranan' => [],
                'gate' => null,
                'medan' => [],
                'rujukan' => null,
            ],

            AliranKerja::PENJANAAN_LAPORAN => [
                'utama' => 4,
                'label' => 'Penjanaan Laporan',
                'fasa' => AliranKerja::FASA_AKAN_DATANG,
                'peranan' => [],
                'gate' => null,
                'medan' => [],
                'rujukan' => null,
            ],

            AliranKerja::SEMAKAN_KELULUSAN => [
                'utama' => 5,
                'label' => 'Semakan, Kelulusan & Penyerahan Laporan',
                'fasa' => AliranKerja::FASA_AKAN_DATANG,
                'peranan' => [],
                'gate' => null,
                'medan' => [],
                'rujukan' => null,
            ],
        ];
    }
}
