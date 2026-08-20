<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\WorkflowStatus;
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
            'stage_name' => WorkflowStatus::getStageName(WorkflowStatus::FIRST_STAGE),
            'status' => WorkflowStatus::DEFAULT_STATUS,
            'status_since' => now(),
            'updated_by_user_id' => User::factory()->state(['role' => User::ROLE_COORDINATOR]),
        ];
    }

    /**
     * Letakkan entiti pada peringkat tertentu (1–7).
     */
    public function onStage(int $stage): static
    {
        return $this->state(fn () => [
            'current_stage' => $stage,
            'stage_name' => WorkflowStatus::getStageName($stage),
        ]);
    }

    /**
     * Entiti yang telah menamatkan kesemua tujuh peringkat.
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
