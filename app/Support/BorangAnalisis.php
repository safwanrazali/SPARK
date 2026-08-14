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

            'ringkasan_data' => $request->input('ringkasan_data') ?: null,
            'data_status' => self::dataStatus($request),
            'profil' => self::profil($request),
            'algoritma' => self::algoritma($request),
            'algoritma_lain' => trim((string) $request->input('algoritma_lain', '')),

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

        // Nilai lalai dikenakan hanya pada simpanan muktamad.
        $lajur['status_laporan'] ??= 'Muktamad';

        $data = collect($borang)->except(self::MEDAN_LAJUR)->all();
        $data['ringkasan_data'] ??= 'lengkap';

        return [
            'lajur' => $lajur,
            'data' => $data,
        ];
    }

    /**
     * @return array<string, array<string, string>>
     */
    private static function dataStatus(Request $request): array
    {
        $status = [];

        foreach (['j0', 'j1', 'j2'] as $j) {
            $status[$j] = [
                'penerimaan' => $request->input("data_status.$j.penerimaan", 'Tiada'),
                'kebolehgunaan' => $request->input("data_status.$j.kebolehgunaan", 'Tidak Boleh Digunakan'),
                'nota' => $request->input("data_status.$j.nota", ''),
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
                'nota' => $request->input('profil.'.md5($kategori).'.nota', ''),
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
                'nota' => $nilai['nota'] ?? '',
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
