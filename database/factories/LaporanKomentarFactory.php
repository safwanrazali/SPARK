<?php

namespace Database\Factories;

use App\Models\LaporanKomentar;
use App\Models\User;
use App\Support\SeksyenAnalisis;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LaporanKomentar>
 */
class LaporanKomentarFactory extends Factory
{
    protected $model = LaporanKomentar::class;

    public function definition(): array
    {
        return [
            'agency_code' => fake()->bothify('A######'),
            'agency_name' => fake()->company(),
            // Kunci seksyen sentiasa diambil daripada Borang Input — fikstur
            // tidak boleh mencipta seksyen yang ditolak pengesahan pelayan.
            'section' => fake()->randomElement(SeksyenAnalisis::kunci()),
            'content' => fake()->sentence(),
            'status' => LaporanKomentar::STATUS_TERBUKA,
            'user_id' => User::factory()->state(['role' => User::ROLE_KETUA_BAHAGIAN]),
        ];
    }

    /**
     * Komentar yang telah ditanda "Tindakan Diambil" oleh Pegawai Analisis.
     */
    public function ditindak(?User $pegawaiAnalisis = null): static
    {
        return $this->state(fn () => [
            'status' => LaporanKomentar::STATUS_TINDAKAN_DIAMBIL,
            'tindakan_oleh_user_id' => $pegawaiAnalisis?->id
                ?? User::factory()->state(['role' => User::ROLE_ANALYST]),
            'tindakan_pada' => now(),
        ]);
    }
}
