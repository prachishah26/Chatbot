<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Chat\Data\Role;
use App\Models\Conversation;
use App\Models\Message;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Message>
 */
final class MessageFactory extends Factory
{
    protected $model = Message::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'conversation_id' => Conversation::factory(),
            'role' => Role::User,
            'content' => fake()->sentence(),
        ];
    }

    public function fromAssistant(): self
    {
        return $this->state(fn (): array => ['role' => Role::Assistant]);
    }
}
