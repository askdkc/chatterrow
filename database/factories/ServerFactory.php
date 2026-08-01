<?php

namespace Database\Factories;

use App\Models\Server;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Server>
 */
class ServerFactory extends Factory
{
    protected $model = Server::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'created_by' => User::factory(),
            'name' => fake()->company().' サーバー',
            'description' => fake()->sentence(),
            'starts_on' => fake()->date(),
            'ends_on' => fake()->dateTimeBetween('+1 month', '+1 year')->format('Y-m-d'),
        ];
    }
}
