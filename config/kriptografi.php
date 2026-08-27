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
    | Katalog algoritma mengikut AKSA MySEAL 2.1 (Approved).
    | Sumber: mykripto.cybersecurity.my — 12 kategori, 104 algoritma.
    |
    | Struktur: kategori => sub-kumpulan => senarai algoritma.
    | Sub-kumpulan bernama '' bermakna kategori tersebut tiada sub-kumpulan
    | pada laman rasmi; borang tidak memaparkan tajuk kecil untuknya.
    |
    | PENTING — kunci tersimpan kekal berbentuk "Kategori|Algoritma" (DUA
    | bahagian). Sub-kumpulan ialah pengelompokan PAPARAN sahaja dan tidak
    | masuk ke dalam kunci; menambahnya akan memecahkan
    | AnalisisInventori::algoritmaLapuk() yang mengambil bahagian kedua.
    |
    | Katalog ini mengandungi algoritma DILULUSKAN sahaja. Algoritma lapuk
    | (3DES, RC4, MD5, SHA-1) dan klasik (RSA, DSA, ElGamal) tiada di sini —
    | ia direkodkan melalui medan "Lain-lain" pada borang. Oleh itu
    | AnalisisInventori::algoritmaLapuk() dan algoritmaKuantum() turut
    | mengimbas `algoritma_lain`, bukan kunci checkbox sahaja.
    */
    'kategori_algoritma' => [

        'Sifer Blok' => [
            'Tujuan Umum' => ['AES', 'Camellia', 'CLEFIA', 'SEED'],
            'Ringan' => ['HIGHT', 'PRESENT'],
            'Boleh Laras (Tweakable)' => ['Deoxys-TBC', 'Skinny', 'XTS-AES'],
        ],

        'Sifer Alir' => [
            '' => ['ChaCha20', 'HC', 'KCipher-2', 'MUGI', 'Rabbit'],
        ],

        'Fungsi Cincang Kriptografi' => [
            'Tujuan Umum' => ['SHA2', 'SHA3', 'SM3'],
            'Ringan' => ['PHOTON', 'SPONGENT'],
        ],

        'Penyulitan Asimetri' => [
            'Skim Penyulitan' => [
                'ACE-KEM', 'ECIES-KEM', 'FACE-KEM', 'PSEC-KEM', 'RSA-KEM', 'RSA-OAEP',
            ],
            'Skim Persetujuan Kunci' => [
                'DH Ephemeral-Ephemeral (C(2e,0s))',
                'DH Ephemeral-Static (C(1e,1s))',
                'DH Ephemeral-Static (C(1e,2s))',
                'DH Ephemeral-Static (C(2e,2s))',
                'ECDH Ephemeral-Ephemeral (C(2e,0s))',
                'ECDH Ephemeral-Static (C(1e,2s))',
                'ECDH Ephemeral-Static (C(2e,2s))',
                'ECDH Ephemeral-Static (C(1e,1s))',
            ],
            'Mekanisme Enkapsulasi Kunci Pasca-Kuantum' => [
                'mceliece 460896', 'mceliece 6688128', 'mceliece 6960119', 'mceliece 8192128',
                'FrodoKEM', 'eFrodoKEM', 'HQC', 'ML-KEM', 'NTRUHPS', 'NTRUHRSS',
            ],
        ],

        'Skim Tandatangan Digital' => [
            'Berasaskan Masalah Sukar Klasik' => [
                'BLS Signature Scheme',
                'Elliptic Curve Digital Signature Algorithm (ECDSA)',
                'Elliptic Curve Schnorr DSA (ECSDSA)',
                'RSA-PSS (RSA-Probabilistic Signature Scheme)',
                'ShangMi2 (SM2)',
            ],
            'Berasaskan Cincang Berkeadaan' => ['LMS', 'XMSS', 'XMSS^MT'],
            'Pasca-Kuantum' => ['ML-DSA', 'Falcon', 'SLH-DSA-SHA2', 'SLH-DSA-SHAKE'],
        ],

        'Penjana Nombor Perdana Kriptografi' => [
            'Algoritma Ujian Keperdanaan' => [
                'Elliptic Curve Primality Test',
                'Miller-Rabin Primality Test',
                'Probabilistic Lucas Primality Test',
                'Pocklington Primality Test',
            ],
        ],

        'Penjana Bit Rawak Deterministik (DRBG)' => [
            '' => ['AES-CTR-DRBG', 'HMAC-SHA2-DRBG', 'SHA2-DRBG'],
        ],

        'Kod Pengesahan Mesej (MAC)' => [
            '' => [
                'CMAC', 'GMAC', 'HMAC', 'KMAC', 'UMAC', 'XCBC-MAC',
                'Chaskey-12', 'LightMAC', 'MDx-MAC', 'MDx-MAC-Short',
                'Poly1305', "Tsudik's Keymode",
            ],
        ],

        'Fungsi Derivasi Kunci (KDF)' => [
            'KDF Umum' => [
                'One Step HASH-KDF-SHA', 'One Step HASH-KDF-SHA3',
                'One Step HMAC-KDF-SHA', 'One Step HMAC-KDF-SHA3',
                'One Step KMAC-KDF', 'Two Step HMAC-KDF-SHA',
                'Two Step HMAC-KDF-SHA3', 'Two Step AES-CMAC-KDF', 'KMAC-PRF-KDF',
            ],
            'KDF Berasaskan Kata Laluan' => [
                'Argon', 'bcrypt', 'PBKDF2-HMAC-SHA', 'PBKDF2-HMAC-SHA3', 'scrypt',
            ],
        ],

        'Penyulitan Disahkan (AE)' => [
            '' => [
                'ASCON-AEAD', 'ChaCha20-Poly1305', 'XChaCha20-Poly1305',
                'AES-CCM', 'GCM-AES-XPN', 'AES-GCM', 'AES-GCM-SIV',
                'Sophie Germain Counter Mode (SGCM)', 'AES-SIV-CMAC',
            ],
        ],

        'Penyulitan Homomorfik' => [
            '' => [
                'Exponential ElGamal Encryption',
                'Paillier Encryption',
                'Cheon-Kim-Kim-Song (CKKS) Homomorphic Encryption',
            ],
        ],

        'Kriptografi Ambang' => [
            'Tandatangan Ambang' => [
                'FROST (Ed25519, SHA-512)', 'FROST (Ed448, SHAKE256)',
                'FROST (Ristretto255, SHA-512)', 'FROST (Secp256k1, SHA-256)',
            ],
        ],

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

    'pengesahan_laporan' => [
        ['peranan' => 'Disahkan oleh: Ketua Bahagian Migrasi PQC, PTPKM', 'nama' => 'Dr. Isma Norshahila Binti Mohammad Shah'],
        ['peranan' => 'Diluluskan oleh: Timbalan Pengarah 2, PTPKM', 'nama' => 'Hazlin Binti Abdul Rani'],
        ['peranan' => 'Diluluskan oleh: [Jawatan Pegawai], NACSA', 'nama' => ''],
    ],
];
