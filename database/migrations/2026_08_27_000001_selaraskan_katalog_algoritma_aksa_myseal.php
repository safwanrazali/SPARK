<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/*
 * Katalog algoritma diselaraskan dengan AKSA MySEAL 2.1 (Approved):
 * 12 kategori / 104 algoritma, ditambah satu kumpulan legasi bagi algoritma
 * lapuk dan klasik yang TIADA dalam senarai Approved tetapi masih perlu
 * direkodkan (lihat komen dalam config/kriptografi.php).
 *
 * Algoritma disimpan sebagai kunci "Kategori|Algoritma" dalam JSON, jadi
 * penamaan semula kategori memutuskan kaitan rekod lama dengan katalog:
 * `$ikutKategori` tidak lagi sepadan dengan mana-mana kategori config dan
 * algoritma yang telah ditanda akan HILANG daripada jadual laporan.
 *
 * Pemetaan di bawah menutup jurang itu. Ia meliputi setiap kunci yang benar-
 * benar wujud dalam pangkalan data ini serta padanan yang jelas bagi katalog
 * lama seluruhnya, supaya pemasangan lain turut selamat.
 */
return new class extends Migration
{
    private const PETAAN = [
        // Kategori kekal, nama kategori berubah.
        'Simetrik Blok|AES' => 'Sifer Blok|AES',
        'Simetrik Blok|Camellia' => 'Sifer Blok|Camellia',
        'Simetrik Blok|CLEFIA' => 'Sifer Blok|CLEFIA',
        'Simetrik Blok|SEED' => 'Sifer Blok|SEED',
        'Simetrik Alir|ChaCha20' => 'Sifer Alir|ChaCha20',

        // Nama algoritma turut berubah mengikut penamaan AKSA MySEAL.
        'Fungsi Cincang|SHA-2' => 'Fungsi Cincang Kriptografi|SHA2',
        'Fungsi Cincang|SHA-3' => 'Fungsi Cincang Kriptografi|SHA3',
        'Fungsi Cincang|SM3' => 'Fungsi Cincang Kriptografi|SM3',
        'Skim Tandatangan Digital|RSASSA-PSS' => 'Skim Tandatangan Digital|RSA-PSS (RSA-Probabilistic Signature Scheme)',
        'Skim Tandatangan Digital|ECDSA' => 'Skim Tandatangan Digital|Elliptic Curve Digital Signature Algorithm (ECDSA)',
        'Kod Pengesahan Mesej (MAC)|HMAC' => 'Kod Pengesahan Mesej (MAC)|HMAC',
        'Kod Pengesahan Mesej (MAC)|CMAC' => 'Kod Pengesahan Mesej (MAC)|CMAC',
        'Fungsi Derivasi Kunci (KDF)|PBKDF2' => 'Fungsi Derivasi Kunci (KDF)|PBKDF2-HMAC-SHA',
        'Penyulitan Disahkan (AEAD)|AES-GCM' => 'Penyulitan Disahkan (AE)|AES-GCM',
        'Penyulitan Disahkan (AEAD)|ChaCha20-Poly1305' => 'Penyulitan Disahkan (AE)|ChaCha20-Poly1305',
        'Penjana Bit Rawak Deterministik (DRBG)|Hash_DRBG' => 'Penjana Bit Rawak Deterministik (DRBG)|SHA2-DRBG',
        'Penjana Bit Rawak Deterministik (DRBG)|HMAC_DRBG' => 'Penjana Bit Rawak Deterministik (DRBG)|HMAC-SHA2-DRBG',
        'Penjana Bit Rawak Deterministik (DRBG)|CTR_DRBG' => 'Penjana Bit Rawak Deterministik (DRBG)|AES-CTR-DRBG',

        // Tiada padanan dalam senarai Approved — dipindahkan ke kumpulan legasi.
        'Simetrik Blok|3DES' => 'Legasi / Luar Senarai AKSA MySEAL|3DES',
        'Simetrik Blok|Blowfish' => 'Legasi / Luar Senarai AKSA MySEAL|Blowfish',
        'Simetrik Alir|RC4' => 'Legasi / Luar Senarai AKSA MySEAL|RC4',
        'Fungsi Cincang|SHA-1' => 'Legasi / Luar Senarai AKSA MySEAL|SHA-1',
        'Fungsi Cincang|MD5' => 'Legasi / Luar Senarai AKSA MySEAL|MD5',
        'Asimetrik (Penyulitan)|RSA' => 'Legasi / Luar Senarai AKSA MySEAL|RSA',
        'Asimetrik (Penyulitan)|ElGamal' => 'Legasi / Luar Senarai AKSA MySEAL|ElGamal',
        'Skim Tandatangan Digital|DSA' => 'Legasi / Luar Senarai AKSA MySEAL|DSA',
        'Skim Tandatangan Digital|RSASSA-PKCS1-v1_5' => 'Legasi / Luar Senarai AKSA MySEAL|RSASSA-PKCS1-v1_5',
        'Penjanaan / Persetujuan Kunci|DH' => 'Legasi / Luar Senarai AKSA MySEAL|DH',
        'Penjanaan / Persetujuan Kunci|ECDH' => 'Legasi / Luar Senarai AKSA MySEAL|ECDH',
        'Penjanaan / Persetujuan Kunci|ECDHE' => 'Legasi / Luar Senarai AKSA MySEAL|ECDHE',
        'Fungsi Derivasi Kunci (KDF)|HKDF' => 'Legasi / Luar Senarai AKSA MySEAL|HKDF',
    ];

    public function up(): void
    {
        $this->petakan(self::PETAAN);
    }

    public function down(): void
    {
        // Pemetaan songsang; 'HMAC'/'CMAC' dipetakan kepada dirinya sendiri
        // dalam PETAAN, jadi array_flip kekal selamat.
        $this->petakan(array_flip(self::PETAAN));
    }

    /**
     * @param  array<string, string>  $petaan
     */
    private function petakan(array $petaan): void
    {
        foreach ([['analisis_inventori', 'data'], ['analisis_draft_history', 'section_data']] as [$jadual, $lajur]) {
            DB::table($jadual)->orderBy('id')->chunkById(200, function ($baris) use ($jadual, $lajur, $petaan) {
                foreach ($baris as $satu) {
                    $data = json_decode((string) $satu->{$lajur}, true);

                    if (! is_array($data) || empty($data['algoritma']) || ! is_array($data['algoritma'])) {
                        continue;
                    }

                    $baharu = [];
                    $berubah = false;

                    foreach ($data['algoritma'] as $kunci => $nilai) {
                        $ganti = $petaan[$kunci] ?? $kunci;
                        $berubah = $berubah || $ganti !== $kunci;
                        $baharu[$ganti] = $nilai;
                    }

                    if ($berubah) {
                        $data['algoritma'] = $baharu;
                        DB::table($jadual)->where('id', $satu->id)->update([$lajur => json_encode($data)]);
                    }
                }
            });
        }
    }
};
