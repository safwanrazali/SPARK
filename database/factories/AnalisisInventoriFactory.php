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
            'data' => [
                'algoritma' => [
                    'SHA-256|SHA-256' => 'Dipilih',
                ],
                'profil' => [],
            ],
            'selesai' => true,
            'user_id' => User::factory(),
        ];
    }
}
