<?php

declare(strict_types=1);

namespace App\Chat\Support;

use Illuminate\Database\Eloquent\Builder;

/**
 * Who a set of threads belongs to.
 *
 * A signed-in visitor is identified by their account, so their history follows
 * them to any browser. Everyone else is identified by an opaque key held in the
 * session, which keeps the bot usable without an account but confines the
 * history to that one browser.
 */
final readonly class ChatOwner
{
    private function __construct(
        public ?int $userId,
        public ?string $guestKey,
    ) {}

    public static function account(int $userId): self
    {
        return new self($userId, null);
    }

    public static function guest(string $guestKey): self
    {
        return new self(null, $guestKey);
    }

    public function isGuest(): bool
    {
        return $this->userId === null;
    }

    /**
     * Narrows a query to the threads this owner is allowed to see.
     *
     * @template TModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    public function constrain(Builder $query): Builder
    {
        return $this->userId !== null
            ? $query->where('user_id', $this->userId)
            : $query->whereNull('user_id')->where('owner_key', $this->guestKey);
    }

    /**
     * The ownership columns to stamp on a newly created thread.
     *
     * @return array{user_id: ?int, owner_key: ?string}
     */
    public function attributes(): array
    {
        return ['user_id' => $this->userId, 'owner_key' => $this->guestKey];
    }
}
