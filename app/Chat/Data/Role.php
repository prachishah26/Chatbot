<?php

declare(strict_types=1);

namespace App\Chat\Data;

/**
 * Who authored a turn in a conversation.
 */
enum Role: string
{
    case User = 'user';
    case Assistant = 'assistant';
}
