<?php

declare(strict_types=1);

namespace App\Chat\Conversations;

use App\Models\Conversation;
use App\Models\User;
use Illuminate\Contracts\Session\Session;
use Illuminate\Support\Str;

/**
 * The session-held identity of a visitor who has not signed in, and the
 * hand-over of their threads when they do.
 */
final readonly class GuestConversationService
{
    private const OWNER_KEY = 'chat.owner_key';

    private const CLAIMABLE_KEY = 'chat.claimable_ids';

    /**
     * The guest's stable identity, minted on first use.
     *
     * Kept separate from the session id so a session regeneration does not
     * orphan the visitor's existing threads.
     */
    public function key(Session $session): string
    {
        $key = $session->get(self::OWNER_KEY);

        if (! is_string($key) || $key === '') {
            $key = Str::random(40);
            $session->put(self::OWNER_KEY, $key);
        }

        return $key;
    }

    /**
     * Notes a guest thread as one this visit opened, so signing in later can
     * carry it over without also sweeping up an earlier visitor's chats.
     */
    public function remember(Session $session, Conversation $conversation): void
    {
        $claimable = $session->get(self::CLAIMABLE_KEY, []);

        $session->put(self::CLAIMABLE_KEY, [
            ...(is_array($claimable) ? $claimable : []),
            $conversation->id,
        ]);
    }

    /**
     * Hands the threads started during this visit to the account that just signed in,
     * so signing up mid-conversation does not appear to lose the thread.
     *
     * Deliberately limited to threads this session opened, tracked as they are
     * created. A browser's guest key outlives any one visitor -- on a shared or
     * public machine, claiming everything under that key would move a previous
     * visitor's chats into this account permanently, turning a moment at the
     * keyboard into lasting access. The residual case, where two people share one
     * unexpired session, cannot be closed here and needs a confirmation step.
     *
     * @return int the number of threads transferred
     */
    public function claim(Session $session, User $user): int
    {
        $key = $session->get(self::OWNER_KEY);
        $claimable = $session->get(self::CLAIMABLE_KEY, []);

        if (! is_string($key) || $key === '' || ! is_array($claimable) || $claimable === []) {
            return 0;
        }

        $claimed = Conversation::query()
            ->whereIn('id', $claimable)
            ->whereNull('user_id')
            ->where('owner_key', $key)
            ->update(['user_id' => $user->id, 'owner_key' => null]);

        $session->forget(self::CLAIMABLE_KEY);

        return $claimed;
    }
}
