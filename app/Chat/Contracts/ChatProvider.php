<?php

declare(strict_types=1);

namespace App\Chat\Contracts;

use App\Chat\Exceptions\ChatProviderException;
use App\Chat\Support\ChatTurn;

/**
 * A backend capable of producing an assistant reply.
 *
 * Implementations must never mutate the turns they are given.
 */
interface ChatProvider
{
    /**
     * @param  list<ChatTurn>  $history  Prior turns, oldest first, excluding $message.
     * @param  ?string  $model  Preferred provider model id; ignored when unknown.
     *
     * @throws ChatProviderException When the upstream service fails or replies unusably.
     */
    public function reply(string $message, array $history = [], ?string $model = null): string;
}
