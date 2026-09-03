<?php

namespace App\Services;

use App\Models\AnalisisInventori;
use App\Models\LaporanKomentar;
use App\Support\BorangAnalisis;
use App\Support\TeksBerformat;

/**
 * Menyusun data mentah AnalisisInventori kepada bentuk yang dibaca oleh
 * templat Laporan Analisis Inventori Kriptografi.
 *
 * SATU sumber bagi kedua-dua saluran laporan — pratonton skrin
 * (laporan.inventori) dan muat turun PDF (laporan.pdf.body) — supaya
 * kandungan kedua-duanya tidak boleh terpesong antara satu sama lain.
 *
 * Dipisahkan daripada LaporanController semata-mata kerana susunan kod:
 * controller mengesahkan kebenaran dan memulangkan respons, manakala
 * penyusunan data laporan ialah kerja yang berdiri sendiri dan boleh dibaca
 * tanpa lapisan HTTP. TIADA peraturan kandungan berubah semasa pemisahan.
 */
class LaporanPenyediaanService
{
    /**
     * Baris pengesahan laporan, dengan nama dan tarikh diambil daripada aliran
     * kerja sebenar apabila langkah tersebut telah dilaksanakan.
     *
     * Hanya baris bertanda `sumber` mempunyai langkah aliran kerja yang
     * sepadan; yang lain kekal kosong untuk ditandatangani secara manual.
     * Aliran kerja, kebenaran dan logik tandatangan TIDAK disentuh di sini —
     * kaedah ini hanya MEMBACA keadaan semakan yang sedia ada.
     *
     * @return list<array{peranan: string, nama: string, tarikh: string}>
     */
    public function pengesahan(AnalisisInventori $analisis): array
    {
        $semakan = app(LaporanSemakanService::class)->untuk($analisis->agency_code);

        return array_map(function (array $baris) use ($semakan) {
            $nama = (string) ($baris['nama'] ?? '');
            $tarikh = '';

            if (($baris['sumber'] ?? null) === 'disahkan' && $semakan?->disahkan_pada !== null) {
                $nama = $semakan->disahkanOleh?->name ?: $nama;
                $tarikh = $semakan->disahkan_pada->format('d/m/Y');
            }

            return [
                'peranan' => (string) $baris['peranan'],
                'nama' => $nama,
                'tarikh' => $tarikh,
            ];
        }, config('kriptografi.pengesahan_laporan'));
    }

    /**
     * Angka Romawi kecil (i, ii, iii...) untuk penomboran algoritma dalam
     * jadual. Senarai katalog terbesar ialah enam item, jadi julat pendek
     * memadai; nilai di luar julat jatuh kembali kepada digit.
     */
    private static function angkaRomawi(int $n): string
    {
        $romawi = ['i', 'ii', 'iii', 'iv', 'v', 'vi', 'vii', 'viii', 'ix', 'x'];

        return $romawi[$n - 1] ?? (string) $n;
    }

    /**
     * Eja bilangan dalam bentuk "satu (1)" untuk ayat Catatan.
     * Bilangan di luar senarai config jatuh kembali kepada digit sahaja.
     */
    private static function ejaBilangan(int $bilangan): string
    {
        $perkataan = config('kriptografi.bilangan_perkataan')[$bilangan] ?? null;

        return $perkataan === null ? (string) $bilangan : $perkataan . ' (' . $bilangan . ')';
    }

    /**
     * Sediakan semua data yang diperlukan oleh templat laporan
     * (dikongsi antara pratonton skrin dan muat turun PDF).
     *
     * @param  bool  $includeComments  Sertakan komentar KB/PPA — hanya untuk
     *                                  skrin dan hanya untuk peranan yang
     *                                  dibenarkan; TIDAK PERNAH untuk PDF.
     */
    public function siapkanData(AnalisisInventori $analisis, bool $includeComments = false): array
    {
        $data = $analisis->data;

        $ikutKategori = [];
        foreach ($data['algoritma'] ?? [] as $kunci => $nilai) {
            [$kategori, $nama] = array_pad(explode('|', $kunci, 2), 2, $kunci);
            $ikutKategori[$kategori][] = ['nama' => $nama] + $nilai;
        }

        // Algoritma dikumpulkan mengikut susunan katalog dalam config supaya
        // penomboran kategori stabil antara laporan, bukan mengikut susunan
        // pegawai menanda kotak semak. Hanya kategori yang MEMPUNYAI algoritma
        // dikenal pasti disenaraikan — lajur templat ialah "Dikenal Pasti".
        $algoritma = [];

        foreach (array_keys(config('kriptografi.kategori_algoritma')) as $kategori) {
            if (empty($ikutKategori[$kategori])) {
                continue;
            }

            $algoritma[] = [
                'kategori' => $kategori,
                'item' => array_map(fn($a, $i) => [
                    'label' => self::angkaRomawi($i + 1),
                    'nama' => $a['nama'],
                    'bilangan' => trim((string) ($a['bilangan'] ?? '')),
                ], $ikutKategori[$kategori], array_keys($ikutKategori[$kategori])),
            ];
        }

        // Baris "Lain-lain" templat: mekanisme di luar katalog AKSA MySEAL,
        // ditaip bebas oleh pegawai dan boleh lebih daripada satu.
        $lain = BorangAnalisis::algoritmaLain($data['algoritma_lain'] ?? null);

        if ($lain !== []) {
            $algoritma[] = [
                'kategori' => 'Lain-lain',
                'item' => array_map(fn($satu, $i) => [
                    'label' => self::angkaRomawi($i + 1),
                    'nama' => $satu['nama'],
                    'bilangan' => $satu['bilangan'],
                ], $lain, array_keys($lain)),
            ];
        }

        // Vendor dikumpulkan mengikut nama supaya vendor yang mempunyai
        // beberapa produk tidak berulang pada setiap baris. Pengumpulan ini
        // dilakukan pada masa PAPARAN sahaja — struktur data asal (senarai
        // baris rata) kekal tidak berubah.
        $kumpulanVendor = [];

        foreach ($data['vendor'] ?? [] as $baris) {
            $nama = trim((string) ($baris['nama'] ?? ''));
            $kunci = $nama !== '' ? $nama : '—';

            $kumpulanVendor[$kunci][] = [
                'produk' => trim((string) ($baris['produk'] ?? '')),
                'bilangan' => trim((string) ($baris['bilangan'] ?? '')),
            ];
        }

        $vendor = [];

        foreach ($kumpulanVendor as $nama => $item) {
            $vendor[] = [
                'nama' => $nama,
                // Nombor roman hanya apabila vendor mempunyai lebih daripada
                // satu produk; satu produk dipaparkan tanpa penomboran.
                'item' => array_map(fn($satu, $i) => $satu + [
                    'label' => count($item) > 1 ? self::angkaRomawi($i + 1) : '',
                ], $item, array_keys($item)),
            ];
        }

        // Cadangan tindakan susulan: ayat piawai yang DIPILIH pegawai daripada
        // bank dalam config, disusun mengikut indeks bank supaya urutannya
        // stabil, diikuti tindakan "Lain-lain" yang ditaip sendiri. Tiada
        // cadangan dijana sendiri oleh sistem.
        $tindakan = collect($data['tindakan'] ?? [])
            ->sort()
            ->map(fn($i) => config('kriptografi.tindakan_susulan')[$i]['tindakan'] ?? null)
            ->filter()
            ->values()
            ->all();

        // Tindakan tambahan boleh lebih daripada satu; semuanya menyusul
        // selepas ayat piawai yang dipilih daripada bank.
        foreach (BorangAnalisis::senaraiTeks($data['tindakan_lain'] ?? null) as $satu) {
            $tindakan[] = $satu;
        }

        // Profil sistem dan aset dibina mengikut susunan kategori dalam config,
        // BUKAN mengikut susunan kunci yang tersimpan. Ini memastikan keempat-empat
        // baris templat sentiasa hadir dan tersusun sama, walaupun rekod lama
        // tidak mengandungi salah satu kategori.
        $profil = collect(config('kriptografi.kategori_profil'))
            ->map(fn($kategori) => [
                'perkara' => $kategori,
                'jumlah' => (int) ($data['profil'][$kategori]['jumlah'] ?? 0),
            ])
            ->all();

        // Fail sumber bagi nota "Catatan:" dalam seksyen Status Penerimaan dan
        // Kebolehgunaan Data. Diambil daripada input borang, BUKAN daripada
        // modul muat naik: spesifikasi bahagian 3 menetapkan aliran pelaporan
        // tidak boleh bergantung pada modul tersebut (dikuatkuasakan oleh
        // Phase13ReleaseReadinessTest::test_aliran_pelaporan_tidak_merujuk_modul_muat_naik).
        $failSumber = BorangAnalisis::senaraiTeks($data['fail_sumber'] ?? null);

        $result = [
            'analisis' => $analisis,
            'data' => $data,
            'profil' => $profil,
            'ulasanProfil' => TeksBerformat::blok($data['ulasan_profil'] ?? null),
            'algoritma' => $algoritma,
            'ulasanAlgoritma' => TeksBerformat::blok($data['ulasan_algoritma'] ?? null),
            'ulasanProtokol' => TeksBerformat::blok($data['ulasan_protokol'] ?? null),
            'ulasanPustaka' => TeksBerformat::blok($data['ulasan_pustaka'] ?? null),
            'vendor' => $vendor,
            'ulasanVendor' => TeksBerformat::blok($data['ulasan_vendor'] ?? null),
            'klasifikasi' => config('kriptografi.klasifikasi_laporan'),
            'failSumber' => $failSumber,
            'bilanganFail' => self::ejaBilangan(count($failSumber)),
            'tindakan' => $tindakan,
            'kesimpulan' => TeksBerformat::blok($data['kesimpulan'] ?? null),
            'pengesahan' => $this->pengesahan($analisis),
        ];

        // Komentar KB/PPA hanya dipaparkan pada skrin, TIDAK PERNAH dalam
        // PDF: `unduh()` memanggil kaedah ini dengan includeComments: false,
        // jadi laporan/pdf/body.blade.php sentiasa menerima koleksi KOSONG.
        //
        // Kunci ini sentiasa wujud supaya paparan tidak perlu menyemak
        // isset() pada setiap seksyen — ia sekadar kosong apabila pengguna
        // tiada akses kepada modul komentar atau apabila PDF sedang dijana.
        $result['komentar'] = $includeComments
            ? LaporanKomentar::forAgency($analisis->agency_code)
                ->with(['user', 'tindakanOleh'])
                ->get()
                ->groupBy('section')
            : collect();

        return $result;
    }
}
