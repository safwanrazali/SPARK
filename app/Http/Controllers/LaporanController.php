<?php

namespace App\Http\Controllers;

use App\Models\AnalisisInventori;
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
            ->paginate(15);

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

        $lapuk = $analisis->algoritmaLapuk();
        $kuantum = $analisis->algoritmaKuantum();

        $jumlahAset = collect($data['profil'] ?? [])->sum(fn ($p) => (int) ($p['jumlah'] ?? 0));

        $kesimpulanLapuk = sprintf(
            'Hasil analisis mengenal pasti penggunaan algoritma atau fungsi kriptografi yang mempunyai kelemahan keselamatan yang diketahui atau tidak lagi disyorkan%s. Walaupun kelemahan tersebut tidak semestinya berkaitan secara langsung dengan ancaman pengkomputeran kuantum, penggunaannya boleh meningkatkan risiko keselamatan dan menjejaskan tahap perlindungan sistem. Oleh itu, algoritma berkenaan perlu diberi perhatian untuk digantikan dengan mekanisme yang lebih selamat sebagai sebahagian daripada usaha pemodenan kriptografi dan persediaan migrasi PQC.',
            $lapuk ? ', iaitu '.implode(', ', $lapuk) : '',
        );

        return [
            'analisis' => $analisis,
            'data' => $data,
            'ikutKategori' => $ikutKategori,
            'lapuk' => $lapuk,
            'kuantum' => $kuantum,
            'jumlahAset' => $jumlahAset,
            'kesimpulanLapuk' => $kesimpulanLapuk,
            'ringkasanData' => config('kriptografi.ringkasan_data.'.($data['ringkasan_data'] ?? 'lengkap')),
            'tindakanBank' => config('kriptografi.tindakan_susulan'),
            'kesimpulanBank' => config('kriptografi.kesimpulan'),
            'pengesahan' => config('kriptografi.pengesahan_laporan'),
        ];
    }
}
