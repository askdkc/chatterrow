<?php

namespace Database\Factories;

use App\Models\Channel;
use App\Models\Message;
use App\Models\Server;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Message>
 */
class MessageFactory extends Factory
{
    protected $model = Message::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'server_id' => Server::factory(),
            'channel_id' => Channel::factory(),
            'user_id' => User::factory(),
            'parent_id' => null,
            'body' => fake()->paragraph(),
        ];
    }
}
