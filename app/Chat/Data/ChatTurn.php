<?php

declare(strict_types=1);

namespace App\Chat\Data;

/**
 * An immutable single turn of a conversation.
 *
 * Provider-neutral on purpose: each provider maps turns to its own wire format.
 */
final readonly class ChatTurn
{
    public function __construct(
        public Role $role,
        public string $text,
    ) {}

    public static function user(string $text): self
    {
        return new self(Role::User, $text);
    }

    public static function assistant(string $text): self
    {
        return new self(Role::Assistant, $text);
    }
}
