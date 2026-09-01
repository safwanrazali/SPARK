<?php

namespace App\Support;

use App\Models\User;

/**
 * Takrifan rasmi aliran kerja — LIMA peringkat utama, sebahagiannya
 * mengandungi sub-peringkat.
 *
 *   1. Penerimaan & Semakan Awal Data
 *      1.1 Penerimaan Data                  KB / PPA
 *      1.2 Pendaftaran Data                 PPA
 *      1.3 Semakan Awal Data                PA
 *   2. Penyediaan & Pengesahan Data         PA
 *   3. Analisis Data
 *      3.1 Analisis Inventori Kriptografi   PA
 *      3.2 Analisis Risiko Migrasi PQC      [fasa akan datang]
 *   4. Penjanaan Laporan                    [fasa akan datang]
 *   5. Semakan, Kelulusan & Penyerahan      [fasa akan datang]
 *
 * INILAH satu-satunya tempat struktur peringkat ditakrifkan. Model, servis,
 * controller, paparan dan migrasi semuanya membacanya dari sini — tiada
 * nombor peringkat bertaburan dalam kod, kerana struktur bersarang tidak
 * boleh diwakili oleh satu integer sahaja.
 *
 * KUNCI PERINGKAT ialah string ('1.1', '2', '3.1'), bukan integer. Itulah
 * sebabnya `workflow_stage_status.stage` menyimpan string: sub-peringkat
 * ialah sebahagian daripada identiti peringkat, bukan hiasan paparan.
 *
 * FASA: peringkat 3.2, 4 dan 5 WUJUD dalam struktur tetapi belum dibina.
 * Barisnya tetap dicipta supaya modulnya boleh ditambah kemudian tanpa
 * migrasi struktur semula; ia hanya tidak menerima sebarang tindakan dan
 * tidak dikira dalam kemajuan fasa semasa (lihat self::semasa()).
 */
final class AliranKerja
{
    /**
     * Peringkat yang berfungsi sepenuhnya dalam fasa semasa.
     */
    public const FASA_SEMASA = 'semasa';

    /**
     * Peringkat yang telah ditakrifkan tetapi belum dibina. Tiada tindakan,
     * tiada peranan, tiada logik perniagaan — hanya tempat yang dikhaskan.
     */
    public const FASA_AKAN_DATANG = 'akan_datang';

    /**
     * Lima peringkat utama. Bilangan ini ialah takrifan "berapa peringkat
     * sistem ini ada" — sub-peringkat tidak menambahnya.
     *
     * @var array<int, string>
     */
    public const UTAMA = [
        1 => 'Penerimaan & Semakan Awal Data',
        2 => 'Penyediaan & Pengesahan Data',
        3 => 'Analisis Data',
        4 => 'Penjanaan Laporan',
        5 => 'Semakan, Kelulusan & Penyerahan Laporan',
    ];

    public const UTAMA_PERTAMA = 1;

    public const UTAMA_TERAKHIR = 5;

    /**
     * Kunci peringkat yang dirujuk secara langsung oleh logik aliran kerja.
     * Dinamakan supaya tiada string mentah bertaburan dalam kod.
     */
    public const PENERIMAAN_DATA = '1.1';

    public const PENDAFTARAN_DATA = '1.2';

    public const SEMAKAN_AWAL_DATA = '1.3';

    public const PENYEDIAAN_DATA = '2';

    public const ANALISIS_INVENTORI = '3.1';

    public const ANALISIS_RISIKO_PQC = '3.2';

    public const PENJANAAN_LAPORAN = '4';

    public const SEMAKAN_KELULUSAN = '5';

    public const PERTAMA = self::PENERIMAAN_DATA;

    /**
     * Peringkat terakhir yang berfungsi dalam fasa semasa. Selepas ini,
     * aliran kerja berhenti sehingga modul fasa berikutnya dibina.
     */
    public const TERAKHIR_SEMASA = self::ANALISIS_INVENTORI;

    /**
     * Lajur `workflow_stage_status` yang menyimpan data tangkapan setiap
     * peringkat. Disenaraikan supaya borang dan pengesahan tidak boleh
     * menulis lajur di luar senarai ini.
     */
    public const MEDAN_TARIKH_TERIMA = 'tarikh_terima';

    public const MEDAN_TARIKH_DAFTAR = 'tarikh_daftar';

    public const MEDAN_TARIKH_SEMAKAN = 'tarikh_semakan';

    public const MEDAN_TARIKH_MULA = 'tarikh_mula';

    public const MEDAN_TARIKH_TAMAT = 'tarikh_tamat';

    public const MEDAN_STATUS_BORANG = 'status_borang';

    public const MEDAN_NAMA_FAIL = 'nama_fail';

    public const MEDAN_NO_RUJUKAN = 'no_rujukan';

    /**
     * Peranan yang memasukkan SETIAP No. Rujukan dalam sistem — Pegawai
     * Penyelaras Rekod.
     *
     * Ini BUKAN peranan peringkat. Peringkat 1.1, 1.2, 1.3 dan 3.1 dimiliki
     * oleh KB, PPA dan PA, tetapi tiada seorang pun daripada mereka
     * memasukkan nombor rujukannya sendiri: merekod No. Rujukan ialah
     * keseluruhan tanggungjawab PPR, dan satu-satunya kuasa menulis yang
     * dimilikinya.
     *
     * Kerana itu ia ditakrifkan sekali di sini dan bukan sebagai medan pada
     * setiap peringkat — peraturannya global, bukan per peringkat.
     */
    public const PERANAN_RUJUKAN = User::ROLE_PENYELARAS_REKOD;

    /**
     * Gate yang melindungi kemasukan No. Rujukan. Sengaja BERASINGAN daripada
     * gate peringkat: menyatukannya akan memberi pemilik peringkat kuasa
     * menetapkan nombor rujukan yang bukan tanggungjawabnya.
     */
    public const GATE_RUJUKAN = 'record-stage-reference';

    /**
     * Perbendaharaan "Status Borang" — SATU senarai, dikongsi oleh setiap
     * peringkat yang menangkapnya:
     *
     *   Status Borang Penerimaan Data          (1.1)
     *   Status Borang Pendaftaran Data         (1.2)
     *   Status Borang Semakan Awal Data        (1.3)
     *   Status Mastertable                     (2)
     *   Status Laporan Inventori Kriptografi   (3.1)
     *
     * Label medan berbeza mengikut peringkat, tetapi nilainya sama — jadi ia
     * ditakrifkan sekali di sini dan bukan diulang pada setiap peringkat.
     *
     * PERBENDAHARAAN INI BERASINGAN daripada WorkflowStageStatus::STATUSES.
     * Yang itu ialah kedudukan PERINGKAT dalam aliran kerja (Belum Mula /
     * Dalam Proses / Selesai); yang ini ialah status BORANG yang direkodkan
     * pada peringkat itu. Kedua-duanya berkongsi beberapa perkataan yang sama
     * tetapi menjawab soalan yang berlainan, dan menggabungkannya akan
     * menghilangkan keadaan seperti "Tidak Berkaitan" yang hanya bermakna
     * bagi borang.
     *
     * @var array<int, string>
     */
    public const STATUS_BORANG = [
        'Belum Mula',
        'Dalam Proses',
        'Dalam Semakan',
        'Selesai',
        'Tidak Boleh Diteruskan',
        'Tidak Berkaitan',
        'Telah Diserah',
    ];

    /**
     * Medan bertarikh — dipisahkan kerana pengesahannya berbeza.
     *
     * @var array<int, string>
     */
    public const MEDAN_TARIKH = [
        self::MEDAN_TARIKH_TERIMA,
        self::MEDAN_TARIKH_DAFTAR,
        self::MEDAN_TARIKH_SEMAKAN,
        self::MEDAN_TARIKH_MULA,
        self::MEDAN_TARIKH_TAMAT,
    ];

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
     * dimasukkan oleh PPR, dan kerja peringkat 1.2 tidak sepatutnya tertahan
     * menunggu pegawai lain.
     *
     * Peringkat dengan `syarat_selesai` kosong kekal ditandakan Selesai secara
     * eksplisit oleh pegawainya (butang "Selesai"), dan peringkat seterusnya
     * hanya terbuka setelah ia benar-benar Selesai.
     *
     * SIAPA memasukkan No. Rujukan tidak ditakrifkan di sini: setiap No.
     * Rujukan dimasukkan oleh PPR tanpa mengira siapa memiliki peringkatnya
     * (lihat self::PERANAN_RUJUKAN).
     *
     * @return array<string, array<string, mixed>>
     */
    public static function semua(): array
    {
        return [
            self::PENERIMAAN_DATA => [
                'utama' => 1,
                'label' => 'Penerimaan Data',
                'fasa' => self::FASA_SEMASA,
                'peranan' => [User::ROLE_KETUA_BAHAGIAN, User::ROLE_COORDINATOR],
                'gate' => 'manage-stage-penerimaan',
                'medan' => [
                    self::MEDAN_TARIKH_TERIMA => 'Tarikh Terima',
                    self::MEDAN_STATUS_BORANG => 'Status Borang Penerimaan Data',
                ],
                'rujukan' => 'No. Rujukan Borang Penerimaan Data',

                // Selesai menuntut ketiga-tiganya; meneruskan ke peringkat 1.2
                // menuntut dua sahaja — No. Rujukan milik PPR dan tidak
                // sepatutnya menahan kerja peringkat berikutnya.
                'syarat_selesai' => [
                    self::MEDAN_TARIKH_TERIMA,
                    self::MEDAN_STATUS_BORANG,
                    self::MEDAN_NO_RUJUKAN,
                ],
                'syarat_lanjut' => [
                    self::MEDAN_TARIKH_TERIMA,
                    self::MEDAN_STATUS_BORANG,
                ],
            ],

            self::PENDAFTARAN_DATA => [
                'utama' => 1,
                'label' => 'Pendaftaran Data',
                'fasa' => self::FASA_SEMASA,
                'peranan' => [User::ROLE_COORDINATOR],
                'gate' => 'manage-stage-pendaftaran',
                'medan' => [
                    self::MEDAN_TARIKH_DAFTAR => 'Tarikh Daftar',
                    self::MEDAN_STATUS_BORANG => 'Status Borang Pendaftaran Data',
                ],
                'rujukan' => 'No. Rujukan Borang Pendaftaran Data',

                // Tiada butang "Selesai": peringkat ini Selesai apabila
                // kedua-dua medannya direkod. No. Rujukan TIDAK disenaraikan —
                // ia milik PPR, dan menuntutnya akan menahan peringkat 1.3
                // menunggu pegawai lain.
                'syarat_selesai' => [
                    self::MEDAN_TARIKH_DAFTAR,
                    self::MEDAN_STATUS_BORANG,
                ],
                'syarat_lanjut' => [
                    self::MEDAN_TARIKH_DAFTAR,
                    self::MEDAN_STATUS_BORANG,
                ],

                // ...DAN seorang Pegawai Analisis mesti ditugaskan. Peringkat
                // 1.3 ialah kerja PA; tanpa pegawai yang ditugaskan, tiada
                // sesiapa yang boleh membukanya.
                //
                // Ini syarat LANJUT sahaja, bukan syarat Selesai: pendaftaran
                // data itu sendiri sudah lengkap dengan dua medannya. Peringkat
                // 1.2 boleh Selesai sementara penugasan masih tertunggak.
                'lanjut_perlu_penugasan' => true,
            ],

            self::SEMAKAN_AWAL_DATA => [
                'utama' => 1,
                'label' => 'Semakan Awal Data',
                'fasa' => self::FASA_SEMASA,
                'peranan' => [User::ROLE_ANALYST],
                'gate' => 'advance-analysis-stage',
                'medan' => [
                    self::MEDAN_TARIKH_SEMAKAN => 'Tarikh Semakan',
                    self::MEDAN_STATUS_BORANG => 'Status Borang Semakan Awal Data',
                ],
                'rujukan' => 'No. Rujukan Borang Semakan Awal Data',
            ],

            self::PENYEDIAAN_DATA => [
                'utama' => 2,
                'label' => 'Penyediaan & Pengesahan Data',
                'fasa' => self::FASA_SEMASA,
                'peranan' => [User::ROLE_ANALYST],
                'gate' => 'advance-analysis-stage',
                'medan' => [
                    self::MEDAN_TARIKH_MULA => 'Tarikh Mula',
                    self::MEDAN_TARIKH_TAMAT => 'Tarikh Tamat',
                    self::MEDAN_STATUS_BORANG => 'Status Mastertable',
                    self::MEDAN_NAMA_FAIL => 'Nama Fail',
                ],
                'rujukan' => null,
            ],

            self::ANALISIS_INVENTORI => [
                'utama' => 3,
                'label' => 'Analisis Inventori Kriptografi',
                'fasa' => self::FASA_SEMASA,
                'peranan' => [User::ROLE_ANALYST],
                'gate' => 'advance-analysis-stage',
                'medan' => [
                    self::MEDAN_TARIKH_MULA => 'Tarikh Mula',
                    self::MEDAN_TARIKH_TAMAT => 'Tarikh Tamat',
                    self::MEDAN_STATUS_BORANG => 'Status Laporan Inventori Kriptografi',
                ],
                'rujukan' => 'No. Rujukan Laporan',
            ],

            self::ANALISIS_RISIKO_PQC => [
                'utama' => 3,
                'label' => 'Analisis Risiko Migrasi PQC',
                'fasa' => self::FASA_AKAN_DATANG,
                'peranan' => [],
                'gate' => null,
                'medan' => [],
                'rujukan' => null,
            ],

            self::PENJANAAN_LAPORAN => [
                'utama' => 4,
                'label' => 'Penjanaan Laporan',
                'fasa' => self::FASA_AKAN_DATANG,
                'peranan' => [],
                'gate' => null,
                'medan' => [],
                'rujukan' => null,
            ],

            self::SEMAKAN_KELULUSAN => [
                'utama' => 5,
                'label' => 'Semakan, Kelulusan & Penyerahan Laporan',
                'fasa' => self::FASA_AKAN_DATANG,
                'peranan' => [],
                'gate' => null,
                'medan' => [],
                'rujukan' => null,
            ],
        ];
    }

    /**
     * Semua kunci peringkat mengikut turutan aliran.
     *
     * Kunci DICASTKAN kepada string di sini kerana PHP menukar kunci array
     * berbentuk angka kepada integer secara senyap: '2', '4' dan '5' menjadi
     * 2, 4 dan 5 di dalam self::semua(). Tanpa cast ini, perbandingan ketat
     * (in_array …, true) terhadap kunci daripada pangkalan data — yang
     * sentiasa string — akan gagal bagi tiga peringkat itu sahaja, iaitu
     * pepijat yang hanya menyerang peringkat tanpa sub-peringkat.
     *
     * @return array<int, string>
     */
    public static function kekunci(): array
    {
        return array_map(strval(...), array_keys(self::semua()));
    }

    /**
     * Bentuk kanonik satu kunci peringkat: sentiasa string.
     */
    private static function kunci(mixed $key): ?string
    {
        return is_string($key) || is_int($key) ? (string) $key : null;
    }

    /**
     * Kunci peringkat yang berfungsi dalam fasa semasa (1.1 hingga 3.1).
     *
     * Inilah senarai yang menakrifkan "kerja yang boleh dilakukan sekarang".
     * Kemajuan, peratusan dan status keseluruhan semuanya dikira terhadapnya
     * — jika tidak, tiada entiti boleh mencapai Siap selagi 3.2, 4 dan 5
     * belum dibina.
     *
     * @return array<int, string>
     */
    public static function semasa(): array
    {
        return array_map(strval(...), array_keys(array_filter(
            self::semua(),
            fn (array $def): bool => $def['fasa'] === self::FASA_SEMASA,
        )));
    }

    /**
     * Kunci peringkat yang dikhaskan untuk fasa akan datang.
     *
     * @return array<int, string>
     */
    public static function akanDatang(): array
    {
        return array_map(strval(...), array_keys(array_filter(
            self::semua(),
            fn (array $def): bool => $def['fasa'] === self::FASA_AKAN_DATANG,
        )));
    }

    public static function wujud(mixed $key): bool
    {
        $kunci = self::kunci($key);

        return $kunci !== null && array_key_exists($kunci, self::semua());
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function def(mixed $key): ?array
    {
        return self::wujud($key) ? self::semua()[self::kunci($key)] : null;
    }

    /**
     * Nama peringkat sahaja (tanpa nombor).
     */
    public static function label(mixed $key): string
    {
        return self::def($key)['label'] ?? 'Peringkat Tidak Dikenali';
    }

    /**
     * Nombor dan nama — bentuk yang dipaparkan kepada pengguna.
     * Contoh: "1.1 Penerimaan Data".
     */
    public static function labelPenuh(mixed $key): string
    {
        return self::wujud($key)
            ? self::kunci($key).' '.self::label($key)
            : self::label($key);
    }

    /**
     * Nombor peringkat utama yang memiliki peringkat ini.
     */
    public static function utamaBagi(mixed $key): ?int
    {
        return self::def($key)['utama'] ?? null;
    }

    public static function labelUtama(int $utama): string
    {
        return self::UTAMA[$utama] ?? 'Peringkat Tidak Dikenali';
    }

    /**
     * Kunci sub-peringkat bagi satu peringkat utama, mengikut turutan.
     *
     * Peringkat utama tanpa sub-peringkat mengembalikan satu kunci sahaja —
     * kuncinya ialah nombor utama itu sendiri ('2', '4', '5').
     *
     * @return array<int, string>
     */
    public static function subPeringkat(int $utama): array
    {
        return array_map(strval(...), array_keys(array_filter(
            self::semua(),
            fn (array $def): bool => $def['utama'] === $utama,
        )));
    }

    /**
     * Adakah peringkat utama ini benar-benar mempunyai sub-peringkat?
     *
     * Benar bagi 1 dan 3 sahaja; peringkat 2, 4 dan 5 ialah proses tunggal.
     */
    public static function adaSubPeringkat(int $utama): bool
    {
        $sub = self::subPeringkat($utama);

        return count($sub) > 1 || ($sub !== [] && $sub[0] !== (string) $utama);
    }

    public static function fasa(mixed $key): ?string
    {
        return self::def($key)['fasa'] ?? null;
    }

    public static function adalahSemasa(mixed $key): bool
    {
        return self::fasa($key) === self::FASA_SEMASA;
    }

    public static function adalahAkanDatang(mixed $key): bool
    {
        return self::fasa($key) === self::FASA_AKAN_DATANG;
    }

    /**
     * Kedudukan peringkat dalam turutan aliran (bermula 1).
     *
     * Digunakan untuk menyusun baris pangkalan data — kunci string tidak
     * boleh disusun mengikut abjad tanpa ralat ('1.10' sebelum '1.2').
     */
    public static function ordinal(mixed $key): ?int
    {
        $kedudukan = array_search(self::kunci($key), self::kekunci(), true);

        return $kedudukan === false ? null : $kedudukan + 1;
    }

    /**
     * Peringkat sebelum ini dalam turutan aliran, atau null bagi yang pertama.
     */
    public static function sebelum(mixed $key): ?string
    {
        $kekunci = self::kekunci();
        $kedudukan = array_search(self::kunci($key), $kekunci, true);

        return $kedudukan === false || $kedudukan === 0 ? null : $kekunci[$kedudukan - 1];
    }

    /**
     * Peringkat berikutnya dalam turutan aliran, atau null bagi yang terakhir.
     */
    public static function selepas(mixed $key): ?string
    {
        $kekunci = self::kekunci();
        $kedudukan = array_search(self::kunci($key), $kekunci, true);

        return $kedudukan === false || $kedudukan + 1 >= count($kekunci)
            ? null
            : $kekunci[$kedudukan + 1];
    }

    /**
     * Peranan yang bertanggungjawab melaksanakan peringkat ini.
     *
     * @return array<int, string>
     */
    public static function peranan(mixed $key): array
    {
        return self::def($key)['peranan'] ?? [];
    }

    /**
     * Gate kebenaran yang melindungi tindakan peringkat ini, atau null jika
     * peringkat itu belum mempunyai tindakan (fasa akan datang).
     */
    public static function gate(mixed $key): ?string
    {
        return self::def($key)['gate'] ?? null;
    }

    /**
     * Medan data yang ditangkap oleh peringkat ini: lajur => label.
     *
     * @return array<string, string>
     */
    public static function medan(mixed $key): array
    {
        return self::def($key)['medan'] ?? [];
    }

    /**
     * Label No. Rujukan bagi peringkat ini, atau null jika ia tiada.
     */
    public static function labelRujukan(mixed $key): ?string
    {
        return self::def($key)['rujukan'] ?? null;
    }

    /**
     * Medan yang mesti ada sebelum peringkat ini boleh menjadi Selesai.
     *
     * Senarai kosong bermakna peringkat itu TIDAK diterbitkan daripada data:
     * pegawainya menandakannya Selesai secara eksplisit.
     *
     * @return array<int, string>
     */
    public static function syaratSelesai(mixed $key): array
    {
        return self::def($key)['syarat_selesai'] ?? [];
    }

    /**
     * Medan yang mesti ada sebelum peringkat SETERUSNYA boleh dimulakan.
     *
     * Senarai kosong bermakna peraturan lalai terpakai: peringkat seterusnya
     * hanya terbuka setelah peringkat ini benar-benar Selesai.
     *
     * @return array<int, string>
     */
    public static function syaratLanjut(mixed $key): array
    {
        return self::def($key)['syarat_lanjut'] ?? [];
    }

    /**
     * Adakah peringkat SETERUSNYA menuntut seorang Pegawai Analisis
     * ditugaskan kepada entiti?
     *
     * Diasingkan daripada `syarat_lanjut` kerana ia BUKAN medan pada baris
     * peringkat: penugasan hidup dalam `entiti_assignment`. Menyimpannya
     * sebagai penanda di sini mengekalkan satu tempat yang menjawab "apa yang
     * membuka peringkat seterusnya".
     */
    public static function perluPenugasanUntukLanjut(mixed $key): bool
    {
        return (bool) (self::def($key)['lanjut_perlu_penugasan'] ?? false);
    }

    /**
     * Adakah status peringkat ini DITERBITKAN daripada datanya, dan bukan
     * ditetapkan oleh butang?
     */
    public static function statusDiterbitkan(mixed $key): bool
    {
        return self::syaratSelesai($key) !== [];
    }

    /**
     * Label bagi satu lajur data peringkat — termasuk No. Rujukan, yang
     * labelnya disimpan berasingan daripada senarai medan.
     */
    public static function labelMedan(mixed $key, string $lajur): string
    {
        if ($lajur === self::MEDAN_NO_RUJUKAN) {
            return self::labelRujukan($key) ?? $lajur;
        }

        return self::medan($key)[$lajur] ?? $lajur;
    }

    /**
     * Peranan yang memasukkan No. Rujukan peringkat ini, atau null jika
     * peringkat itu langsung tiada No. Rujukan.
     *
     * Sentiasa PPR — termasuk No. Rujukan Laporan peringkat 3.1, yang TIDAK
     * dimasukkan oleh Pegawai Analisis walaupun laporan itu kerjanya.
     */
    public static function perananRujukan(mixed $key): ?string
    {
        return self::labelRujukan($key) === null ? null : self::PERANAN_RUJUKAN;
    }

    /**
     * Gate yang melindungi kemasukan No. Rujukan peringkat ini, atau null jika
     * peringkat itu langsung tiada No. Rujukan.
     */
    public static function gateRujukan(mixed $key): ?string
    {
        return self::labelRujukan($key) === null ? null : self::GATE_RUJUKAN;
    }

    /**
     * Bolehkah pengguna ini melaksanakan peringkat berkenaan?
     *
     * Semata-mata semakan PERANAN. Kawalan akses entiti ialah lapisan
     * berasingan (lihat EntityAccessService) dan kedua-duanya mesti lulus.
     */
    public static function bolehKendali(?User $user, mixed $key): bool
    {
        $peranan = self::peranan($key);

        return $user !== null && $peranan !== [] && $user->hasAnyRole($peranan);
    }

    /**
     * Nilai "Status Borang" yang sah bagi peringkat ini, atau senarai kosong
     * jika peringkat itu tidak menangkap status borang langsung.
     *
     * Dipanggil dan bukan dibaca terus daripada STATUS_BORANG, supaya satu
     * peringkat boleh diberi senarai tersendiri kelak tanpa mengubah setiap
     * tempat yang memaparkannya.
     *
     * @return array<int, string>
     */
    public static function statusBorang(mixed $key): array
    {
        return array_key_exists(self::MEDAN_STATUS_BORANG, self::medan($key))
            ? self::STATUS_BORANG
            : [];
    }

    /**
     * Semua lajur data peringkat yang boleh ditulis melalui borang.
     *
     * @return array<int, string>
     */
    public static function semuaMedan(): array
    {
        $medan = [];

        foreach (self::semua() as $def) {
            $medan = [...$medan, ...array_keys($def['medan'])];
        }

        return array_values(array_unique($medan));
    }

    /**
     * Pemetaan nombor peringkat aliran kerja LAMA (1–7) kepada kunci baharu.
     *
     * Dipegang di sini dan bukan di dalam fail migrasi kerana ia menerangkan
     * hubungan antara dua struktur, bukan satu langkah sekali laksana —
     * dan kerana ia perlu dibaca semula apabila rekod lama ditafsir.
     *
     * Dua perubahan bukan sekadar penomboran semula:
     *
     * - Peringkat lama 1 ("Penerimaan & Pendaftaran Data") menggabungkan DUA
     *   proses yang kini berasingan, jadi ia DIPECAHKAN kepada 1.1 dan 1.2.
     * - Peringkat lama 6 dan 7 ("Semakan & Kelulusan", "Penyerahan &
     *   Penutupan") kini satu peringkat, jadi kedua-duanya DIGABUNGKAN
     *   kepada 5.
     *
     * @var array<int, array<int, string>>
     */
    public const PETAAN_LAMA = [
        1 => [self::PENERIMAAN_DATA, self::PENDAFTARAN_DATA],
        2 => [self::SEMAKAN_AWAL_DATA],
        3 => [self::PENYEDIAAN_DATA],
        4 => [self::ANALISIS_INVENTORI],
        5 => [self::PENJANAAN_LAPORAN],
        6 => [self::SEMAKAN_KELULUSAN],
        7 => [self::SEMAKAN_KELULUSAN],
    ];
}
