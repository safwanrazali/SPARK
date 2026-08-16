<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

/**
 * FASA 13 — semaian pemasangan.
 *
 * Hanya akaun pentadbir awal disemai. Akaun ujian rangka kerja (kata laluan
 * lalai "password") telah dibuang kerana `php artisan db:seed` turut
 * dijalankan semasa pemasangan pelayan — akaun ujian tidak boleh wujud di
 * sana. Data ujian dijana oleh factory di dalam suite ujian sahaja.
 */
class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $this->call(AdminUserSeeder::class);
    }
}
