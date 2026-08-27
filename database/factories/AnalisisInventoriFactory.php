<?php

namespace Database\Factories;

use App\Models\AnalisisInventori;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AnalisisInventori>
 */
class AnalisisInventoriFactory extends Factory
{
    protected $model = AnalisisInventori::class;

    public function definition(): array
    {
        return [
            'sector_code' => '001',
            'sector_name' => 'Kerajaan',
            'agency_code' => fake()->unique()->bothify('A######'),
            'agency_name' => fake()->company(),
            'tarikh_laporan' => now()->toDateString(),
            // Mengikut format rasmi R-LP-MIG-4-****-V*.* supaya rekod kilang
            // lulus pengesahan AnalisisInventoriController@simpan.
            'kod_rujukan' => 'R-LP-MIG-4-'.fake()->unique()->numerify('####').'-V1.0',
            'status_laporan' => 'Selesai',
            // Struktur mesti sepadan dengan yang ditulis oleh
            // AnalisisInventoriController@simpan supaya templat laporan
            // boleh dirender dalam ujian.
            'data' => [
                'data_status' => [
                    'j0' => ['kebolehgunaan' => 'Lengkap', 'nota' => ''],
                    'j1' => ['kebolehgunaan' => 'Lengkap', 'nota' => ''],
                    'j2' => ['kebolehgunaan' => 'Tidak Lengkap', 'nota' => ''],
                ],
                'profil' => [
                    'Sistem/Aplikasi' => ['jumlah' => 5, 'nota' => ''],
                ],
                'algoritma' => [
                    'Simetri|AES-256' => ['bilangan' => '5', 'nota' => ''],
                    'Hash|SHA-256' => ['bilangan' => '3', 'nota' => ''],
                ],
                'algoritma_lain' => '',
                'protokol' => [],
                'pustaka' => [],
                'vendor' => [],
                'tindakan' => [],
                'tindakan_lain' => '',
                'kesimpulan' => [],
                'kesimpulan_lain' => '',
            ],
            'selesai' => true,
            'user_id' => User::factory(),
        ];
    }
}
