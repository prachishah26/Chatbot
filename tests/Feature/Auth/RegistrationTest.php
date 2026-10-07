<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Chat\Data\Role;
use App\Models\Conversation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_renders_the_registration_screen(): void
    {
        $this->get(route('register'))
            ->assertOk()
            ->assertSee('Create your account');
    }

    #[Test]
    public function it_registers_a_user_and_signs_them_in(): void
    {
        $this->post(route('register.store'), [
            'name' => 'Prachi',
            'email' => 'prachi@example.com',
            'password' => 'correct-horse-battery',
            'password_confirmation' => 'correct-horse-battery',
        ])->assertRedirect(route('chat.index'));

        $user = User::sole();

        $this->assertSame('Prachi', $user->name);
        $this->assertAuthenticatedAs($user);
        $this->assertNotSame('correct-horse-battery', $user->password, 'The password is stored hashed.');
    }

    #[Test]
    public function it_rejects_an_email_that_is_already_registered(): void
    {
        User::factory()->create(['email' => 'taken@example.com']);

        $this->post(route('register.store'), [
            'name' => 'Someone',
            'email' => 'taken@example.com',
            'password' => 'correct-horse-battery',
            'password_confirmation' => 'correct-horse-battery',
        ])->assertSessionHasErrors('email');

        $this->assertGuest();
        $this->assertSame(1, User::count());
    }

    #[Test]
    public function it_requires_a_confirmed_password_of_reasonable_length(): void
    {
        $this->post(route('register.store'), [
            'name' => 'Someone',
            'email' => 'someone@example.com',
            'password' => 'short',
            'password_confirmation' => 'mismatch',
        ])->assertSessionHasErrors('password');

        $this->assertGuest();
        $this->assertSame(0, User::count());
    }

    #[Test]
    public function registering_claims_the_threads_started_as_a_guest(): void
    {
        $this->get(route('chat.index'))->assertOk();

        $guestThread = Conversation::sole();
        $guestThread->messages()->create(['role' => Role::User, 'content' => 'Started before signing up']);

        $this->post(route('register.store'), [
            'name' => 'Prachi',
            'email' => 'prachi@example.com',
            'password' => 'correct-horse-battery',
            'password_confirmation' => 'correct-horse-battery',
        ])->assertRedirect(route('chat.index'));

        $guestThread->refresh();

        $this->assertSame(User::sole()->id, $guestThread->user_id);
        $this->assertNull($guestThread->owner_key, 'A claimed thread is no longer reachable by the guest key.');

        $this->get(route('chat.index'))->assertOk()->assertSee('Started before signing up');
    }

    #[Test]
    public function it_keeps_signed_in_visitors_away_from_the_registration_screen(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('register'))
            ->assertRedirect(route('chat.index'));
    }

    protected function tearDown(): void
    {
        RateLimiter::clear('auth');

        parent::tearDown();
    }
}
