<?php

namespace Database\Factories;

use App\Models\Channel;
use App\Models\Server;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Channel>
 */
class ChannelFactory extends Factory
{
    protected $model = Channel::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'server_id' => Server::factory(),
            'created_by' => User::factory(),
            'name' => fake()->unique()->words(2, true),
            'description' => fake()->sentence(),
            'starts_on' => fake()->date(),
            'ends_on' => fake()->dateTimeBetween('+1 month', '+1 year')->format('Y-m-d'),
        ];
    }
}
