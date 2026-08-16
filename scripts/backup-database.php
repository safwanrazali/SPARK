<?php

/*
|--------------------------------------------------------------------------
| SANDARAN PANGKALAN DATA — FASA 13
| Sistem Pemantauan & Pelaporan Analisis Data Migrasi PQC
|--------------------------------------------------------------------------
|
| Membuat salinan sandaran pangkalan data SQLite yang konsisten TANPA perlu
| menghentikan aplikasi. Skrip ini sengaja tidak bergantung kepada Laravel
| supaya ia tetap boleh dijalankan walaupun aplikasi gagal dimulakan.
|
| Penggunaan:
|   php scripts/backup-database.php
|   php scripts/backup-database.php --target=D:\sandaran
|   php scripts/backup-database.php --keep=14
|
| Pilihan:
|   --database=PATH   Fail pangkalan data sumber (lalai: daripada .env)
|   --target=PATH     Fail atau direktori destinasi (lalai: storage/backups)
|   --keep=N          Simpan N sandaran terbaharu sahaja; yang lama dipadam
|   --quiet           Kurangkan output
|
| Kod keluar: 0 = berjaya, 1 = gagal.
|
*/

declare(strict_types=1);

$asas = dirname(__DIR__);

require_once $asas.'/scripts/lib/sqlite-support.php';

$pilihan = huraikanArgumen($argv);
$senyap = isset($pilihan['quiet']);

try {
    $sumber = $pilihan['database'] ?? failPangkalanData($asas);

    if (! is_file($sumber)) {
        throw new RuntimeException("Fail pangkalan data tidak dijumpai: {$sumber}");
    }

    lapor($senyap, 'Sumber       : '.$sumber);
    lapor($senyap, 'Saiz         : '.saizBolehBaca((int) filesize($sumber)));

    // 1 ── Sahkan integriti sumber SEBELUM menyandarkannya. Menyandarkan
    //      pangkalan data yang telah rosak hanya menyalin kerosakan.
    lapor($senyap, 'Memeriksa integriti sumber...');
    sahkanIntegriti($sumber);

    // 2 ── Tentukan destinasi.
    $destinasi = tentukanDestinasi($pilihan['target'] ?? $asas.'/storage/backups');

    // 3 ── Salin. VACUUM INTO menghasilkan salinan yang konsisten walaupun
    //      terdapat penulisan serentak, dan turut memampatkan fail.
    lapor($senyap, 'Menyandar    : '.$destinasi);
    salinPangkalanData($sumber, $destinasi);

    // 4 ── Sahkan hasil sandaran boleh dibuka dan tidak rosak.
    lapor($senyap, 'Mengesahkan hasil sandaran...');
    sahkanIntegriti($destinasi);

    $jadual = bilanganJadual($destinasi);

    if ($jadual === 0) {
        throw new RuntimeException('Sandaran terhasil tetapi tidak mengandungi sebarang jadual.');
    }

    lapor($senyap, 'Jadual       : '.$jadual);
    lapor($senyap, 'Saiz         : '.saizBolehBaca((int) filesize($destinasi)));

    // 5 ── Simpanan berkala (jika diminta).
    if (isset($pilihan['keep'])) {
        $dibuang = pangkasSandaranLama(dirname($destinasi), max(1, (int) $pilihan['keep']));

        foreach ($dibuang as $fail) {
            lapor($senyap, 'Dipadam      : '.basename($fail));
        }
    }

    lapor($senyap, '');
    lapor($senyap, 'SANDARAN BERJAYA: '.$destinasi);

    exit(0);
} catch (Throwable $e) {
    fwrite(STDERR, 'SANDARAN GAGAL: '.$e->getMessage().PHP_EOL);

    exit(1);
}
