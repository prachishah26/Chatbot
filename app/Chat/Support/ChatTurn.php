<?php

declare(strict_types=1);

namespace App\Chat\Support;

/**
 * An immutable single turn of a conversation.
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

    /**
     * @return array{role: string, parts: list<array{text: string}>}
     */
    public function toGeminiContent(): array
    {
        return [
            'role' => $this->role->toGemini(),
            'parts' => [['text' => $this->text]],
        ];
    }

    /**
     * @return array{role: string, content: string}
     */
    public function toOllamaMessage(): array
    {
        return [
            'role' => $this->role->value,
            'content' => $this->text,
        ];
    }
}
