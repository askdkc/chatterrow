<?php

namespace Database\Factories;

use App\Models\Channel;
use App\Models\Todo;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Todo>
 */
class TodoFactory extends Factory
{
    protected $model = Todo::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'channel_id' => Channel::factory(),
            'assignee_id' => User::factory(),
            'created_by' => User::factory(),
            'title' => fake()->sentence(4),
            'details' => fake()->paragraph(),
            'due_on' => fake()->dateTimeBetween('now', '+1 month')->format('Y-m-d'),
            'completed_at' => null,
            'completed_by' => null,
            'position' => 0,
        ];
    }
}
