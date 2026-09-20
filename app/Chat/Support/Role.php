<?php

declare(strict_types=1);

namespace App\Chat\Support;

/**
 * Who authored a turn in a conversation.
 */
enum Role: string
{
    case User = 'user';
    case Assistant = 'assistant';

    /**
     * Gemini names the assistant role "model".
     */
    public function toGemini(): string
    {
        return match ($this) {
            self::User => 'user',
            self::Assistant => 'model',
        };
    }
}
