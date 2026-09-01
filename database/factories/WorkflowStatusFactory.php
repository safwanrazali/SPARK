<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\WorkflowStatus;
use App\Support\AliranKerja;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WorkflowStatus>
 */
class WorkflowStatusFactory extends Factory
{
    protected $model = WorkflowStatus::class;

    public function definition(): array
    {
        return [
            'agency_code' => fake()->unique()->bothify('A######'),
            'agency_name' => fake()->company(),
            'sector_code' => '001',
            'sector_name' => 'Kerajaan',
            'current_stage' => WorkflowStatus::FIRST_STAGE,
            'current_stage_key' => AliranKerja::PERTAMA,
            'stage_name' => WorkflowStatus::getStageName(WorkflowStatus::FIRST_STAGE),
            'status' => WorkflowStatus::DEFAULT_STATUS,
            'status_since' => now(),
            'updated_by_user_id' => User::factory()->state(['role' => User::ROLE_COORDINATOR]),
        ];
    }

    /**
     * Letakkan entiti pada peringkat UTAMA tertentu (1–5).
     *
     * Kunci sub-peringkat ditetapkan kepada sub-peringkat PERTAMA peringkat
     * utama itu — kedudukan paling awal yang boleh dimiliki entiti di situ.
     */
    public function onStage(int $stage): static
    {
        return $this->state(fn () => [
            'current_stage' => $stage,
            'current_stage_key' => AliranKerja::subPeringkat($stage)[0] ?? AliranKerja::PERTAMA,
            'stage_name' => WorkflowStatus::getStageName($stage),
        ]);
    }

    /**
     * Letakkan entiti pada satu kunci peringkat tertentu ('1.2', '3.1', …).
     */
    public function onStageKey(string $key): static
    {
        return $this->state(fn () => [
            'current_stage' => AliranKerja::utamaBagi($key) ?? WorkflowStatus::FIRST_STAGE,
            'current_stage_key' => $key,
            'stage_name' => WorkflowStatus::getStageName(AliranKerja::utamaBagi($key) ?? WorkflowStatus::FIRST_STAGE),
        ]);
    }

    /**
     * Entiti yang telah menamatkan kesemua peringkat.
     *
     * Berada pada peringkat terakhir TIDAK sama dengan siap — status inilah
     * yang ditetapkan oleh KemajuanAnalisisService apabila setiap peringkat
     * Selesai, dan papan pemuka mengiranya daripada situ.
     */
    public function siap(): static
    {
        return $this->onStage(WorkflowStatus::LAST_STAGE)
            ->state(fn () => ['status' => 'Siap']);
    }
}
