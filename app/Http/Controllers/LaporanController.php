<?php

namespace App\Http\Controllers;

use App\Models\AnalisisInventori;
use App\Services\LaporanSemakanService;
use App\Support\BorangAnalisis;
use App\Support\Halaman;
use App\Support\TeksBerformat;
use Illuminate\Http\Request;
use Spatie\Browsershot\Browsershot;

class LaporanController extends Controller
{
    /**
     * Senarai entiti yang mempunyai dapatan analisis untuk dijana laporan.
     * Ditapis mengikut entiti yang boleh diakses pengguna (Fasa 4).
     */
    public function index(Request $request)
    {
        $rekod = AnalisisInventori::query()
            ->accessibleBy($request->user())
            ->latest('updated_at')
            ->paginate(Halaman::SETIAP_MUKA);

        return view('laporan.index', compact('rekod'));
    }

    /**
     * Jana Laporan Analisis Inventori Kriptografi mengikut templat rasmi.
     * Templat + business rules + input berstruktur -> kandungan laporan.
     */
    public function inventori(AnalisisInventori $analisis)
    {
        $this->authorize('view', $analisis);

        return view('laporan.inventori', $this->siapkanData($analisis));
    }

    /**
     * Muat turun Laporan Analisis Inventori Kriptografi sebagai PDF,
     * dengan header (NACSA + PTPKM + RAHSIA) dan footer (kod rujukan +
     * nombor muka surat) berulang pada setiap muka surat.
     */
    public function unduh(AnalisisInventori $analisis)
    {
        $this->authorize('generateReport', $analisis);

        // Carta aliran bahagian 12: hanya laporan yang telah disahkan Ketua
        // Bahagian (status Sah) boleh dimuat turun.
        $semakan = app(LaporanSemakanService::class)->untuk($analisis->agency_code);

        abort_unless(
            $semakan !== null && $semakan->isSah(),
            403,
            'Laporan ini belum disahkan. Hanya laporan berstatus Sah boleh dimuat turun.',
        );

        $viewData = $this->siapkanData($analisis);

        $bodyHtml = view('laporan.pdf.body', $viewData)->render();

        $headerHtml = view('laporan.pdf.header', [
            'nacsaLogoBase64' => base64_encode(file_get_contents(public_path('image/logo_nacsa.png'))),
            'ptpkmLogoBase64' => base64_encode(file_get_contents(public_path('image/logo_ptpkm.png'))),
        ])->render();

        $footerHtml = view('laporan.pdf.footer', [
            'kodRujukan' => $analisis->kod_rujukan ?? '[KOD RUJUKAN FAIL]',
        ])->render();

        $pdf = Browsershot::html($bodyHtml)
            ->format('A4')
            ->showBrowserHeaderAndFooter()
            ->headerHtml($headerHtml)
            ->footerHtml($footerHtml)
            // printBackground: TANPA ini Chrome menggugurkan SETIAP latar CSS
            // semasa mencetak ke PDF — sepanduk biru pengenalan laporan, sel
            // kelabu jadual maklumat dan latar <th> jadual laporan menjadi
            // putih, lalu teks putih di atasnya hilang sama sekali. Ia tidak
            // menjejaskan kepala/kaki halaman: kedua-duanya menggunakan <img>
            // dan teks sahaja, tiada latar CSS.
            ->showBackground()
            // Margin atas MESTI lebih besar daripada tinggi kotak-margin
            // header (kini ~37mm), kerana header dilukis di dalam ruang
            // margin ini pada SETIAP muka surat. Bakinya (47-37=10mm)
            // ialah jarak header->kandungan yang sama rata pada semua
            // muka surat. Jika saiz logo diubah, kira semula nilai ini.
            ->margins(47, 15, 22, 15)
            ->waitUntilNetworkIdle()
            ->writeOptionsToFile()   // must come before ->pdf()
            ->pdf();

        $namaFail = 'laporan-'.($analisis->kod_rujukan ?: $analisis->id).'.pdf';

        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$namaFail.'"',
        ]);
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

        return $perkataan === null ? (string) $bilangan : $perkataan.' ('.$bilangan.')';
    }

    /**
     * Sediakan semua data yang diperlukan oleh templat laporan
     * (dikongsi antara pratonton skrin dan muat turun PDF).
     */
    private function siapkanData(AnalisisInventori $analisis): array
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
                'item' => array_map(fn ($a, $i) => [
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
                'item' => array_map(fn ($satu, $i) => [
                    'label' => self::angkaRomawi($i + 1),
                    'nama' => $satu['nama'],
                    'bilangan' => $satu['bilangan'],
                ], $lain, array_keys($lain)),
            ];
        }

        $lapuk = $analisis->algoritmaLapuk();
        $kuantum = $analisis->algoritmaKuantum();

        // Profil sistem dan aset dibina mengikut susunan kategori dalam config,
        // BUKAN mengikut susunan kunci yang tersimpan. Ini memastikan keempat-empat
        // baris templat sentiasa hadir dan tersusun sama, walaupun rekod lama
        // tidak mengandungi salah satu kategori.
        $profil = collect(config('kriptografi.kategori_profil'))
            ->map(fn ($kategori) => [
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

        $kesimpulanLapuk = sprintf(
            'Hasil analisis mengenal pasti penggunaan algoritma atau fungsi kriptografi yang mempunyai kelemahan keselamatan yang diketahui atau tidak lagi disyorkan%s. Walaupun kelemahan tersebut tidak semestinya berkaitan secara langsung dengan ancaman pengkomputeran kuantum, penggunaannya boleh meningkatkan risiko keselamatan dan menjejaskan tahap perlindungan sistem. Oleh itu, algoritma berkenaan perlu diberi perhatian untuk digantikan dengan mekanisme yang lebih selamat sebagai sebahagian daripada usaha pemodenan kriptografi dan persediaan migrasi PQC.',
            $lapuk ? ', iaitu '.implode(', ', $lapuk) : '',
        );

        return [
            'analisis' => $analisis,
            'data' => $data,
            'profil' => $profil,
            'ulasanProfil' => TeksBerformat::blok($data['ulasan_profil'] ?? null),
            'algoritma' => $algoritma,
            'ulasanAlgoritma' => TeksBerformat::blok($data['ulasan_algoritma'] ?? null),
            'kesimpulanLapuk' => $kesimpulanLapuk,
            'klasifikasi' => config('kriptografi.klasifikasi_laporan'),
            'failSumber' => $failSumber,
            'bilanganFail' => self::ejaBilangan(count($failSumber)),
            'tindakanBank' => config('kriptografi.tindakan_susulan'),
            'kesimpulanBank' => config('kriptografi.kesimpulan'),
            'pengesahan' => config('kriptografi.pengesahan_laporan'),
        ];
    }
}
