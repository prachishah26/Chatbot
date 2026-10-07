<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Chat\Data\ChatOwner;
use App\Models\Conversation;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class ChatOwnerTest extends TestCase
{
    #[Test]
    public function an_account_owner_is_scoped_by_user_id_only(): void
    {
        $owner = ChatOwner::account(7);

        $this->assertFalse($owner->isGuest());
        $this->assertSame(['user_id' => 7, 'owner_key' => null], $owner->attributes());

        $query = $owner->constrain(Conversation::query());

        $this->assertSame('select * from "conversations" where "user_id" = ?', $query->toSql());
        $this->assertSame([7], $query->getBindings());
    }

    #[Test]
    public function a_guest_owner_never_sees_threads_that_belong_to_an_account(): void
    {
        $owner = ChatOwner::guest('opaque-key');

        $this->assertTrue($owner->isGuest());
        $this->assertSame(['user_id' => null, 'owner_key' => 'opaque-key'], $owner->attributes());

        $query = $owner->constrain(Conversation::query());

        $this->assertSame('select * from "conversations" where "user_id" is null and "owner_key" = ?', $query->toSql());
        $this->assertSame(['opaque-key'], $query->getBindings());
    }
}
