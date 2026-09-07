<?php

/*
|--------------------------------------------------------------------------
| Rujukan Kriptografi — Platform Analisis & Pelaporan Migrasi PQC (Fasa 1)
|--------------------------------------------------------------------------
| Kategori algoritma mengikut templat Laporan Analisis Inventori Kriptografi
| dan rujukan AKSA MySEAL. Bank ayat tindakan susulan, kesimpulan dan
| ringkasan status data diambil terus daripada templat laporan rasmi.
*/

return [

    /*
    | Katalog algoritma AKSA MySEAL 2.1 — KETIGA-TIGA kategori rasmi.
    |
    | Sumber: CyberSecurity Malaysia / MyKripto — AKSA MySEAL 2.1
    |   Approved  : https://mykripto.cybersecurity.my/index.php/services/myseal/myseal-category/aksa-myseal/aksa-myseal-approved
    |   Neutral   : https://mykripto.cybersecurity.my/index.php/services/myseal/myseal-category/aksa-myseal/aksa-myseal-neutral
    |   Monitored : https://mykripto.cybersecurity.my/index.php/services/myseal/myseal-category/aksa-myseal/aksa-myseal-monitored
    | Tarikh capaian: 3 September 2026.
    |
    | Struktur: primitif => sub-kumpulan => algoritma => metadata.
    | Sub-kumpulan bernama '' bermakna kategori tersebut tiada sub-kumpulan
    | pada laman rasmi; borang tidak memaparkan tajuk kecil untuknya.
    |
    | Metadata setiap algoritma:
    |   'myseal'    — pengelasan RASMI MySEAL: Approved, Neutral atau
    |                 Monitored. TIDAK boleh disimpulkan sendiri; ia diambil
    |                 terus daripada laman kategori tempat algoritma itu
    |                 disenaraikan.
    |   'parameter' — panjang kunci / panjang cerna / varian / set parameter
    |                 seperti yang dipaparkan laman rasmi, atau null jika
    |                 laman itu tidak memberikan sebarang lajur sedemikian.
    |
    | PENTING — kunci tersimpan kekal berbentuk "Kategori|Algoritma" (DUA
    | bahagian). Sub-kumpulan, pengelasan MySEAL dan parameter ialah
    | maklumat PAPARAN/RUJUKAN sahaja dan TIDAK masuk ke dalam kunci.
    | Menambahnya akan memecahkan setiap rekod Borang Input sedia ada serta
    | AnalisisInventori::algoritmaLapuk() dan LaporanController::siapkanData(),
    | yang kedua-duanya mengambil bahagian kedua kunci.
    |
    | Algoritma yang TIADA langsung pada mana-mana daripada tiga laman itu
    | (cth. MD5, RC4, Blowfish) kekal direkodkan melalui medan "Lain-lain"
    | pada borang, seperti sebelum ini.
    */
    'kategori_algoritma' => [

        'Sifer Blok' => [
            'Tujuan Umum' => [
                'AES' => ['myseal' => 'Approved', 'parameter' => 'Panjang kunci: 128, 192, 256'],
                'Camellia' => ['myseal' => 'Approved', 'parameter' => 'Panjang kunci: 128, 192, 256'],
                'CLEFIA' => ['myseal' => 'Approved', 'parameter' => 'Panjang kunci: 128, 192, 256'],
                'SEED' => ['myseal' => 'Approved', 'parameter' => 'Panjang kunci: 128'],
            ],
            'Ringan' => [
                'HIGHT' => ['myseal' => 'Approved', 'parameter' => 'Panjang kunci: 128'],
                'PRESENT' => ['myseal' => 'Approved', 'parameter' => 'Panjang kunci: 80, 128'],
            ],
            'Boleh Laras (Tweakable)' => [
                'Deoxys-TBC' => ['myseal' => 'Approved', 'parameter' => 'Panjang kunci: 256, 384'],
                'Skinny' => ['myseal' => 'Approved', 'parameter' => 'Panjang kunci: 64/192, 128/256, 128/384'],
                'XTS-AES' => ['myseal' => 'Approved', 'parameter' => 'Panjang kunci: 128, 256'],
            ],
        ],

        'Sifer Alir' => [
            '' => [
                'ChaCha20' => ['myseal' => 'Approved', 'parameter' => 'Panjang kunci: 256'],
                'HC' => ['myseal' => 'Approved', 'parameter' => 'Panjang kunci: 128'],
                'KCipher-2' => ['myseal' => 'Approved', 'parameter' => 'Panjang kunci: 128'],
                'MUGI' => ['myseal' => 'Approved', 'parameter' => 'Panjang kunci: 128'],
                'Rabbit' => ['myseal' => 'Approved', 'parameter' => 'Panjang kunci: 128'],
            ],
        ],

        'Fungsi Cincang Kriptografi' => [
            'Tujuan Umum' => [
                'SHA2' => ['myseal' => 'Approved', 'parameter' => 'Panjang cerna: 384, 512, 512/224, 512/256'],
                'SHA3' => ['myseal' => 'Approved', 'parameter' => 'Panjang cerna: 224, 256, 384, 512'],
                'SM3' => ['myseal' => 'Approved', 'parameter' => 'Panjang cerna: 256'],
            ],
            'Ringan' => [
                'PHOTON' => ['myseal' => 'Approved', 'parameter' => 'Panjang cerna: 80/20/16 (P100), 128/16/16 (P144), 160/36/36 (P196), 224/32/32 (P256), 256/32/32 (P288)'],
                'SPONGENT' => ['myseal' => 'Approved', 'parameter' => 'Panjang cerna: 88, 128, 160, 224, 256'],
            ],
            // Laman Neutral menyenaraikan SHA-256 secara BERASINGAN daripada
            // SHA2 Approved — panjang cerna 224 dan 256 memang tiada dalam
            // baris SHA2 Approved. Kedua-duanya dikekalkan seperti sumber.
            'Neutral (MySEAL 2.1)' => [
                'SHA-256' => [
                    'myseal' => 'Neutral',
                    'parameter' => 'Dibenarkan sehingga tahun 2030 sahaja; untuk pencincangan kata laluan, tandatangan digital dan pengesahan integriti data; tidak disyorkan untuk pengesahan mesej; diiktiraf sebagai keselamatan 80-bit dalam skim Malaysia',
                ],
            ],
            'Dipantau (MySEAL 2.1)' => [
                'SHA-1' => ['myseal' => 'Monitored', 'parameter' => null],
            ],
        ],

        'Penyulitan Asimetri' => [
            'Skim Penyulitan' => [
                'ACE-KEM' => ['myseal' => 'Approved', 'parameter' => null],
                'ECIES-KEM' => ['myseal' => 'Approved', 'parameter' => null],
                'FACE-KEM' => ['myseal' => 'Approved', 'parameter' => null],
                'PSEC-KEM' => ['myseal' => 'Approved', 'parameter' => null],
                'RSA-KEM' => ['myseal' => 'Approved', 'parameter' => null],
                'RSA-OAEP' => ['myseal' => 'Approved', 'parameter' => null],
            ],
            'Skim Persetujuan Kunci' => [
                'DH Ephemeral-Ephemeral (C(2e,0s))' => ['myseal' => 'Approved', 'parameter' => null],
                'DH Ephemeral-Static (C(1e,1s))' => ['myseal' => 'Approved', 'parameter' => null],
                'DH Ephemeral-Static (C(1e,2s))' => ['myseal' => 'Approved', 'parameter' => null],
                'DH Ephemeral-Static (C(2e,2s))' => ['myseal' => 'Approved', 'parameter' => null],
                'ECDH Ephemeral-Ephemeral (C(2e,0s))' => ['myseal' => 'Approved', 'parameter' => null],
                'ECDH Ephemeral-Static (C(1e,2s))' => ['myseal' => 'Approved', 'parameter' => null],
                'ECDH Ephemeral-Static (C(2e,2s))' => ['myseal' => 'Approved', 'parameter' => null],
                'ECDH Ephemeral-Static (C(1e,1s))' => ['myseal' => 'Approved', 'parameter' => null],
            ],
            'Mekanisme Enkapsulasi Kunci Pasca-Kuantum' => [
                'mceliece 460896' => ['myseal' => 'Approved', 'parameter' => 'Set parameter: 460896, 460896f, 460896pc'],
                'mceliece 6688128' => ['myseal' => 'Approved', 'parameter' => 'Set parameter: 6688128, 6688128f, 6688128pc'],
                'mceliece 6960119' => ['myseal' => 'Approved', 'parameter' => 'Set parameter: 6960119, 6960119f, 6960119pc'],
                'mceliece 8192128' => ['myseal' => 'Approved', 'parameter' => 'Set parameter: 8192128, 8192128f, 8192128pc'],
                'FrodoKEM' => ['myseal' => 'Approved', 'parameter' => 'Set parameter: 976, 1344'],
                'eFrodoKEM' => ['myseal' => 'Approved', 'parameter' => 'Set parameter: 976, 1344'],
                'HQC' => ['myseal' => 'Approved', 'parameter' => 'Set parameter: 128, 192, 256'],
                'ML-KEM' => ['myseal' => 'Approved', 'parameter' => 'Set parameter: 512, 768, 1024'],
                'NTRUHPS' => ['myseal' => 'Approved', 'parameter' => 'Set parameter: 2048677, 4096821, 40961229'],
                'NTRUHRSS' => ['myseal' => 'Approved', 'parameter' => 'Set parameter: 701, 1373'],
            ],
        ],

        'Skim Tandatangan Digital' => [
            'Berasaskan Masalah Sukar Klasik' => [
                'BLS Signature Scheme' => ['myseal' => 'Approved', 'parameter' => null],
                'Elliptic Curve Digital Signature Algorithm (ECDSA)' => ['myseal' => 'Approved', 'parameter' => null],
                'Elliptic Curve Schnorr DSA (ECSDSA)' => ['myseal' => 'Approved', 'parameter' => null],
                'RSA-PSS (RSA-Probabilistic Signature Scheme)' => ['myseal' => 'Approved', 'parameter' => null],
                'ShangMi2 (SM2)' => ['myseal' => 'Approved', 'parameter' => null],
            ],
            'Berasaskan Cincang Berkeadaan' => [
                'LMS' => ['myseal' => 'Approved', 'parameter' => null],
                'XMSS' => ['myseal' => 'Approved', 'parameter' => null],
                'XMSS^MT' => ['myseal' => 'Approved', 'parameter' => null],
            ],
            'Pasca-Kuantum' => [
                'ML-DSA' => ['myseal' => 'Approved', 'parameter' => 'Varian: 44, 65, 87'],
                'Falcon' => ['myseal' => 'Approved', 'parameter' => 'Varian: 512, 1024'],
                'SLH-DSA-SHA2' => ['myseal' => 'Approved', 'parameter' => 'Varian: 128s, 192s, 256s, 128f, 192f, 256f'],
                'SLH-DSA-SHAKE' => ['myseal' => 'Approved', 'parameter' => 'Varian: 128s, 192s, 256s, 128f, 192f, 256f'],
            ],
        ],

        /*
        | Laman Monitored menyenaraikan PENGESAHAN tandatangan (operasi
        | mengesahkan tandatangan lama), bukan skim penandatanganan baharu.
        | Ia sengaja diasingkan daripada 'Skim Tandatangan Digital' di atas
        | supaya pengelasan Approved dan Monitored tidak bercampur pada
        | algoritma yang berkongsi nama (cth. ECDSA).
        */
        'Pengesahan Tandatangan Digital' => [
            'Kekuatan Keselamatan < 112 bit' => [
                'DSA' => ['myseal' => 'Monitored', 'parameter' => '(512 ≤ L < 2048) atau (160 ≤ N < 224)'],
                'ECDSA' => ['myseal' => 'Monitored', 'parameter' => '160 ≤ len(n) < 224'],
                'RSA' => ['myseal' => 'Monitored', 'parameter' => '1024 ≤ len(n) < 2048'],
            ],
        ],

        /*
        | Laman Monitored mengelompokkan TDEA dan SKIPJACK di bawah
        | "Symmetric Algorithms" — satu primitif yang lebih luas daripada
        | 'Sifer Blok'. Ia dikekalkan seperti sumber dan TIDAK dimasukkan ke
        | dalam 'Sifer Blok', kerana laman rasmi tidak menyatakan
        | pengelasan itu.
        */
        'Algoritma Simetri' => [
            'Keserasian Sistem Legasi' => [
                'Two-key TDEA Decryption' => ['myseal' => 'Monitored', 'parameter' => null],
                'Three-key TDEA Decryption' => ['myseal' => 'Monitored', 'parameter' => null],
                'SKIPJACK Decryption' => ['myseal' => 'Monitored', 'parameter' => null],
            ],
        ],

        'Penjana Nombor Perdana Kriptografi' => [
            'Algoritma Ujian Keperdanaan' => [
                'Elliptic Curve Primality Test' => ['myseal' => 'Approved', 'parameter' => null],
                'Miller-Rabin Primality Test' => ['myseal' => 'Approved', 'parameter' => null],
                'Probabilistic Lucas Primality Test' => ['myseal' => 'Approved', 'parameter' => null],
                'Pocklington Primality Test' => ['myseal' => 'Approved', 'parameter' => null],
            ],
        ],

        'Penjana Bit Rawak Deterministik (DRBG)' => [
            '' => [
                'AES-CTR-DRBG' => ['myseal' => 'Approved', 'parameter' => 'Panjang kunci: 128, 192, 256'],
                'HMAC-SHA2-DRBG' => ['myseal' => 'Approved', 'parameter' => 'Panjang cerna: 224, 256, 384, 512, 512/224, 512/256'],
                'SHA2-DRBG' => ['myseal' => 'Approved', 'parameter' => 'Panjang cerna: 224, 256, 384, 512, 512/224, 512/256'],
            ],
        ],

        'Kod Pengesahan Mesej (MAC)' => [
            '' => [
                'CMAC' => ['myseal' => 'Approved', 'parameter' => null],
                'GMAC' => ['myseal' => 'Approved', 'parameter' => null],
                'HMAC' => ['myseal' => 'Approved', 'parameter' => null],
                'KMAC' => ['myseal' => 'Approved', 'parameter' => null],
                'UMAC' => ['myseal' => 'Approved', 'parameter' => null],
                'XCBC-MAC' => ['myseal' => 'Approved', 'parameter' => null],
                'Chaskey-12' => ['myseal' => 'Approved', 'parameter' => null],
                'LightMAC' => ['myseal' => 'Approved', 'parameter' => null],
                'MDx-MAC' => ['myseal' => 'Approved', 'parameter' => null],
                'MDx-MAC-Short' => ['myseal' => 'Approved', 'parameter' => null],
                'Poly1305' => ['myseal' => 'Approved', 'parameter' => null],
                "Tsudik's Keymode" => ['myseal' => 'Approved', 'parameter' => null],
            ],
            'Dipantau (MySEAL 2.1)' => [
                'HMAC Verification' => ['myseal' => 'Monitored', 'parameter' => 'Panjang kunci < 112 bit'],
                'CMAC Verification' => ['myseal' => 'Monitored', 'parameter' => 'Two-key TDEA dan Three-key TDEA'],
            ],
        ],

        'Fungsi Derivasi Kunci (KDF)' => [
            'KDF Umum' => [
                'One Step HASH-KDF-SHA' => ['myseal' => 'Approved', 'parameter' => 'Panjang cerna: 224, 256, 384, 512, 512/224, 512/256'],
                'One Step HASH-KDF-SHA3' => ['myseal' => 'Approved', 'parameter' => 'Panjang cerna: 224, 256, 384, 512'],
                'One Step HMAC-KDF-SHA' => ['myseal' => 'Approved', 'parameter' => 'Panjang cerna: 224, 256, 384, 512, 512/224, 512/256'],
                'One Step HMAC-KDF-SHA3' => ['myseal' => 'Approved', 'parameter' => 'Panjang cerna: 224, 256, 384, 512'],
                'One Step KMAC-KDF' => ['myseal' => 'Approved', 'parameter' => 'Panjang kunci: 128, 256'],
                'Two Step HMAC-KDF-SHA' => ['myseal' => 'Approved', 'parameter' => 'Panjang cerna: 224, 256, 384, 512, 512/224, 512/256'],
                'Two Step HMAC-KDF-SHA3' => ['myseal' => 'Approved', 'parameter' => 'Panjang cerna: 224, 256, 384, 512'],
                'Two Step AES-CMAC-KDF' => ['myseal' => 'Approved', 'parameter' => 'Panjang kunci: 128, 192, 256'],
                'KMAC-PRF-KDF' => ['myseal' => 'Approved', 'parameter' => 'Panjang kunci: 128, 256'],
            ],
            'KDF Berasaskan Kata Laluan' => [
                'Argon' => ['myseal' => 'Approved', 'parameter' => 'Varian: 2i, 2d, 2id'],
                'bcrypt' => ['myseal' => 'Approved', 'parameter' => null],
                'PBKDF2-HMAC-SHA' => ['myseal' => 'Approved', 'parameter' => 'Panjang cerna: 224, 256, 384, 512, 512/224, 512/256'],
                'PBKDF2-HMAC-SHA3' => ['myseal' => 'Approved', 'parameter' => 'Panjang cerna: 224, 256, 384, 512'],
                'scrypt' => ['myseal' => 'Approved', 'parameter' => null],
            ],
        ],

        'Penyulitan Disahkan (AE)' => [
            '' => [
                'ASCON-AEAD' => ['myseal' => 'Approved', 'parameter' => 'Varian: 128, 128a'],
                'ChaCha20-Poly1305' => ['myseal' => 'Approved', 'parameter' => null],
                'XChaCha20-Poly1305' => ['myseal' => 'Approved', 'parameter' => null],
                'AES-CCM' => ['myseal' => 'Approved', 'parameter' => 'Panjang kunci: 128, 192, 256'],
                'GCM-AES-XPN' => ['myseal' => 'Approved', 'parameter' => 'Panjang kunci: 128, 256'],
                'AES-GCM' => ['myseal' => 'Approved', 'parameter' => 'Panjang kunci: 128, 192, 256'],
                'AES-GCM-SIV' => ['myseal' => 'Approved', 'parameter' => 'Panjang kunci: 128, 256'],
                'Sophie Germain Counter Mode (SGCM)' => ['myseal' => 'Approved', 'parameter' => null],
                'AES-SIV-CMAC' => ['myseal' => 'Approved', 'parameter' => 'Panjang kunci: 256, 384, 512'],
            ],
        ],

        'Penyulitan Homomorfik' => [
            '' => [
                'Exponential ElGamal Encryption' => ['myseal' => 'Approved', 'parameter' => null],
                'Paillier Encryption' => ['myseal' => 'Approved', 'parameter' => null],
                'Cheon-Kim-Kim-Song (CKKS) Homomorphic Encryption' => ['myseal' => 'Approved', 'parameter' => null],
            ],
            'Neutral (MySEAL 2.1)' => [
                'Brakerski-Fan-Vercauteren (BFV) Encryption' => ['myseal' => 'Neutral', 'parameter' => null],
                'Brakerski-Gentry-Vaikuntanathan (BGV) Encryption' => ['myseal' => 'Neutral', 'parameter' => null],
                "Chillotti-Gama-Georgieva-Izabachène (CGGI) Encryption" => ['myseal' => 'Neutral', 'parameter' => null],
                'Ducas-Micciancio (DM/FHEW) Encryption' => ['myseal' => 'Neutral', 'parameter' => null],
            ],
        ],

        'Kriptografi Ambang' => [
            'Tandatangan Ambang' => [
                'FROST (Ed25519, SHA-512)' => ['myseal' => 'Approved', 'parameter' => null],
                'FROST (Ed448, SHAKE256)' => ['myseal' => 'Approved', 'parameter' => null],
                'FROST (Ristretto255, SHA-512)' => ['myseal' => 'Approved', 'parameter' => null],
                'FROST (Secp256k1, SHA-256)' => ['myseal' => 'Approved', 'parameter' => null],
            ],
        ],

    ],

    /*
    | Pengelasan rasmi AKSA MySEAL 2.1. Susunan di sini ialah susunan
    | paparan; 'Approved' ialah nilai lalai dan tidak dilencanakan pada
    | borang supaya 104 baris Approved kekal bersih.
    */
    'myseal_kategori' => [
        'Approved' => ['label' => 'Approved', 'kelas' => 'text-bg-success'],
        'Neutral' => ['label' => 'Neutral', 'kelas' => 'text-bg-warning'],
        'Monitored' => ['label' => 'Monitored', 'kelas' => 'text-bg-danger'],
    ],

    // Algoritma yang tidak lagi disyorkan. Nama ini TIADA dalam katalog
    // AKSA MySEAL (Approved), jadi ia dipadankan dengan teks yang ditaip
    // pegawai dalam medan "Lain-lain" — lihat AnalisisInventori::padanan().
    'tidak_disyorkan' => ['3DES', 'DES', 'Blowfish', 'RC4', 'SHA-1', 'MD5'],

    // Algoritma berisiko terhadap ancaman pengkomputeran kuantum.
    // Dua kumpulan digabungkan di sini:
    //   - nama AKSA MySEAL, dipadankan dengan checkbox katalog;
    //   - nama klasik/legasi, dipadankan dengan teks medan "Lain-lain".
    //
    // NOTA: algoritma klasik AKSA MySEAL yang lain (RSA-KEM, RSA-OAEP,
    // ACE-KEM, ECIES-KEM, FACE-KEM, PSEC-KEM, varian DH/ECDH, BLS, ECSDSA,
    // SM2) BELUM disenaraikan — pengelasan risiko ialah keputusan dasar,
    // bukan andaian teknikal.
    'risiko_kuantum' => [
        'RSA-PSS (RSA-Probabilistic Signature Scheme)',
        'Elliptic Curve Digital Signature Algorithm (ECDSA)',
        'RSA', 'ElGamal', 'DH', 'ECDH', 'ECDHE', 'RSASSA-PKCS1-v1_5', 'DSA',
    ],

    'kategori_profil' => ['Sistem/Aplikasi', 'Pelayan', 'Peranti', 'Lain-lain'],

    'tindakan_susulan' => [
        ['tindakan' => 'Mengemas kini Jadual 0–2 berdasarkan pembetulan dan pengesahan yang telah dilaksanakan serta mengemukakan semula data yang dikemas kini melalui saluran yang ditetapkan.', 'kategori' => 'Pengemaskinian inventori'],
        ['tindakan' => 'Memastikan setiap rekod dalam Jadual 0 mempunyai pemetaan yang jelas dan konsisten kepada rekod berkaitan dalam SBOM dan CBOM melalui pengenal unik sistem atau aset.', 'kategori' => 'Pemetaan dan kesinambungan data'],
        ['tindakan' => 'Mengelaskan setiap rekod sistem dan aset mengikut kategori yang bersesuaian dan konsisten, seperti sistem/aplikasi, pelayan, peranti, atau kategori lain yang berkaitan.', 'kategori' => 'Pengelasan sistem dan aset'],
        ['tindakan' => 'Melengkapkan maklumat asas sistem, aset dan komponen yang belum dinyatakan atau tidak jelas, termasuk nama, jenis, produk, vendor dan versi, mengikut medan yang berkaitan dalam Jadual 0–2.', 'kategori' => 'Kelengkapan maklumat sistem, aset dan komponen'],
        ['tindakan' => 'Melengkapkan atau membetulkan maklumat kriptografi yang belum dinyatakan atau tidak jelas dalam CBOM, termasuk algoritma, protokol kriptografi serta pustaka atau modul kriptografi, mengikut maklumat yang diperlukan dalam buku kerja.', 'kategori' => 'Kelengkapan maklumat kriptografi'],
        ['tindakan' => 'Melengkapkan maklumat berkaitan data, termasuk kategori, klasifikasi dan tempoh penyimpanan, bagi sistem atau aset yang berkenaan.', 'kategori' => 'Kelengkapan maklumat data'],
        ['tindakan' => 'Merekodkan algoritma, protokol, pustaka atau modul kriptografi secara berasingan bagi sistem atau aset yang menggunakan lebih daripada satu mekanisme kriptografi.', 'kategori' => 'Struktur dan penyediaan data'],
        ['tindakan' => 'Mengesahkan maklumat yang tidak lengkap, tidak jelas atau tidak konsisten bersama pemilik sistem, pegawai teknikal atau vendor yang berkaitan, mengikut keperluan.', 'kategori' => 'Pengesahan maklumat'],
    ],

    // Format kod rujukan laporan mengikut templat rasmi:
    //     R-LP-MIG-4-****-V*.*   (cth. R-LP-MIG-4-0001-V1.0)
    // Segmen tengah (****) menerima huruf/digit tanpa had panjang; segmen
    // versi menerima nombor major.minor. `corak` disimpan TANPA pembatas
    // supaya nilai yang sama boleh digunakan semula sebagai atribut
    // `pattern` HTML pada borang dan sebagai peraturan `regex:` Laravel.
    'kod_rujukan' => [
        'corak' => 'R-LP-MIG-4-[A-Za-z0-9]+-V\d+\.\d+',
        'format' => 'R-LP-MIG-4-****-V*.*',
        'contoh' => 'R-LP-MIG-4-0001-V1.0',
    ],

    // Status laporan — DUA pilihan sahaja mengikut templat rasmi. Nilai lama
    // ('Muktamad', 'Muktamad dengan Catatan') telah dipetakan kepada 'Selesai'
    // oleh migrasi 2026_08_26_000001_selaraskan_status_laporan_analisis.
    // Pilihan pertama ialah nilai lalai bagi simpanan muktamad dan draf baharu.
    'status_laporan' => ['Selesai', 'Memerlukan Tindakan Susulan'],

    // Status kebolehgunaan bagi setiap Jadual 0-2 — DUA pilihan sahaja
    // mengikut templat rasmi. Nilai lama ('Boleh Digunakan', 'Boleh Digunakan
    // dengan Catatan', 'Memerlukan Pengesahan', 'Tidak Boleh Digunakan')
    // telah dipetakan oleh migrasi
    // 2026_08_26_000002_selaraskan_status_kebolehgunaan_data.
    // Pilihan pertama ialah nilai lalai borang.
    'kebolehgunaan_data' => ['Lengkap', 'Tidak Lengkap'],

    // Perkataan bilangan bagi ayat Catatan dalam seksyen Status Penerimaan
    // dan Kebolehgunaan Data ("Maklumat diperoleh daripada satu (1) fail
    // berikut"). Bilangan di luar senarai ini jatuh kembali kepada digit
    // sahaja, jadi senarai pendek memadai.
    'bilangan_perkataan' => [
        1 => 'satu', 2 => 'dua', 3 => 'tiga', 4 => 'empat', 5 => 'lima',
        6 => 'enam', 7 => 'tujuh', 8 => 'lapan', 9 => 'sembilan', 10 => 'sepuluh',
    ],

    // Klasifikasi keselamatan dokumen, dipaparkan dalam jadual maklumat
    // laporan. Disimpan di sini kerana ia satu nilai dasar peringkat sistem,
    // bukan input per-entiti: setiap Laporan Analisis Inventori Kriptografi
    // dikeluarkan pada klasifikasi yang sama. Menyimpannya sebagai config
    // (bukan heks dalam Blade) mengelakkan nilai ini terpesong antara
    // pratonton skrin dan PDF.
    'klasifikasi_laporan' => 'RAHSIA',

    /*
    | Baris pengesahan laporan mengikut templat rasmi: dua pegawai PTPKM,
    | diikuti dua ruang kosong untuk pihak NACSA menandatangani.
    |
    | `sumber` menandakan baris yang namanya diambil daripada aliran kerja
    | sebenar dan bukan daripada config: 'disahkan' bermaksud pegawai yang
    | benar-benar menekan "Sahkan" (laporan_semakan.disahkan_oleh). Nama dalam
    | config hanya menjadi sandaran sebelum pengesahan dibuat.
    |
    | Baris tanpa `sumber` tiada langkah aliran kerja yang sepadan, jadi
    | namanya kekal sebagai teks tetap atau dibiarkan kosong untuk ditulis
    | tangan pada salinan bercetak.
    */
    'pengesahan_laporan' => [
        [
            'peranan' => 'Disahkan oleh: Ketua Bahagian Migrasi PQC, PTPKM',
            'nama' => 'Dr. Isma Norshahila Binti Mohammad Shah',
            'sumber' => 'disahkan',
        ],
        [
            'peranan' => 'Diluluskan oleh: Timbalan Pengarah 2, PTPKM',
            'nama' => 'Hazlin Binti Abdul Rani',
        ],
        ['peranan' => 'Diluluskan oleh:', 'nama' => ''],
    ],
];
