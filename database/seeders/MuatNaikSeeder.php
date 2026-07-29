<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class MuatNaikSeeder extends Seeder
{
    public function run(): void
    {
        for ($i = 1; $i <= 10; $i++) {

            MuatNaik::create([
                'nama_fail' => "MasterTable_$i.xlsx",
                'lokasi_fail' => "uploads/file_$i.xlsx",
                'status' => 'Berjaya',
                'jumlah_rekod' => rand(50, 500),
            ]);
        }
    }
}
