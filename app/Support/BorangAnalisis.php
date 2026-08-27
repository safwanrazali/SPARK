<?php

namespace App\Support;

use App\Models\AnalisisInventori;
use Illuminate\Http\Request;

/**
 * FASA 6 — pemetaan tunggal antara borang input dan struktur data analisis.
 *
 * Logik ini sebelum ini berada di dalam AnalisisInventoriController@simpan.
 * Ia diasingkan supaya simpanan draf dan simpanan muktamad menggunakan
 * struktur yang SAMA — draf yang disambung semula tidak boleh terpesong
 * daripada bentuk data yang akhirnya masuk ke dalam laporan.
 */
class BorangAnalisis
{
    /**
     * Medan yang disimpan sebagai lajur pada analisis_inventori,
     * bukan di dalam JSON `data`.
     */
    public const MEDAN_LAJUR = ['tarikh_laporan', 'kod_rujukan', 'status_laporan'];

    /**
     * Baca keseluruhan keadaan borang daripada permintaan.
     *
     * Tiada pengesahan dilakukan di sini — draf dibenarkan separa lengkap.
     *
     * @return array<string, mixed>
     */
    public static function daripadaRequest(Request $request): array
    {
        return [
            // Nilai yang tidak dihantar dikekalkan sebagai null supaya draf
            // menggambarkan apa yang pegawai benar-benar isi. Nilai lalai
            // hanya dikenakan pada simpanan muktamad (@see kepadaModel).
            'tarikh_laporan' => $request->input('tarikh_laporan') ?: null,
            'kod_rujukan' => $request->input('kod_rujukan') ?: null,
            'status_laporan' => $request->input('status_laporan') ?: null,

            'data_status' => self::dataStatus($request),

            // Fail rujukan yang menjadi sumber analisis, dipaparkan di bawah
            // "Catatan:" dalam laporan. Direkodkan sebagai input borang kerana
            // aliran pelaporan tidak boleh bergantung pada modul muat naik
            // (spesifikasi bahagian 3).
            'fail_sumber' => self::senaraiTeks($request->input('fail_sumber')),
            'profil' => self::profil($request),
            'ulasan_profil' => trim((string) $request->input('ulasan_profil', '')),
            'algoritma' => self::algoritma($request),
            'algoritma_lain' => self::algoritmaLain($request->input('algoritma_lain')),
            'ulasan_algoritma' => trim((string) $request->input('ulasan_algoritma', '')),

            'protokol' => self::baris($request, 'protokol', ['nama', 'versi', 'bilangan', 'nota']),
            'pustaka' => self::baris($request, 'pustaka', ['nama', 'versi', 'bilangan', 'nota']),
            'vendor' => self::baris($request, 'vendor', ['nama', 'produk', 'versi', 'bilangan', 'nota']),

            'tindakan' => array_map('intval', (array) $request->input('tindakan', [])),
            'tindakan_lain' => trim((string) $request->input('tindakan_lain', '')),

            'kesimpulan' => array_values(array_intersect(
                (array) $request->input('kesimpulan', []),
                array_keys(config('kriptografi.kesimpulan')),
            )),
            'kesimpulan_lain' => trim((string) $request->input('kesimpulan_lain', '')),
        ];
    }

    /**
     * Keadaan borang bagi rekod analisis yang telah disimpan.
     *
     * @return array<string, mixed>
     */
    public static function daripadaModel(?AnalisisInventori $analisis): array
    {
        if ($analisis === null) {
            return [];
        }

        return array_merge($analisis->data ?? [], [
            'tarikh_laporan' => $analisis->tarikh_laporan?->format('Y-m-d'),
            'kod_rujukan' => $analisis->kod_rujukan,
            'status_laporan' => $analisis->status_laporan,
        ]);
    }

    /**
     * Pecahkan keadaan borang kepada lajur model dan JSON `data`.
     *
     * @param  array<string, mixed>  $borang
     * @return array{lajur: array<string, mixed>, data: array<string, mixed>}
     */
    public static function kepadaModel(array $borang): array
    {
        $lajur = [];

        foreach (self::MEDAN_LAJUR as $medan) {
            $lajur[$medan] = $borang[$medan] ?? null;
        }

        // Nilai lalai dikenakan hanya pada simpanan muktamad. Pilihan pertama
        // dalam config ialah status lalai (lihat config/kriptografi.php).
        $lajur['status_laporan'] ??= config('kriptografi.status_laporan')[0];

        $data = collect($borang)->except(self::MEDAN_LAJUR)->all();

        return [
            'lajur' => $lajur,
            'data' => $data,
        ];
    }

    /**
     * Normalkan medan teks boleh-berbilang kepada senarai rata.
     *
     * Digunakan oleh `data_status.*.nota` (penerangan status) dan
     * `fail_sumber` (senarai fail rujukan). Kedua-duanya dihantar sebagai
     * beberapa input bernama `[]`.
     *
     * `nota` dahulunya SATU rentetan (medan teks tunggal), jadi fungsi ini
     * menerima KEDUA-DUA bentuk supaya rekod lama terus terbaca tanpa migrasi
     * data: rentetan menjadi senarai satu item, dan nilai kosong digugurkan
     * supaya baris tidak memaparkan penerangan kosong.
     *
     * @return list<string>
     */
    public static function senaraiTeks(mixed $nota): array
    {
        if ($nota === null) {
            return [];
        }

        $senarai = is_array($nota) ? $nota : [$nota];

        return array_values(array_filter(
            array_map(fn ($n) => is_scalar($n) ? trim((string) $n) : '', $senarai),
            fn ($n) => $n !== '',
        ));
    }

    /**
     * Normalkan medan "Lain-lain" algoritma kepada senarai nama + bilangan.
     *
     * Tiga bentuk diterima supaya rekod lama terus terbaca tanpa migrasi:
     *   - rentetan tunggal            (borang asal)
     *   - senarai rentetan            (borang boleh-tambah pertama)
     *   - senarai ['nama','bilangan'] (bentuk semasa)
     *
     * Baris tanpa nama digugurkan; bilangan dikekalkan sebagai rentetan
     * supaya konsisten dengan medan bilangan algoritma katalog.
     *
     * @return list<array{nama: string, bilangan: string}>
     */
    public static function algoritmaLain(mixed $nilai): array
    {
        if ($nilai === null) {
            return [];
        }

        $senarai = is_array($nilai) ? $nilai : [$nilai];
        $bersih = [];

        foreach ($senarai as $satu) {
            if (is_array($satu)) {
                $nama = trim((string) ($satu['nama'] ?? ''));
                $bilangan = trim((string) ($satu['bilangan'] ?? ''));
            } else {
                $nama = is_scalar($satu) ? trim((string) $satu) : '';
                $bilangan = '';
            }

            if ($nama !== '') {
                $bersih[] = ['nama' => $nama, 'bilangan' => $bilangan];
            }
        }

        return $bersih;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private static function dataStatus(Request $request): array
    {
        $pilihan = config('kriptografi.kebolehgunaan_data');

        // Lalai ialah pilihan TERAKHIR ("Tidak Lengkap"): jadual yang tidak
        // disentuh oleh pegawai tidak boleh dianggap lengkap secara senyap.
        $lalai = $pilihan[count($pilihan) - 1] ?? null;

        $status = [];

        foreach (['j0', 'j1', 'j2'] as $j) {
            $status[$j] = [
                'kebolehgunaan' => $request->input("data_status.$j.kebolehgunaan", $lalai),
                'nota' => self::senaraiTeks($request->input("data_status.$j.nota")),
            ];
        }

        return $status;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private static function profil(Request $request): array
    {
        $profil = [];

        foreach (config('kriptografi.kategori_profil') as $kategori) {
            $profil[$kategori] = [
                'jumlah' => (int) $request->input('profil.'.md5($kategori).'.jumlah', 0),
            ];
        }

        return $profil;
    }

    /**
     * Hanya algoritma yang ditanda (checkbox) disimpan.
     *
     * @return array<string, array<string, string>>
     */
    private static function algoritma(Request $request): array
    {
        $algoritma = [];

        foreach ((array) $request->input('algoritma', []) as $nilai) {
            if (empty($nilai['dipilih']) || ! isset($nilai['id'])) {
                continue;
            }

            $algoritma[$nilai['id']] = [
                'bilangan' => $nilai['bilangan'] ?? '',
            ];
        }

        return $algoritma;
    }

    /**
     * Baris dinamik (protokol / pustaka / vendor); baris kosong dibuang.
     *
     * @param  array<int, string>  $kolum
     * @return array<int, array<string, string>>
     */
    private static function baris(Request $request, string $medan, array $kolum): array
    {
        return collect((array) $request->input($medan, []))
            ->map(fn ($baris) => collect($kolum)->mapWithKeys(
                fn ($k) => [$k => trim((string) ($baris[$k] ?? ''))]
            )->all())
            ->filter(fn ($baris) => collect($baris)->filter()->isNotEmpty())
            ->values()
            ->all();
    }
}
