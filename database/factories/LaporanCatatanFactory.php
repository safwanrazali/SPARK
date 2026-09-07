<?php

namespace Database\Factories;

use App\Models\LaporanCatatan;
use App\Models\User;
use App\Support\SeksyenAnalisis;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LaporanCatatan>
 */
class LaporanCatatanFactory extends Factory
{
    protected $model = LaporanCatatan::class;

    public function definition(): array
    {
        return [
            'agency_code' => fake()->bothify('A######'),
            'agency_name' => fake()->company(),
            // Kunci seksyen sentiasa diambil daripada Borang Input — fikstur
            // tidak boleh mencipta seksyen yang ditolak pengesahan pelayan.
            'section' => fake()->randomElement(SeksyenAnalisis::kunci()),
            'content' => fake()->sentence(),
            'status' => LaporanCatatan::STATUS_TERBUKA,
            'user_id' => User::factory()->state(['role' => User::ROLE_KETUA_BAHAGIAN]),
        ];
    }

    /**
     * Catatan yang telah ditanda "Tindakan Diambil" oleh Pegawai Analisis.
     */
    public function ditindak(?User $pegawaiAnalisis = null): static
    {
        return $this->state(fn () => [
            'status' => LaporanCatatan::STATUS_TINDAKAN_DIAMBIL,
            'tindakan_oleh_user_id' => $pegawaiAnalisis?->id
                ?? User::factory()->state(['role' => User::ROLE_ANALYST]),
            'tindakan_pada' => now(),
        ]);
    }
}
