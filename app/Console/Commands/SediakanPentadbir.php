<?php

namespace App\Console\Commands;

use App\Services\AkaunPentadbirLalai;
use Illuminate\Console\Command;

/**
 * Pemulihan akaun pentadbir lalai.
 *
 * `php artisan db:seed` sengaja tidak pernah menetapkan semula kata laluan
 * pentadbir sedia ada. Arahan ini menyediakan satu-satunya laluan pemulihan
 * apabila kata laluan itu hilang — tanpanya, pemasangan yang kehilangan
 * kelayakan pentadbirnya tidak lagi boleh menambah pengguna.
 */
class SediakanPentadbir extends Command
{
    protected $signature = 'pentadbir:sedia
        {--kata-laluan= : Tetapkan kata laluan ini (jika tidak: ikut ADMIN_PASSWORD dalam .env)}
        {--tetap-semula : Tetapkan semula kata laluan akaun pentadbir yang sedia ada}';

    protected $description = 'Pastikan akaun pentadbir lalai wujud supaya pengguna lain boleh ditambah.';

    public function handle(AkaunPentadbirLalai $akaun): int
    {
        $kataLaluan = $this->option('kata-laluan');
        $tetapSemula = (bool) $this->option('tetap-semula');

        $hasil = $akaun->pastikan(
            is_string($kataLaluan) && $kataLaluan !== '' ? $kataLaluan : null,
            $tetapSemula,
        );

        $pengguna = $hasil['user'];

        if ($hasil['dicipta']) {
            $this->info("Akaun pentadbir [{$pengguna->username}] telah dicipta.");
        } elseif ($hasil['kataLaluan'] !== null) {
            $this->info("Kata laluan akaun pentadbir [{$pengguna->username}] telah ditetapkan semula.");
        } else {
            $this->info("Akaun pentadbir [{$pengguna->username}] telah wujud dan kekal aktif.");
            $this->line('Kata laluan tidak diubah. Guna --tetap-semula jika ia perlu dipulihkan.');
        }

        // Kata laluan dipaparkan sekali sahaja; ia tidak disimpan di mana-mana
        // dalam bentuk yang boleh dibaca semula.
        if ($hasil['kataLaluan'] !== null) {
            $this->newLine();
            $this->warn("Nama pengguna : {$pengguna->username}");
            $this->warn("Kata laluan   : {$hasil['kataLaluan']}");
            $this->newLine();
            $this->warn('Simpan sekarang — ia tidak akan dipaparkan semula. Tukar selepas log masuk pertama.');
        }

        return self::SUCCESS;
    }
}
