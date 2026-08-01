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
            'starts_at' => fake()->dateTimeBetween('now', '+1 week'),
            'due_at' => fake()->dateTimeBetween('+1 week', '+1 month'),
            'priority' => 'normal',
            'due_on' => null,
            'completed_at' => null,
            'completed_by' => null,
            'position' => 0,
        ];
    }
}
