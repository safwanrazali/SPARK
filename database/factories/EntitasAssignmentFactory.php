<?php

namespace Database\Factories;

use App\Models\EntitasAssignment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EntitasAssignment>
 */
class EntitasAssignmentFactory extends Factory
{
    protected $model = EntitasAssignment::class;

    public function definition(): array
    {
        return [
            'agency_code' => fake()->unique()->bothify('A######'),
            'agency_name' => fake()->company(),
            'sector_code' => '001',
            'sector_name' => 'Kerajaan',
            'assigned_to_user_id' => User::factory()->state(['role' => User::ROLE_ANALYST]),
            'assigned_by_user_id' => User::factory()->state(['role' => User::ROLE_COORDINATOR]),
            'assigned_at' => now(),
            'status' => 'active',
            'notes' => fake()->sentence(),
        ];
    }
}
