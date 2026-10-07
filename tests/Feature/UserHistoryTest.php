<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Chat\Data\Role;
use App\Models\Conversation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Account-scoped history: a signed-in visitor's threads follow the account
 * rather than the browser session, so they survive a new browser or a private
 * window. Guests keep the session-scoped behaviour.
 */
final class UserHistoryTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function a_signed_in_visitor_sees_their_history_in_a_brand_new_browser(): void
    {
        $user = User::factory()->create();
        $this->threadFor($user, 'Kept across browsers');

        // No session state at all: the private-window case.
        $this->flushSession();

        $this->actingAs($user)
            ->get(route('chat.index'))
            ->assertOk()
            ->assertSee('Kept across browsers');
    }

    #[Test]
    public function one_users_threads_are_hidden_from_another(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        $thread = $this->threadFor($owner, 'Private to the owner');

        $this->actingAs($intruder)
            ->get(route('chat.index'))
            ->assertOk()
            ->assertDontSee('Private to the owner');

        $this->actingAs($intruder)->get(route('chat.show', $thread))->assertNotFound();
        $this->actingAs($intruder)->delete(route('chat.conversations.destroy', $thread))->assertNotFound();

        $this->assertModelExists($thread);
    }

    #[Test]
    public function a_guest_cannot_reach_an_accounts_threads(): void
    {
        $thread = $this->threadFor(User::factory()->create(), 'Belongs to an account');

        $this->get(route('chat.show', $thread))->assertNotFound();
        $this->get(route('chat.index'))->assertOk()->assertDontSee('Belongs to an account');
    }

    #[Test]
    public function a_signed_in_visitor_does_not_inherit_the_guest_threads_of_that_browser(): void
    {
        $strangerGuestThread = Conversation::factory()->ownedBy('someone-elses-browser')->create();
        $strangerGuestThread->messages()->create(['role' => Role::User, 'content' => 'Guest thread elsewhere']);

        $this->actingAs(User::factory()->create())
            ->get(route('chat.index'))
            ->assertOk()
            ->assertDontSee('Guest thread elsewhere');
    }

    #[Test]
    public function new_threads_started_while_signed_in_belong_to_the_account(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('chat.index'))->assertOk();

        $thread = Conversation::sole();

        $this->assertSame($user->id, $thread->user_id);
        $this->assertNull($thread->owner_key, 'An account thread carries no guest key.');
    }

    #[Test]
    public function signing_out_returns_the_visitor_to_an_empty_guest_history(): void
    {
        $user = User::factory()->create();
        $this->threadFor($user, 'Only visible when signed in');

        $this->actingAs($user)->get(route('chat.index'))->assertOk()->assertSee('Only visible when signed in');

        $this->post(route('logout'))->assertRedirect(route('chat.index'));

        $this->get(route('chat.index'))->assertOk()->assertDontSee('Only visible when signed in');
    }

    #[Test]
    public function deleting_an_account_removes_its_threads(): void
    {
        $user = User::factory()->create();
        $thread = $this->threadFor($user, 'Goes with the account');

        $user->delete();

        $this->assertModelMissing($thread);
    }

    private function threadFor(User $user, string $firstMessage): Conversation
    {
        $thread = Conversation::factory()->forUser($user)->create();
        $thread->messages()->create(['role' => Role::User, 'content' => $firstMessage]);
        $thread->update(['title' => $firstMessage]);

        return $thread;
    }

    protected function tearDown(): void
    {
        RateLimiter::clear('chat');
        RateLimiter::clear('chat-ui');

        parent::tearDown();
    }
}
