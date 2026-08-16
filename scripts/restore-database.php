<?php

/*
|--------------------------------------------------------------------------
| PEMULIHAN PANGKALAN DATA — FASA 13
| Sistem Pemantauan & Pelaporan Analisis Data Migrasi PQC
|--------------------------------------------------------------------------
|
| Memulihkan pangkalan data daripada fail sandaran.
|
| Perlindungan yang dikuatkuasakan:
| 1. Integriti fail sandaran disahkan SEBELUM apa-apa ditulis.
| 2. Pangkalan data semasa disalin dahulu sebagai salinan keselamatan,
|    supaya pemulihan yang silap masih boleh dipatah balik.
| 3. Pengesahan interaktif diperlukan melainkan --yes diberikan.
| 4. Hasil pemulihan disahkan semula sebelum skrip melaporkan kejayaan.
|
| Penggunaan:
|   php scripts/restore-database.php --from=storage/backups/backup-20260817-090000.sqlite
|   php scripts/restore-database.php --from=... --yes
|
| Pilihan:
|   --from=PATH       WAJIB. Fail sandaran yang hendak dipulihkan
|   --database=PATH   Pangkalan data destinasi (lalai: daripada .env)
|   --yes             Teruskan tanpa pengesahan interaktif
|   --no-safety-copy  Jangan salin pangkalan data semasa (tidak disyorkan)
|
| SELEPAS PEMULIHAN, jalankan:
|   php artisan migrate --force      (jika sandaran lebih lama daripada kod)
|   php artisan config:clear && php artisan cache:clear
|
| Kod keluar: 0 = berjaya, 1 = gagal/dibatalkan.
|
*/

declare(strict_types=1);

$asas = dirname(__DIR__);

require_once $asas.'/scripts/lib/sqlite-support.php';

$pilihan = huraikanArgumen($argv);

try {
    $sandaran = $pilihan['from'] ?? null;

    if (! is_string($sandaran) || $sandaran === '') {
        throw new RuntimeException('Pilihan --from=PATH wajib diberikan.');
    }

    if (! is_file($sandaran)) {
        throw new RuntimeException("Fail sandaran tidak dijumpai: {$sandaran}");
    }

    $destinasi = $pilihan['database'] ?? failPangkalanData($asas);

    echo 'Sandaran     : '.$sandaran.PHP_EOL;
    echo 'Saiz         : '.saizBolehBaca((int) filesize($sandaran)).PHP_EOL;
    echo 'Destinasi    : '.$destinasi.PHP_EOL;
    echo PHP_EOL;

    // 1 ── Jangan sekali-kali memulihkan fail yang rosak.
    echo 'Memeriksa integriti sandaran...'.PHP_EOL;
    sahkanIntegriti($sandaran);

    $jadual = bilanganJadual($sandaran);

    if ($jadual === 0) {
        throw new RuntimeException('Fail sandaran tidak mengandungi sebarang jadual.');
    }

    echo 'Jadual dalam sandaran: '.$jadual.PHP_EOL.PHP_EOL;

    // 2 ── Pengesahan.
    if (! isset($pilihan['yes'])) {
        echo 'AMARAN: pangkalan data destinasi akan DIGANTIKAN sepenuhnya.'.PHP_EOL;
        echo 'Taip "PULIH" untuk meneruskan: ';

        $jawapan = trim((string) fgets(STDIN));

        if ($jawapan !== 'PULIH') {
            echo 'Dibatalkan. Tiada perubahan dibuat.'.PHP_EOL;

            exit(1);
        }
    }

    // 3 ── Salinan keselamatan pangkalan data semasa.
    if (is_file($destinasi) && ! isset($pilihan['no-safety-copy'])) {
        $salinan = dirname($destinasi).'/pra-pemulihan-'.date('Ymd-His').'.sqlite';

        if (! copy($destinasi, $salinan)) {
            throw new RuntimeException('Gagal membuat salinan keselamatan. Pemulihan dibatalkan.');
        }

        echo 'Salinan keselamatan: '.$salinan.PHP_EOL;
    }

    // 4 ── Pulihkan.
    pastikanDirektori(dirname($destinasi));

    if (! copy($sandaran, $destinasi)) {
        throw new RuntimeException('Gagal menyalin fail sandaran ke destinasi.');
    }

    // Fail sisa mod WAL daripada pangkalan data lama akan bercanggah dengan
    // fail yang baharu dipulihkan.
    foreach (['-wal', '-shm'] as $akhiran) {
        if (is_file($destinasi.$akhiran)) {
            @unlink($destinasi.$akhiran);
        }
    }

    // 5 ── Sahkan hasil pemulihan.
    echo 'Mengesahkan pangkalan data yang dipulihkan...'.PHP_EOL;
    sahkanIntegriti($destinasi);

    if (bilanganJadual($destinasi) !== $jadual) {
        throw new RuntimeException('Bilangan jadual selepas pemulihan tidak sepadan dengan sandaran.');
    }

    echo PHP_EOL;
    echo 'PEMULIHAN BERJAYA.'.PHP_EOL;
    echo PHP_EOL;
    echo 'Langkah seterusnya:'.PHP_EOL;
    echo '  php artisan migrate --force'.PHP_EOL;
    echo '  php artisan config:clear'.PHP_EOL;
    echo '  php artisan cache:clear'.PHP_EOL;

    exit(0);
} catch (Throwable $e) {
    fwrite(STDERR, 'PEMULIHAN GAGAL: '.$e->getMessage().PHP_EOL);

    exit(1);
}
