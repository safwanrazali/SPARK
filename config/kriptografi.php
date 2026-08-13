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

    'kategori_algoritma' => [
        'Simetrik Blok' => ['AES', 'Camellia', 'CLEFIA', 'SEED', '3DES', 'Blowfish'],
        'Simetrik Alir' => ['ChaCha20', 'RC4'],
        'Asimetrik (Penyulitan)' => ['RSA', 'ElGamal'],
        'Fungsi Cincang' => ['SHA-2', 'SHA-3', 'SM3', 'SHA-1', 'MD5'],
        'Penjanaan / Persetujuan Kunci' => ['DH', 'ECDH', 'ECDHE'],
        'Skim Tandatangan Digital' => ['RSASSA-PSS', 'RSASSA-PKCS1-v1_5', 'ECDSA', 'DSA'],
        'Kod Pengesahan Mesej (MAC)' => ['HMAC', 'CMAC'],
        'Fungsi Derivasi Kunci (KDF)' => ['PBKDF2', 'HKDF'],
        'Penyulitan Disahkan (AEAD)' => ['AES-GCM', 'ChaCha20-Poly1305'],
        'Penjana Bit Rawak Deterministik (DRBG)' => ['Hash_DRBG', 'HMAC_DRBG', 'CTR_DRBG'],
    ],

    // Algoritma yang tidak lagi disyorkan.
    'tidak_disyorkan' => ['3DES', 'Blowfish', 'RC4', 'SHA-1', 'MD5'],

    // Algoritma berisiko terhadap ancaman pengkomputeran kuantum.
    'risiko_kuantum' => [
        'RSA', 'ElGamal', 'DH', 'ECDH', 'ECDHE',
        'RSASSA-PSS', 'RSASSA-PKCS1-v1_5', 'ECDSA', 'DSA',
    ],

    'kategori_profil' => ['Sistem/Aplikasi', 'Pelayan', 'Peranti', 'Lain-lain'],

    'ringkasan_data' => [
        'lengkap' => 'Berdasarkan semakan yang dilaksanakan, data inventori kriptografi yang diterima didapati lengkap, konsisten dan mempunyai kesinambungan yang mencukupi untuk digunakan bagi tujuan analisis.',
        'catatan' => 'Berdasarkan semakan yang dilaksanakan, data inventori kriptografi yang diterima boleh digunakan bagi tujuan analisis. Walau bagaimanapun, terdapat beberapa isu yang memerlukan tindakan susulan oleh entiti sebelum pelaksanaan fasa migrasi PQC yang seterusnya dapat diteruskan.',
        'pengesahan' => 'Berdasarkan semakan yang dilaksanakan, terdapat maklumat tertentu yang memerlukan pengesahan lanjut daripada entiti sebelum analisis dapat dimuktamadkan.',
        'terhad' => 'Semakan mendapati data yang diterima mempunyai keterbatasan yang ketara dari segi kelengkapan, ketepatan, konsistensi atau kesinambungan. Analisis hanya dapat dilaksanakan berdasarkan maklumat yang tersedia dan dapatan hendaklah dianggap sebagai penilaian awal serta tertakluk kepada pengesahan lanjut.',
    ],

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

    'kesimpulan' => [
        'umum' => [
            'nama' => 'Kesimpulan Umum',
            'teks' => 'Hasil analisis menunjukkan bahawa inventori kriptografi yang diterima masih memerlukan penambahbaikan dari segi kelengkapan, ketepatan, konsistensi dan kesinambungan maklumat. Keadaan ini mengehadkan kebolehlihatan terhadap penggunaan kriptografi dalam sistem dan aset serta menyukarkan pengenalpastian algoritma yang tidak lagi disyorkan atau berisiko terhadap ancaman kuantum, pemetaan antara sistem atau aset dengan komponen berkaitan, serta penentuan keutamaan bagi pelaksanaan migrasi PQC. Sehubungan itu, inventori hendaklah dikemas kini dan disahkan bagi menyokong analisis risiko serta perancangan migrasi PQC yang lebih tepat.',
        ],
        'ot' => [
            'nama' => 'Sistem Teknologi Operasi',
            'teks' => 'Hasil analisis menunjukkan bahawa maklumat kriptografi bagi sistem Teknologi Operasi (Operational Technology, OT) adalah terhad dan tidak dapat dikenal pasti secara menyeluruh. Keadaan ini dipengaruhi oleh keterbatasan dokumentasi teknikal, kebergantungan kepada vendor dan maklumat konfigurasi kriptografi yang tidak dinyatakan secara terperinci. Saluran komunikasi antara persekitaran OT dan teknologi maklumat turut memerlukan perhatian kerana berpotensi menggunakan mekanisme kriptografi yang perlu dinilai dalam konteks migrasi PQC. Oleh itu, pengesahan lanjut bersama pemilik sistem, pegawai teknikal dan vendor berkaitan diperlukan.',
        ],
        'lapuk' => [
            'nama' => 'Penggunaan Algoritma Kriptografi yang Tidak Lagi Selamat',
            // Teks dijana secara dinamik dalam LaporanController — nama algoritma
            // diisi automatik daripada pilihan yang tidak lagi disyorkan.
            'teks' => null,
        ],
        'legasi' => [
            'nama' => 'Sistem Legasi',
            'teks' => 'Hasil analisis menunjukkan bahawa sistem legasi menghadapi kekangan dari segi dokumentasi teknikal, sokongan naik taraf, kelincahan kriptografi dan keupayaan untuk menukar mekanisme kriptografi sedia ada. Dalam keadaan tertentu, sistem legasi turut menggunakan algoritma, protokol atau konfigurasi yang tidak lagi disyorkan. Kekangan ini meningkatkan kerumitan penilaian dan perancangan migrasi PQC. Oleh itu, penilaian lanjut diperlukan bagi menentukan pendekatan mitigasi, naik taraf, penggantian atau migrasi yang bersesuaian.',
        ],
        'legasi_ot' => [
            'nama' => 'Sistem Legasi dan Teknologi Operasi',
            'teks' => 'Hasil analisis menunjukkan bahawa sistem legasi dan sistem Teknologi Operasi merupakan antara aset yang paling mencabar untuk dinilai dari perspektif kriptografi. Kekangan seperti dokumentasi yang tidak lengkap, kebergantungan kepada vendor, sokongan naik taraf yang terhad dan kelincahan kriptografi yang rendah menyukarkan pengenalpastian algoritma, protokol serta komponen kriptografi yang digunakan. Keadaan ini mengehadkan kebolehlihatan kriptografi dan boleh menjejaskan ketepatan penentuan keutamaan aset bagi perancangan migrasi PQC. Sehubungan itu, penilaian lanjut bersama pemilik sistem, pegawai teknikal dan vendor berkaitan diperlukan.',
        ],
    ],

    'pengesahan_laporan' => [
        ['peranan' => 'Disahkan oleh: Ketua Bahagian Migrasi PQC, PTPKM', 'nama' => 'Dr. Isma Norshahila Binti Mohammad Shah'],
        ['peranan' => 'Diluluskan oleh: Timbalan Pengarah 2, PTPKM', 'nama' => 'Hazlin Binti Abdul Rani'],
        ['peranan' => 'Diluluskan oleh: [Jawatan Pegawai], NACSA', 'nama' => ''],
    ],
];
