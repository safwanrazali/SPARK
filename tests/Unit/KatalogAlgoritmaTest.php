<?php

namespace Tests\Unit;

use Tests\TestCase;

/**
 * Katalog algoritma AKSA MySEAL 2.1 — data induk.
 *
 * Sumber: CyberSecurity Malaysia / MyKripto, dicapai 3 September 2026.
 *   Approved  : .../aksa-myseal/aksa-myseal-approved
 *   Neutral   : .../aksa-myseal/aksa-myseal-neutral
 *   Monitored : .../aksa-myseal/aksa-myseal-monitored
 *
 * Senarai rasmi di bawah ialah transkripsi BEBAS daripada ketiga-tiga laman
 * itu. Ia sengaja tidak dijana daripada config: jika kedua-duanya berkongsi
 * satu sumber, ujian ini tidak lagi membuktikan apa-apa. Apabila MyKripto
 * mengemas kini senarainya, kedua-dua fail ini perlu dikemas kini bersama.
 */
class KatalogAlgoritmaTest extends TestCase
{
    /**
     * AKSA MySEAL 2.1 Approved — primitif => algoritma.
     *
     * @return array<string, list<string>>
     */
    private function approvedRasmi(): array
    {
        return [
            'Sifer Blok' => [
                'AES', 'Camellia', 'CLEFIA', 'SEED',
                'HIGHT', 'PRESENT',
                'Deoxys-TBC', 'Skinny', 'XTS-AES',
            ],
            'Sifer Alir' => ['ChaCha20', 'HC', 'KCipher-2', 'MUGI', 'Rabbit'],
            'Fungsi Cincang Kriptografi' => ['SHA2', 'SHA3', 'SM3', 'PHOTON', 'SPONGENT'],
            'Penyulitan Asimetri' => [
                'ACE-KEM', 'ECIES-KEM', 'FACE-KEM', 'PSEC-KEM', 'RSA-KEM', 'RSA-OAEP',
                'DH Ephemeral-Ephemeral (C(2e,0s))',
                'DH Ephemeral-Static (C(1e,1s))',
                'DH Ephemeral-Static (C(1e,2s))',
                'DH Ephemeral-Static (C(2e,2s))',
                'ECDH Ephemeral-Ephemeral (C(2e,0s))',
                'ECDH Ephemeral-Static (C(1e,2s))',
                'ECDH Ephemeral-Static (C(2e,2s))',
                'ECDH Ephemeral-Static (C(1e,1s))',
                'mceliece 460896', 'mceliece 6688128', 'mceliece 6960119', 'mceliece 8192128',
                'FrodoKEM', 'eFrodoKEM', 'HQC', 'ML-KEM', 'NTRUHPS', 'NTRUHRSS',
            ],
            'Skim Tandatangan Digital' => [
                'BLS Signature Scheme',
                'Elliptic Curve Digital Signature Algorithm (ECDSA)',
                'Elliptic Curve Schnorr DSA (ECSDSA)',
                'RSA-PSS (RSA-Probabilistic Signature Scheme)',
                'ShangMi2 (SM2)',
                'LMS', 'XMSS', 'XMSS^MT',
                'ML-DSA', 'Falcon', 'SLH-DSA-SHA2', 'SLH-DSA-SHAKE',
            ],
            'Penjana Nombor Perdana Kriptografi' => [
                'Elliptic Curve Primality Test',
                'Miller-Rabin Primality Test',
                'Probabilistic Lucas Primality Test',
                'Pocklington Primality Test',
            ],
            'Penjana Bit Rawak Deterministik (DRBG)' => [
                'AES-CTR-DRBG', 'HMAC-SHA2-DRBG', 'SHA2-DRBG',
            ],
            'Kod Pengesahan Mesej (MAC)' => [
                'CMAC', 'GMAC', 'HMAC', 'KMAC', 'UMAC', 'XCBC-MAC',
                'Chaskey-12', 'LightMAC', 'MDx-MAC', 'MDx-MAC-Short',
                'Poly1305', "Tsudik's Keymode",
            ],
            'Fungsi Derivasi Kunci (KDF)' => [
                'One Step HASH-KDF-SHA', 'One Step HASH-KDF-SHA3',
                'One Step HMAC-KDF-SHA', 'One Step HMAC-KDF-SHA3',
                'One Step KMAC-KDF', 'Two Step HMAC-KDF-SHA',
                'Two Step HMAC-KDF-SHA3', 'Two Step AES-CMAC-KDF', 'KMAC-PRF-KDF',
                'Argon', 'bcrypt', 'PBKDF2-HMAC-SHA', 'PBKDF2-HMAC-SHA3', 'scrypt',
            ],
            'Penyulitan Disahkan (AE)' => [
                'ASCON-AEAD', 'ChaCha20-Poly1305', 'XChaCha20-Poly1305',
                'AES-CCM', 'GCM-AES-XPN', 'AES-GCM', 'AES-GCM-SIV',
                'Sophie Germain Counter Mode (SGCM)', 'AES-SIV-CMAC',
            ],
            'Penyulitan Homomorfik' => [
                'Exponential ElGamal Encryption',
                'Paillier Encryption',
                'Cheon-Kim-Kim-Song (CKKS) Homomorphic Encryption',
            ],
            'Kriptografi Ambang' => [
                'FROST (Ed25519, SHA-512)', 'FROST (Ed448, SHAKE256)',
                'FROST (Ristretto255, SHA-512)', 'FROST (Secp256k1, SHA-256)',
            ],
        ];
    }

    /**
     * AKSA MySEAL 2.1 Neutral — primitif => algoritma.
     *
     * @return array<string, list<string>>
     */
    private function neutralRasmi(): array
    {
        return [
            'Fungsi Cincang Kriptografi' => ['SHA-256'],
            'Penyulitan Homomorfik' => [
                'Brakerski-Fan-Vercauteren (BFV) Encryption',
                'Brakerski-Gentry-Vaikuntanathan (BGV) Encryption',
                'Chillotti-Gama-Georgieva-Izabachène (CGGI) Encryption',
                'Ducas-Micciancio (DM/FHEW) Encryption',
            ],
        ];
    }

    /**
     * AKSA MySEAL 2.1 Monitored — primitif => algoritma.
     *
     * Primitif "Symmetric Algorithms" dan "Digital Signature Verification"
     * pada laman rasmi tiada pasangan dalam senarai Approved, jadi ia menjadi
     * kategori tersendiri dalam katalog.
     *
     * @return array<string, list<string>>
     */
    private function monitoredRasmi(): array
    {
        return [
            'Algoritma Simetri' => [
                'Two-key TDEA Decryption',
                'Three-key TDEA Decryption',
                'SKIPJACK Decryption',
            ],
            'Pengesahan Tandatangan Digital' => ['DSA', 'ECDSA', 'RSA'],
            'Fungsi Cincang Kriptografi' => ['SHA-1'],
            'Kod Pengesahan Mesej (MAC)' => ['HMAC Verification', 'CMAC Verification'],
        ];
    }

    /**
     * Katalog diratakan: "Kategori|Algoritma" => metadata.
     *
     * Bentuk ini SAMA dengan kunci yang disimpan dalam Borang Input, jadi
     * ujian di sini menguji kunci sebenar dan bukan bentuk perantaraan.
     *
     * @return array<string, array<string, mixed>>
     */
    private function katalog(): array
    {
        $rata = [];

        foreach (config('kriptografi.kategori_algoritma') as $kategori => $subKumpulan) {
            foreach ($subKumpulan as $senarai) {
                foreach ($senarai as $nama => $meta) {
                    $rata[$kategori.'|'.$nama] = $meta;
                }
            }
        }

        return $rata;
    }

    /**
     * @param  array<string, list<string>>  $rasmi
     */
    private function assertSenaraiRasmiHadir(array $rasmi, string $myseal): void
    {
        $katalog = $this->katalog();

        foreach ($rasmi as $kategori => $algoritma) {
            foreach ($algoritma as $nama) {
                $kunci = $kategori.'|'.$nama;

                $this->assertArrayHasKey(
                    $kunci,
                    $katalog,
                    "Algoritma AKSA MySEAL 2.1 {$myseal} tiada dalam katalog: {$kunci}",
                );

                $this->assertSame(
                    $myseal,
                    $katalog[$kunci]['myseal'],
                    "Pengelasan MySEAL salah bagi {$kunci}",
                );
            }
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Data induk
    |--------------------------------------------------------------------------
    */

    public function test_setiap_algoritma_approved_hadir_dengan_pengelasan_betul(): void
    {
        $this->assertSenaraiRasmiHadir($this->approvedRasmi(), 'Approved');
    }

    public function test_setiap_algoritma_neutral_hadir_dengan_pengelasan_betul(): void
    {
        $this->assertSenaraiRasmiHadir($this->neutralRasmi(), 'Neutral');
    }

    public function test_setiap_algoritma_monitored_hadir_dengan_pengelasan_betul(): void
    {
        $this->assertSenaraiRasmiHadir($this->monitoredRasmi(), 'Monitored');
    }

    /**
     * Katalog tidak mengandungi apa-apa SELAIN ketiga-tiga senarai rasmi —
     * ujian ini yang menangkap algoritma yang direka sendiri.
     */
    public function test_katalog_tidak_mengandungi_algoritma_di_luar_senarai_rasmi(): void
    {
        $dijangka = [];

        foreach ([$this->approvedRasmi(), $this->neutralRasmi(), $this->monitoredRasmi()] as $senarai) {
            foreach ($senarai as $kategori => $algoritma) {
                foreach ($algoritma as $nama) {
                    $dijangka[] = $kategori.'|'.$nama;
                }
            }
        }

        $sebenar = array_keys($this->katalog());

        sort($dijangka);
        sort($sebenar);

        $this->assertSame($dijangka, $sebenar);
    }

    public function test_bilangan_mengikut_pengelasan_myseal(): void
    {
        $kira = ['Approved' => 0, 'Neutral' => 0, 'Monitored' => 0];

        foreach ($this->katalog() as $meta) {
            $kira[$meta['myseal']]++;
        }

        $this->assertSame(['Approved' => 104, 'Neutral' => 5, 'Monitored' => 9], $kira);
    }

    public function test_tiada_kunci_pendua_dalam_katalog(): void
    {
        $kunci = [];

        foreach (config('kriptografi.kategori_algoritma') as $kategori => $subKumpulan) {
            foreach ($subKumpulan as $senarai) {
                foreach (array_keys($senarai) as $nama) {
                    $kunci[] = $kategori.'|'.$nama;
                }
            }
        }

        $pendua = array_keys(array_filter(array_count_values($kunci), fn ($n) => $n > 1));

        $this->assertSame([], $pendua, 'Kunci katalog berulang: '.implode(', ', $pendua));
        $this->assertCount(118, $kunci);
    }

    public function test_setiap_algoritma_mempunyai_pengelasan_dan_medan_parameter(): void
    {
        $sah = array_keys(config('kriptografi.myseal_kategori'));

        foreach ($this->katalog() as $kunci => $meta) {
            $this->assertArrayHasKey('myseal', $meta, "Tiada pengelasan MySEAL: {$kunci}");
            $this->assertContains($meta['myseal'], $sah, "Pengelasan MySEAL tidak sah: {$kunci}");

            // Kunci mesti WUJUD walaupun laman rasmi tiada lajur parameter,
            // supaya paparan tidak perlu menyemak isset() pada setiap baris.
            $this->assertArrayHasKey('parameter', $meta, "Tiada medan parameter: {$kunci}");
        }
    }

    public function test_setiap_primitif_mempunyai_sekurang_kurangnya_satu_algoritma(): void
    {
        foreach (config('kriptografi.kategori_algoritma') as $kategori => $subKumpulan) {
            $bilangan = array_sum(array_map('count', $subKumpulan));

            $this->assertGreaterThan(0, $bilangan, "Primitif kosong: {$kategori}");
        }
    }

    /**
     * Kunci tersimpan ialah "Kategori|Algoritma" — DUA bahagian. Nama yang
     * mengandungi '|' akan memecahkan AnalisisInventori::padanan() dan
     * LaporanController::siapkanData(), yang kedua-duanya memecah kunci itu.
     */
    public function test_nama_kategori_dan_algoritma_tiada_pemisah_kunci(): void
    {
        foreach (config('kriptografi.kategori_algoritma') as $kategori => $subKumpulan) {
            $this->assertStringNotContainsString('|', $kategori);

            foreach ($subKumpulan as $senarai) {
                foreach (array_keys($senarai) as $nama) {
                    $this->assertStringNotContainsString('|', (string) $nama);
                }
            }
        }
    }

    /**
     * Panjang kunci, panjang cerna, varian dan set parameter rasmi dikekalkan.
     */
    public function test_parameter_rasmi_dikekalkan(): void
    {
        $katalog = $this->katalog();

        foreach ([
            'Sifer Blok|AES' => 'Panjang kunci: 128, 192, 256',
            'Sifer Blok|Skinny' => 'Panjang kunci: 64/192, 128/256, 128/384',
            'Fungsi Cincang Kriptografi|SHA2' => 'Panjang cerna: 384, 512, 512/224, 512/256',
            'Fungsi Cincang Kriptografi|SPONGENT' => 'Panjang cerna: 88, 128, 160, 224, 256',
            'Penyulitan Asimetri|ML-KEM' => 'Set parameter: 512, 768, 1024',
            'Penyulitan Asimetri|NTRUHPS' => 'Set parameter: 2048677, 4096821, 40961229',
            'Skim Tandatangan Digital|ML-DSA' => 'Varian: 44, 65, 87',
            'Skim Tandatangan Digital|SLH-DSA-SHAKE' => 'Varian: 128s, 192s, 256s, 128f, 192f, 256f',
            'Fungsi Derivasi Kunci (KDF)|Argon' => 'Varian: 2i, 2d, 2id',
            'Penyulitan Disahkan (AE)|ASCON-AEAD' => 'Varian: 128, 128a',
            'Pengesahan Tandatangan Digital|RSA' => '1024 ≤ len(n) < 2048',
            'Pengesahan Tandatangan Digital|ECDSA' => '160 ≤ len(n) < 224',
            'Kod Pengesahan Mesej (MAC)|HMAC Verification' => 'Panjang kunci < 112 bit',
        ] as $kunci => $parameter) {
            $this->assertSame($parameter, $katalog[$kunci]['parameter'], "Parameter salah bagi {$kunci}");
        }
    }

    /**
     * SHA-256 (Neutral) dan SHA2 (Approved) ialah DUA baris berasingan pada
     * laman rasmi: panjang cerna 224 dan 256 memang tiada dalam baris SHA2
     * Approved. Menggabungkannya akan menghapuskan perbezaan itu.
     */
    public function test_sha256_neutral_berasingan_daripada_sha2_approved(): void
    {
        $katalog = $this->katalog();

        $this->assertSame('Approved', $katalog['Fungsi Cincang Kriptografi|SHA2']['myseal']);
        $this->assertSame('Neutral', $katalog['Fungsi Cincang Kriptografi|SHA-256']['myseal']);
        $this->assertStringNotContainsString('256,', $katalog['Fungsi Cincang Kriptografi|SHA2']['parameter']);
    }
}
