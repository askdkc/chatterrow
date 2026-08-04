<?php

namespace Database\Factories;

use App\Models\Message;
use App\Models\MessageMention;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MessageMention>
 */
class MessageMentionFactory extends Factory
{
    protected $model = MessageMention::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'message_id' => Message::factory(),
            'user_id' => User::factory(),
            'kind' => 'direct',
            'read_at' => null,
        ];
    }

    public function everyone(): static
    {
        return $this->state(fn (): array => ['kind' => 'everyone']);
    }

    public function read(): static
    {
        return $this->state(fn (): array => ['read_at' => now()]);
    }
}
