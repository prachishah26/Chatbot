<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Chat\Support\Role;
use App\Models\Conversation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class LoginTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_renders_the_login_screen(): void
    {
        $this->get(route('login'))
            ->assertOk()
            ->assertSee('Welcome back');
    }

    #[Test]
    public function it_signs_a_user_in_with_valid_credentials(): void
    {
        $user = User::factory()->create([
            'email' => 'prachi@example.com',
            'password' => Hash::make('correct-horse-battery'),
        ]);

        $this->post(route('login.store'), [
            'email' => 'prachi@example.com',
            'password' => 'correct-horse-battery',
        ])->assertRedirect(route('chat.index'));

        $this->assertAuthenticatedAs($user);
    }

    #[Test]
    public function it_rejects_a_wrong_password_without_revealing_whether_the_email_exists(): void
    {
        User::factory()->create([
            'email' => 'prachi@example.com',
            'password' => Hash::make('correct-horse-battery'),
        ]);

        // The same message either way, so the form cannot be used to discover
        // which addresses have accounts.
        $expected = 'Those credentials do not match our records.';

        $this->post(route('login.store'), [
            'email' => 'prachi@example.com',
            'password' => 'not-the-password',
        ])->assertSessionHasErrors(['email' => $expected]);

        $this->post(route('login.store'), [
            'email' => 'nobody@example.com',
            'password' => 'not-the-password',
        ])->assertSessionHasErrors(['email' => $expected]);

        $this->assertGuest();
    }

    #[Test]
    public function it_regenerates_the_session_on_sign_in(): void
    {
        $user = User::factory()->create([
            'email' => 'prachi@example.com',
            'password' => Hash::make('correct-horse-battery'),
        ]);

        $this->get(route('chat.index'))->assertOk();
        $before = session()->getId();

        $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'correct-horse-battery',
        ])->assertRedirect(route('chat.index'));

        $this->assertNotSame($before, session()->getId(), 'A fresh session id blocks session fixation.');
    }

    #[Test]
    public function it_rate_limits_repeated_failed_attempts(): void
    {
        User::factory()->create(['email' => 'prachi@example.com']);

        foreach (range(1, 5) as $ignored) {
            $this->post(route('login.store'), [
                'email' => 'prachi@example.com',
                'password' => 'wrong',
            ]);
        }

        $this->post(route('login.store'), [
            'email' => 'prachi@example.com',
            'password' => 'wrong',
        ])->assertStatus(429);
    }

    #[Test]
    public function signing_in_claims_the_threads_started_as_a_guest(): void
    {
        $user = User::factory()->create(['password' => Hash::make('correct-horse-battery')]);

        $this->get(route('chat.index'))->assertOk();
        $guestThread = Conversation::sole();
        $guestThread->messages()->create(['role' => Role::User, 'content' => 'Asked before signing in']);

        $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'correct-horse-battery',
        ])->assertRedirect(route('chat.index'));

        $this->assertSame($user->id, $guestThread->refresh()->user_id);
        $this->get(route('chat.index'))->assertOk()->assertSee('Asked before signing in');
    }

    #[Test]
    public function signing_in_does_not_claim_guest_threads_from_an_earlier_visit(): void
    {
        $user = User::factory()->create(['password' => Hash::make('correct-horse-battery')]);

        $this->get(route('chat.index'))->assertOk();
        $guestKey = session('chat.owner_key');

        // A thread under this browser's guest key that this visit never opened:
        // on a shared machine that is someone else's conversation.
        $earlier = Conversation::factory()->ownedBy((string) $guestKey)->create(['title' => 'An earlier visitor']);
        $earlier->messages()->create(['role' => Role::User, 'content' => 'An earlier visitor']);

        $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'correct-horse-battery',
        ])->assertRedirect(route('chat.index'));

        $this->assertNull($earlier->refresh()->user_id, 'An earlier visitor is not swept into the account.');
        $this->get(route('chat.index'))->assertOk()->assertDontSee('An earlier visitor');
    }

    #[Test]
    public function an_empty_draft_started_as_a_guest_survives_signing_in(): void
    {
        $user = User::factory()->create(['password' => Hash::make('correct-horse-battery')]);

        $this->get(route('chat.index'))->assertOk();
        $draft = Conversation::sole();

        $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'correct-horse-battery',
        ])->assertRedirect(route('chat.index'));

        $this->assertSame($user->id, $draft->refresh()->user_id);

        $this->get(route('chat.index'))->assertOk();

        $this->assertSame($draft->public_id, session('chat.conversation_id'), 'The pointer still resolves after the claim.');
        $this->assertSame(1, Conversation::count(), 'The claim does not strand the draft and open another.');
    }

    #[Test]
    public function signing_out_clears_the_chat_keys_from_the_session(): void
    {
        $this->actingAs(User::factory()->create())->get(route('chat.index'))->assertOk();

        $this->post(route('logout'))->assertRedirect(route('chat.index'));

        $this->assertNull(session('chat.conversation_id'));
        $this->assertNull(session('chat.owner_key'));
        $this->assertNull(session('chat.claimable_ids'));
    }

    #[Test]
    public function it_signs_a_user_out_and_invalidates_the_session(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('logout'))
            ->assertRedirect(route('chat.index'));

        $this->assertGuest();
    }

    #[Test]
    public function it_keeps_signed_in_visitors_away_from_the_login_screen(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('login'))
            ->assertRedirect(route('chat.index'));
    }

    protected function tearDown(): void
    {
        RateLimiter::clear('auth');

        parent::tearDown();
    }
}
