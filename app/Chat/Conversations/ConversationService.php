<?php

declare(strict_types=1);

namespace App\Chat\Conversations;

use App\Chat\Data\ChatOwner;
use App\Chat\Data\ChatTurn;
use App\Chat\Data\Role;
use App\Models\Conversation;
use App\Models\Message;
use Illuminate\Contracts\Auth\Factory as AuthFactory;
use Illuminate\Contracts\Session\Session;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * The chat window's conversations: which one is open, what the sidebar lists,
 * and adding messages to them. Controllers go through this service rather than
 * querying Conversation directly.
 *
 * A signed-in visitor's threads hang off their account, so the history follows
 * them to any browser. A guest's threads hang off an opaque key in the session,
 * which keeps the bot usable without an account (see GuestConversationService).
 *
 * Every lookup is scoped through ChatOwner, so a visitor can never read or
 * delete another visitor's thread by guessing its public id.
 */
final readonly class ConversationService
{
    private const CURRENT_KEY = 'chat.conversation_id';

    private const TITLE_LENGTH = 48;

    public function __construct(
        private AuthFactory $auth,
        private GuestConversationService $guests,
        private int $historyLimit,
    ) {}

    /**
     * Whose threads the current request may see.
     */
    public function owner(Session $session): ChatOwner
    {
        $userId = $this->auth->guard()->id();

        return $userId === null
            ? ChatOwner::guest($this->guests->key($session))
            : ChatOwner::account((int) $userId);
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
            $this->guests->remember($session, $conversation);
        }

        return $conversation;
    }

    public function makeCurrent(Session $session, Conversation $conversation): void
    {
        $session->put(self::CURRENT_KEY, $conversation->public_id);
    }

    /**
     * The visitor's own threads for the sidebar (see Conversation::scopeForSidebar).
     *
     * @return Collection<int, Conversation>
     */
    public function sidebar(Session $session): Collection
    {
        return Conversation::query()
            ->ownedBy($this->owner($session))
            ->forSidebar()
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

    public function addMessage(Conversation $conversation, Role $role, string $content): Message
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
