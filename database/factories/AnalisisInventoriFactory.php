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
            'kod_rujukan' => 'REF-'.fake()->unique()->numerify('####'),
            'status_laporan' => 'Muktamad',
            // Struktur mesti sepadan dengan yang ditulis oleh
            // AnalisisInventoriController@simpan supaya templat laporan
            // boleh dirender dalam ujian.
            'data' => [
                'ringkasan_data' => 'lengkap',
                'data_status' => [
                    'j0' => ['penerimaan' => 'Diterima', 'kebolehgunaan' => 'Boleh Digunakan', 'nota' => ''],
                    'j1' => ['penerimaan' => 'Diterima', 'kebolehgunaan' => 'Boleh Digunakan', 'nota' => ''],
                    'j2' => ['penerimaan' => 'Tiada', 'kebolehgunaan' => 'Tidak Boleh Digunakan', 'nota' => ''],
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
