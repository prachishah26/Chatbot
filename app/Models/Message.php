<?php

declare(strict_types=1);

namespace App\Models;

use App\Chat\Data\ChatTurn;
use App\Chat\Data\Role;
use Database\Factories\MessageFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One stored turn of a conversation.
 *
 * @property int $id
 * @property int $conversation_id
 * @property Role $role
 * @property string $content
 * @property Carbon $created_at
 */
final class Message extends Model
{
    /** @use HasFactory<MessageFactory> */
    use HasFactory;

    protected $fillable = ['role', 'content'];

    /**
     * Bumping the thread keeps the sidebar ordered by most recent activity.
     *
     * @var list<string>
     */
    protected $touches = ['conversation'];

    /**
     * @return BelongsTo<Conversation, $this>
     */
    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    public function toChatTurn(): ChatTurn
    {
        return new ChatTurn($this->role, $this->content);
    }

    /**
     * @return array{id: int, role: string, content: string, created_at: string}
     */
    public function toChatArray(): array
    {
        return [
            'id' => $this->id,
            'role' => $this->role->value,
            'content' => $this->content,
            'created_at' => $this->created_at->toIso8601String(),
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['role' => Role::class];
    }
}
