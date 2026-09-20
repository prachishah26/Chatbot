<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Conversation;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Conversation>
 */
final class ConversationFactory extends Factory
{
    protected $model = Conversation::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'public_id' => (string) Str::ulid(),
            'user_id' => null,
            'title' => null,
            'owner_key' => Str::random(40),
        ];
    }

    /**
     * Ties the thread to one browser session.
     */
    public function ownedBy(string $ownerKey): static
    {
        return $this->state(fn (): array => ['user_id' => null, 'owner_key' => $ownerKey]);
    }

    /**
     * Ties the thread to an account, so it follows the user between browsers.
     */
    public function forUser(User $user): static
    {
        return $this->state(fn (): array => ['user_id' => $user->id, 'owner_key' => null]);
    }
}
