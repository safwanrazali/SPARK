<?php

namespace Database\Seeders;

use App\Services\AkaunPentadbirLalai;
use Illuminate\Database\Seeder;

/**
 * FASA 13 — akaun pentadbir awal.
 *
 * Sistem mesti mempunyai satu akaun Pentadbir Sistem selepas pemasangan,
 * kerana hanya peranan itu boleh menambah pengguna lain.
 *
 * Peraturan keselamatan (dikuatkuasakan oleh AkaunPentadbirLalai):
 * - Kata laluan TIDAK ditulis di dalam kod; ia dibaca daripada .env supaya
 *   setiap pemasangan mempunyai kelayakan tersendiri.
 * - Pada pelayan pengeluaran, ADMIN_PASSWORD wajib ditetapkan.
 * - Jika akaun pentadbir telah wujud, kata laluannya TIDAK ditetapkan semula.
 *   Menjalankan semula seeder tidak boleh memulangkan kata laluan lama; guna
 *   `php artisan pentadbir:sedia --tetap-semula` untuk itu.
 */
class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        $hasil = app(AkaunPentadbirLalai::class)->pastikan();

        $username = $hasil['user']->username;

        if (! $hasil['dicipta']) {
            $this->command?->info(
                "Akaun pentadbir [{$username}] telah wujud. Kata laluan tidak diubah."
            );

            return;
        }

        // Kata laluan yang dijana hanya dipaparkan sekali di sini.
        if (config('pentadbir.password') === null || config('pentadbir.password') === '') {
            $this->command?->warn(
                "Kata laluan pentadbir [{$username}] dijana: {$hasil['kataLaluan']}"
            );
            $this->command?->warn(
                'Simpan sekarang — ia tidak akan dipaparkan semula. Tukar selepas log masuk pertama.'
            );
        }
    }
}
