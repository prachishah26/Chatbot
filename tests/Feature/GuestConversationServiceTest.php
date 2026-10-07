<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Chat\Conversations\GuestConversationService;
use App\Models\Conversation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Session\ArraySessionHandler;
use Illuminate\Session\Store;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class GuestConversationServiceTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_mints_a_guest_key_once_and_keeps_it(): void
    {
        $session = $this->newSession();
        $guests = new GuestConversationService;

        $key = $guests->key($session);

        $this->assertSame(40, strlen($key));
        $this->assertSame($key, $guests->key($session));
    }

    #[Test]
    public function it_claims_only_threads_this_visit_opened(): void
    {
        $session = $this->newSession();
        $guests = new GuestConversationService;
        $key = $guests->key($session);
        $user = User::factory()->create();

        $opened = Conversation::factory()->ownedBy($key)->create();
        $earlierVisitor = Conversation::factory()->ownedBy($key)->create();
        $guests->remember($session, $opened);

        $this->assertSame(1, $guests->claim($session, $user));

        $this->assertSame($user->id, $opened->refresh()->user_id);
        $this->assertNull($opened->owner_key);
        $this->assertNull($earlierVisitor->refresh()->user_id, "An earlier visitor's thread stays put.");
        $this->assertNull($session->get('chat.claimable_ids'));
    }

    #[Test]
    public function it_never_claims_a_thread_under_another_guest_key(): void
    {
        $session = $this->newSession();
        $guests = new GuestConversationService;
        $guests->key($session);

        $someoneElses = Conversation::factory()->ownedBy('another-key')->create();
        $guests->remember($session, $someoneElses);

        $this->assertSame(0, $guests->claim($session, User::factory()->create()));
        $this->assertNull($someoneElses->refresh()->user_id);
    }

    #[Test]
    public function claiming_with_nothing_remembered_is_a_no_op(): void
    {
        $this->assertSame(0, (new GuestConversationService)->claim($this->newSession(), User::factory()->create()));
    }

    private function newSession(): Store
    {
        return new Store('test', new ArraySessionHandler(120));
    }
}
