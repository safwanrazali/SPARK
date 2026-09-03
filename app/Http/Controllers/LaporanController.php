<?php

namespace App\Http\Controllers;

use App\Models\AnalisisInventori;
use App\Models\LaporanKomentar;
use App\Services\LaporanPenyediaanService;
use App\Support\Halaman;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Spatie\Browsershot\Browsershot;

class LaporanController extends Controller
{
    public function __construct(
        private readonly LaporanPenyediaanService $penyediaan,
    ) {}

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
     * 
     * Laporan boleh dilihat meskipun Borang Input belum disempurnakan.
     *
     * Komentar KB/PPA dimuatkan hanya untuk peranan yang mengambil bahagian
     * dalam modul komentar (PA, KB, PPA). Peranan lain — PS, TPII, PPR, PKD —
     * tetap melihat laporan, tetapi tidak menerima komentar dalam data
     * paparan langsung, bukan sekadar butangnya disembunyikan.
     */
    public function inventori(AnalisisInventori $analisis)
    {
        $this->authorize('view', $analisis);

        return view('laporan.inventori', $this->siapkanData(
            $analisis,
            includeComments: Gate::allows('viewAny', LaporanKomentar::class),
        ));
    }

    /**
     * Muat turun Laporan Analisis Inventori Kriptografi sebagai PDF,
     * dengan header (NACSA + PTPKM + RAHSIA) dan footer (kod rujukan +
     * nombor muka surat) berulang pada setiap muka surat.
     * 
     * Laporan boleh dimuat turun meskipun Borang Input belum disempurnakan
     * (tiada fasa kelulusan diperlukan). Komentar KB dan PPA TIDAK disertakan
     * dalam PDF yang dijana.
     */
    public function unduh(AnalisisInventori $analisis)
    {
        $this->authorize('generateReport', $analisis);

        $viewData = $this->siapkanData($analisis, includeComments: false);

        $bodyHtml = view('laporan.pdf.body', $viewData)->render();

        // Varian image/pdf/* ialah logo yang SAMA dengan image/logo_*.png,
        // cuma jidar lutsinar di sekelilingnya dibuang. Tiada piksel dakwat
        // dipotong. Ia diperlukan kerana kepala PDF memberi kedua-dua logo
        // kotak bersaiz tetap: dengan fail asal, jidar lutsinar itu mengambil
        // sebahagian besar kotak dan logo kelihatan jauh lebih kecil daripada
        // kotaknya. Fail asal SENGAJA dikekalkan untuk pratonton skrin —
        // resources/views/laporan/inventori.blade.php merujuknya secara terus.
        $headerHtml = view('laporan.pdf.header', [
            'nacsaLogoBase64' => base64_encode(file_get_contents(public_path('image/pdf/logo_nacsa_pdf.png'))),
            'ptpkmLogoBase64' => base64_encode(file_get_contents(public_path('image/pdf/logo_ptpkm_pdf.png'))),
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

        $namaFail = 'laporan-' . ($analisis->kod_rujukan ?: $analisis->id) . '.pdf';

        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="' . $namaFail . '"',
        ]);
    }

    /**
     * Baris pengesahan laporan.
     *
     * Dikekalkan sebagai kaedah controller kerana ia sebahagian daripada
     * permukaan yang disemak oleh PengesahanLaporanTest; logiknya sendiri
     * tinggal dalam LaporanPenyediaanService.
     *
     * @return list<array{peranan: string, nama: string, tarikh: string}>
     */
    private function pengesahan(AnalisisInventori $analisis): array
    {
        return $this->penyediaan->pengesahan($analisis);
    }

    /**
     * Sediakan semua data yang diperlukan oleh templat laporan
     * (dikongsi antara pratonton skrin dan muat turun PDF).
     *
     * Dikekalkan sebagai kaedah controller kerana ia sebahagian daripada
     * permukaan yang disemak oleh KomentarLaporanTest; logiknya sendiri
     * tinggal dalam LaporanPenyediaanService.
     *
     * @param  bool  $includeComments  Sertakan komentar KB/PPA — hanya untuk
     *                                  skrin dan hanya untuk peranan yang
     *                                  dibenarkan; TIDAK PERNAH untuk PDF.
     */
    private function siapkanData(AnalisisInventori $analisis, bool $includeComments = false): array
    {
        return $this->penyediaan->siapkanData($analisis, $includeComments);
    }
}
