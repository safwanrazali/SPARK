<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/*
 * Medan "Lain-lain" kini menyimpan NAMA dan BILANGAN sistem/aset bagi setiap
 * algoritma, sepadan dengan lajur "Bilangan Sistem/Aset Terlibat" dalam
 * jadual laporan.
 *
 * Bentuk lama ialah rentetan tunggal ("3DES") atau senarai rentetan
 * (["RSA"]). Kedua-duanya ditukar kepada [['nama' => ..., 'bilangan' => '']].
 * Bilangan dibiarkan kosong kerana ia tidak pernah direkodkan sebelum ini —
 * mengisinya dengan angka rekaan akan memalsukan dapatan laporan.
 *
 * BorangAnalisis::algoritmaLain() turut menerima bentuk lama semasa membaca,
 * jadi migrasi ini kemasan data; laporan berfungsi dengan atau tanpanya.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach ([['analisis_inventori', 'data'], ['analisis_draft_history', 'section_data']] as [$jadual, $lajur]) {
            DB::table($jadual)->orderBy('id')->chunkById(200, function ($baris) use ($jadual, $lajur) {
                foreach ($baris as $satu) {
                    $data = json_decode((string) $satu->{$lajur}, true);

                    if (! is_array($data) || ! array_key_exists('algoritma_lain', $data)) {
                        continue;
                    }

                    $lama = $data['algoritma_lain'];
                    $senarai = is_array($lama) ? $lama : [$lama];
                    $baharu = [];

                    foreach ($senarai as $entri) {
                        // Sudah berbentuk pasangan — biarkan.
                        if (is_array($entri)) {
                            $nama = trim((string) ($entri['nama'] ?? ''));
                            $bilangan = trim((string) ($entri['bilangan'] ?? ''));
                        } else {
                            $nama = is_scalar($entri) ? trim((string) $entri) : '';
                            $bilangan = '';
                        }

                        if ($nama !== '') {
                            $baharu[] = ['nama' => $nama, 'bilangan' => $bilangan];
                        }
                    }

                    if ($baharu === $lama) {
                        continue;
                    }

                    $data['algoritma_lain'] = $baharu;

                    DB::table($jadual)->where('id', $satu->id)->update([$lajur => json_encode($data)]);
                }
            });
        }
    }

    public function down(): void
    {
        // Bilangan yang telah direkodkan akan hilang jika dipulihkan kepada
        // senarai nama sahaja, jadi tiada tindakan songsang dilakukan.
    }
};
