<?php

namespace App\Http\Controllers;

use App\Models\AnalisisInventori;

class LaporanController extends Controller
{
    /**
     * Senarai entiti yang mempunyai dapatan analisis untuk dijana laporan.
     */
    public function index()
    {
        $rekod = AnalisisInventori::latest('updated_at')->paginate(15);

        return view('laporan.index', compact('rekod'));
    }

    /**
     * Jana Laporan Analisis Inventori Kriptografi mengikut templat rasmi.
     * Templat + business rules + input berstruktur -> kandungan laporan.
     */
    public function inventori(AnalisisInventori $analisis)
    {
        $data = $analisis->data;

        // Kumpulkan algoritma dipilih mengikut kategori.
        $ikutKategori = [];
        foreach ($data['algoritma'] ?? [] as $kunci => $nilai) {
            [$kategori, $nama] = array_pad(explode('|', $kunci, 2), 2, $kunci);
            $ikutKategori[$kategori][] = ['nama' => $nama] + $nilai;
        }

        $lapuk = $analisis->algoritmaLapuk();
        $kuantum = $analisis->algoritmaKuantum();

        $jumlahAset = collect($data['profil'] ?? [])->sum(fn ($p) => (int) ($p['jumlah'] ?? 0));

        // Business rule: kesimpulan "algoritma tidak lagi selamat" dijana
        // dengan nama algoritma diisi automatik daripada pilihan pengguna.
        $kesimpulanLapuk = sprintf(
            'Hasil analisis mengenal pasti penggunaan algoritma atau fungsi kriptografi yang mempunyai kelemahan keselamatan yang diketahui atau tidak lagi disyorkan%s. Walaupun kelemahan tersebut tidak semestinya berkaitan secara langsung dengan ancaman pengkomputeran kuantum, penggunaannya boleh meningkatkan risiko keselamatan dan menjejaskan tahap perlindungan sistem. Oleh itu, algoritma berkenaan perlu diberi perhatian untuk digantikan dengan mekanisme yang lebih selamat sebagai sebahagian daripada usaha pemodenan kriptografi dan persediaan migrasi PQC.',
            $lapuk ? ', iaitu '.implode(', ', $lapuk) : '',
        );

        return view('laporan.inventori', [
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
        ]);
    }
}
