<?php

/*
|--------------------------------------------------------------------------
| Fungsi sokongan sandaran / pemulihan SQLite — FASA 13
|--------------------------------------------------------------------------
|
| Dikongsi oleh scripts/backup-database.php dan scripts/restore-database.php.
| Tiada kebergantungan kepada Laravel: skrip pemulihan mesti boleh dijalankan
| walaupun aplikasi tidak dapat dimulakan.
|
*/

declare(strict_types=1);

/**
 * Huraikan argumen baris arahan bergaya --kunci=nilai dan --bendera.
 *
 * @param  array<int, string>  $argv
 * @return array<string, string|bool>
 */
function huraikanArgumen(array $argv): array
{
    $pilihan = [];

    foreach (array_slice($argv, 1) as $hujah) {
        if (! str_starts_with($hujah, '--')) {
            continue;
        }

        $hujah = substr($hujah, 2);

        if (str_contains($hujah, '=')) {
            [$kunci, $nilai] = explode('=', $hujah, 2);
            $pilihan[$kunci] = trim($nilai, " \t\"'");

            continue;
        }

        $pilihan[$hujah] = true;
    }

    return $pilihan;
}

/**
 * Baca satu nilai daripada fail .env tanpa memuatkan Laravel.
 */
function nilaiEnv(string $asas, string $kunci): ?string
{
    $fail = $asas.'/.env';

    if (! is_file($fail)) {
        return null;
    }

    foreach (file($fail, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $baris) {
        $baris = trim($baris);

        if ($baris === '' || str_starts_with($baris, '#') || ! str_contains($baris, '=')) {
            continue;
        }

        [$nama, $nilai] = explode('=', $baris, 2);

        if (trim($nama) === $kunci) {
            return trim(trim($nilai), "\"'");
        }
    }

    return null;
}

/**
 * Laluan fail pangkalan data mengikut konfigurasi aplikasi.
 */
function failPangkalanData(string $asas): string
{
    $pemacu = nilaiEnv($asas, 'DB_CONNECTION') ?? 'sqlite';

    if ($pemacu !== 'sqlite') {
        throw new RuntimeException(
            "Skrip ini hanya menyokong SQLite. DB_CONNECTION semasa: {$pemacu}. ".
            'Gunakan alat sandaran rasmi enjin pangkalan data tersebut.'
        );
    }

    $laluan = nilaiEnv($asas, 'DB_DATABASE');

    if ($laluan === null || $laluan === '') {
        return $asas.'/database/database.sqlite';
    }

    // Laluan relatif ditafsirkan daripada akar projek, sama seperti Laravel.
    return preg_match('/^([a-zA-Z]:[\\\\\/]|\/)/', $laluan) === 1
        ? $laluan
        : $asas.'/'.ltrim($laluan, '/\\');
}

function sambungan(string $fail): PDO
{
    $pdo = new PDO('sqlite:'.$fail);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    return $pdo;
}

/**
 * Jalankan PRAGMA integrity_check dan gagalkan jika tidak "ok".
 */
function sahkanIntegriti(string $fail): void
{
    $hasil = sambungan($fail)->query('PRAGMA integrity_check')->fetchColumn();

    if (strtolower((string) $hasil) !== 'ok') {
        throw new RuntimeException("Pemeriksaan integriti gagal bagi {$fail}: {$hasil}");
    }
}

function bilanganJadual(string $fail): int
{
    return (int) sambungan($fail)
        ->query("SELECT COUNT(*) FROM sqlite_master WHERE type = 'table' AND name NOT LIKE 'sqlite_%'")
        ->fetchColumn();
}

/**
 * Tentukan laluan fail sandaran. Direktori akan diberi nama bercap masa.
 */
function tentukanDestinasi(string $target): string
{
    $adalahDirektori = is_dir($target)
        || str_ends_with($target, '/')
        || str_ends_with($target, '\\')
        || ! str_contains(basename($target), '.');

    if (! $adalahDirektori) {
        pastikanDirektori(dirname($target));

        return $target;
    }

    pastikanDirektori($target);

    return rtrim($target, '/\\').'/backup-'.date('Ymd-His').'.sqlite';
}

function pastikanDirektori(string $direktori): void
{
    if (is_dir($direktori)) {
        return;
    }

    if (! mkdir($direktori, 0755, true) && ! is_dir($direktori)) {
        throw new RuntimeException("Gagal mencipta direktori: {$direktori}");
    }
}

/**
 * Salinan konsisten. VACUUM INTO (SQLite 3.27+) selamat digunakan semasa
 * aplikasi masih berjalan; salinan fail biasa hanya digunakan sebagai
 * langkah ganti pada versi SQLite yang lebih lama.
 */
function salinPangkalanData(string $sumber, string $destinasi): void
{
    if (is_file($destinasi)) {
        throw new RuntimeException("Fail destinasi telah wujud: {$destinasi}");
    }

    $pdo = sambungan($sumber);
    $versi = (string) $pdo->query('SELECT sqlite_version()')->fetchColumn();

    if (version_compare($versi, '3.27.0', '>=')) {
        $pdo->exec("VACUUM INTO '".str_replace("'", "''", $destinasi)."'");

        return;
    }

    // Langkah ganti: kunci penulisan, salin, lepaskan.
    $pdo->exec('BEGIN IMMEDIATE');

    try {
        if (! copy($sumber, $destinasi)) {
            throw new RuntimeException("Gagal menyalin {$sumber} ke {$destinasi}");
        }
    } finally {
        $pdo->exec('COMMIT');
    }
}

/**
 * Kekalkan hanya N sandaran terbaharu dalam direktori.
 *
 * @return array<int, string> fail yang dipadam
 */
function pangkasSandaranLama(string $direktori, int $simpan): array
{
    $fail = glob(rtrim($direktori, '/\\').'/backup-*.sqlite') ?: [];

    if (count($fail) <= $simpan) {
        return [];
    }

    // Nama fail bercap masa: susunan abjad = susunan masa.
    sort($fail);

    $dibuang = array_slice($fail, 0, count($fail) - $simpan);

    foreach ($dibuang as $satu) {
        @unlink($satu);
    }

    return $dibuang;
}

function saizBolehBaca(int $bait): string
{
    $unit = ['B', 'KB', 'MB', 'GB'];
    $i = 0;

    while ($bait >= 1024 && $i < count($unit) - 1) {
        $bait = (int) round($bait / 1024);
        $i++;
    }

    return $bait.' '.$unit[$i];
}

function lapor(bool $senyap, string $mesej): void
{
    if (! $senyap) {
        echo $mesej.PHP_EOL;
    }
}
