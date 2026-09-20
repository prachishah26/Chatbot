<?php

declare(strict_types=1);

namespace App\Chat;

use App\Chat\Support\ChatOwner;
use App\Chat\Support\ChatTurn;
use App\Chat\Support\Role;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use Illuminate\Contracts\Session\Session;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

/**
 * Persistence boundary for chat threads.
 *
 * A signed-in visitor's threads hang off their account, so the history follows
 * them to any browser. A guest's threads hang off an opaque key in the session,
 * which keeps the bot usable without an account.
 */
final readonly class ConversationStore
{
    private const CURRENT_KEY = 'chat.conversation_id';

    private const OWNER_KEY = 'chat.owner_key';

    private const CLAIMABLE_KEY = 'chat.claimable_ids';

    private const TITLE_LENGTH = 48;

    public function __construct(private int $historyLimit) {}

    /**
     * Whose threads the current request may see.
     */
    public function owner(Session $session): ChatOwner
    {
        $userId = Auth::id();

        return $userId === null
            ? ChatOwner::guest($this->guestKey($session))
            : ChatOwner::account((int) $userId);
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
    public function claimGuestThreads(Session $session, User $user): int
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

    /**
     * Notes a guest thread as one this visit opened, so signing in later can
     * carry it over without also sweeping up an earlier visitor's chats.
     */
    private function markClaimable(Session $session, Conversation $conversation): void
    {
        $claimable = $session->get(self::CLAIMABLE_KEY, []);

        $session->put(self::CLAIMABLE_KEY, [
            ...(is_array($claimable) ? $claimable : []),
            $conversation->id,
        ]);
    }

    /**
     * The guest's stable identity, minted on first use.
     *
     * Kept separate from the session id so a session regeneration does not
     * orphan the visitor's existing threads.
     */
    private function guestKey(Session $session): string
    {
        $key = $session->get(self::OWNER_KEY);

        if (! is_string($key) || $key === '') {
            $key = Str::random(40);
            $session->put(self::OWNER_KEY, $key);
        }

        return $key;
    }

    /**
     * The thread the visitor is currently looking at.
     */
    public function current(Session $session): Conversation
    {
        $publicId = $session->get(self::CURRENT_KEY);

        $conversation = is_string($publicId) ? $this->find($session, $publicId) : null;

        return $conversation ?? $this->startNew($session);
    }

    /**
     * Looks up one of the visitor's own threads, or null if it is not theirs.
     */
    public function find(Session $session, string $publicId): ?Conversation
    {
        return Conversation::query()
            ->ownedBy($this->owner($session))
            ->where('public_id', $publicId)
            ->first();
    }

    /**
     * Opens a fresh thread, reusing the current one while it is still empty.
     */
    public function startNew(Session $session): Conversation
    {
        $publicId = $session->get(self::CURRENT_KEY);

        $existing = is_string($publicId) ? $this->find($session, $publicId) : null;

        if ($existing !== null && ! $existing->messages()->exists()) {
            return $existing;
        }

        $owner = $this->owner($session);

        $conversation = new Conversation;
        $conversation->forceFill($owner->attributes());
        $conversation->save();
        $session->put(self::CURRENT_KEY, $conversation->public_id);

        if ($owner->isGuest()) {
            $this->markClaimable($session, $conversation);
        }

        return $conversation;
    }

    public function makeCurrent(Session $session, Conversation $conversation): void
    {
        $session->put(self::CURRENT_KEY, $conversation->public_id);
    }

    /**
     * The visitor's threads for the sidebar, most recently used first.
     *
     * Threads with no messages are drafts and stay hidden until they are used.
     *
     * @return Collection<int, Conversation>
     */
    public function sidebar(Session $session): Collection
    {
        return Conversation::query()
            ->ownedBy($this->owner($session))
            ->whereHas('messages')
            ->latest('updated_at')
            ->latest('id')
            ->get();
    }

    /**
     * @return Collection<int, Message>
     */
    public function messages(Conversation $conversation): Collection
    {
        return $conversation->messages()->get();
    }

    /**
     * The trailing slice of the thread replayed to the provider as context.
     *
     * @return list<ChatTurn>
     */
    public function recentTurns(Conversation $conversation): array
    {
        return $conversation->messages()
            ->reorder('id', 'desc')
            ->limit($this->historyLimit)
            ->get()
            ->reverse()
            ->map(static fn (Message $message): ChatTurn => $message->toChatTurn())
            ->values()
            ->all();
    }

    public function append(Conversation $conversation, Role $role, string $content): Message
    {
        $message = $conversation->messages()->create([
            'role' => $role,
            'content' => $content,
        ]);

        if ($role === Role::User && $conversation->title === null) {
            $conversation->update(['title' => self::titleFrom($content)]);
        }

        return $message;
    }

    public function delete(Conversation $conversation): void
    {
        $conversation->delete();
    }

    /**
     * Names a thread after its opening message, the way the sidebar reads best.
     */
    private static function titleFrom(string $content): string
    {
        $flattened = trim((string) preg_replace('/\s+/', ' ', $content));

        return $flattened === ''
            ? 'New chat'
            : Str::limit($flattened, self::TITLE_LENGTH);
    }
}
