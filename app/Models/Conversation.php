<?php

declare(strict_types=1);

namespace App\Models;

use App\Chat\Data\ChatOwner;
use Database\Factories\ConversationFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * A chat thread, owned either by an account or by one browser session.
 *
 * @property int $id
 * @property string $public_id
 * @property ?int $user_id
 * @property ?string $title
 * @property ?string $owner_key
 * @property Carbon $updated_at
 */
final class Conversation extends Model
{
    /** @use HasFactory<ConversationFactory> */
    use HasFactory;

    /**
     * Ownership is never taken from request data; it is stamped by
     * ConversationService from a server-derived ChatOwner.
     *
     * @var list<string>
     */
    protected $fillable = ['title'];

    /**
     * The id the outside world sees. Keeps the auto-increment key out of URLs,
     * where it would advertise how many threads exist and invite probing.
     */
    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    protected static function booted(): void
    {
        self::creating(static function (self $conversation): void {
            $conversation->public_id ??= (string) Str::ulid();
        });
    }

    /**
     * @return HasMany<Message, $this>
     */
    public function messages(): HasMany
    {
        return $this->hasMany(Message::class)->oldest('id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeOwnedBy(Builder $query, ChatOwner $owner): Builder
    {
        return $owner->constrain($query);
    }

    /**
     * Threads worth listing in the sidebar, most recently used first.
     * Threads with no messages are drafts and stay hidden until they are used.
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeForSidebar(Builder $query): Builder
    {
        return $query->whereHas('messages')->latest('updated_at')->latest('id');
    }

    /**
     * The label shown in the sidebar before the first message names the thread.
     */
    public function displayTitle(): string
    {
        return $this->title ?? 'New chat';
    }
}
