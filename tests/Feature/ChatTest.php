<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Chat\Contracts\ChatProvider;
use App\Chat\Exceptions\ChatProviderException;
use App\Chat\Support\ChatTurn;
use App\Chat\Support\Role;
use App\Models\Conversation;
use App\Models\Message;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\RateLimiter;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class ChatTest extends TestCase
{
    use RefreshDatabase;

    private const OWNER = 'owner-key-for-tests';

    #[Test]
    public function it_renders_the_chat_window(): void
    {
        $this->get(route('chat.index'))
            ->assertOk()
            ->assertSee('on your mind today', escape: false)
            ->assertSee('data-chat-form', escape: false)
            ->assertSee('New chat');
    }

    #[Test]
    public function it_shows_a_random_slice_of_the_starter_prompts(): void
    {
        config([
            'chatbot.suggestions' => ['Alpha prompt', 'Beta prompt', 'Gamma prompt', 'Delta prompt'],
            'chatbot.suggestion_count' => 2,
        ]);

        $shown = [];

        for ($i = 0; $i < 25; $i++) {
            $response = $this->get(route('chat.index'))->assertOk();
            $suggestions = $response->viewData('suggestions');

            $this->assertCount(2, $suggestions);
            $this->assertSame([], array_diff($suggestions, config('chatbot.suggestions')));

            $shown[] = serialize($suggestions);
        }

        $this->assertGreaterThan(1, count(array_unique($shown)));
    }

    #[Test]
    public function it_replays_stored_messages_into_the_view(): void
    {
        $conversation = $this->ownedConversation();
        $conversation->messages()->create(['role' => Role::User, 'content' => 'Earlier question']);

        $this->asOwner(['chat.conversation_id' => $conversation->public_id])
            ->get(route('chat.index'))
            ->assertOk()
            ->assertSee('Earlier question');
    }

    #[Test]
    public function it_escapes_stored_messages_in_the_rendered_payload(): void
    {
        $conversation = $this->ownedConversation();
        $conversation->messages()->create([
            'role' => Role::Assistant,
            'content' => '<script>alert(1)</script>',
        ]);

        $response = $this->asOwner(['chat.conversation_id' => $conversation->public_id])
            ->get(route('chat.index'));

        $response->assertOk();
        $this->assertStringNotContainsString('<script>alert(1)</script>', $response->getContent() ?: '');
    }

    #[Test]
    public function it_stores_the_exchange_and_returns_the_reply(): void
    {
        $this->fakeProvider('Hello there!');

        $response = $this->postJson(route('chat.store'), ['message' => '  Hi  ']);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.user.content', 'Hi')
            ->assertJsonPath('data.assistant.content', 'Hello there!')
            ->assertJsonPath('error', null);

        $this->assertSame(1, Conversation::count());
        $this->assertSame(
            [['user', 'Hi'], ['assistant', 'Hello there!']],
            Message::query()->orderBy('id')->get()
                ->map(fn (Message $m): array => [$m->role->value, $m->content])->all(),
        );
    }

    #[Test]
    public function it_names_the_thread_after_its_first_message(): void
    {
        $this->fakeProvider('hi');

        $this->postJson(route('chat.store'), ['message' => 'What is RAG?'])
            ->assertOk()
            ->assertJsonPath('data.conversation.title', 'What is RAG?');

        $this->postJson(route('chat.store'), ['message' => 'And its components?'])->assertOk();

        $this->assertSame(
            'What is RAG?',
            Conversation::sole()->title,
            'The title is set once and not rewritten by later messages.',
        );
    }

    #[Test]
    public function it_sends_prior_turns_as_context(): void
    {
        $this->app->instance(ChatProvider::class, new class implements ChatProvider
        {
            public function reply(string $message, array $history = [], ?string $model = null): string
            {
                return count($history).' prior turns';
            }
        });

        $this->postJson(route('chat.store'), ['message' => 'first'])->assertOk();

        $this->postJson(route('chat.store'), ['message' => 'second'])
            ->assertOk()
            ->assertJsonPath('data.assistant.content', '2 prior turns');
    }

    #[Test]
    public function a_new_thread_starts_without_the_previous_history(): void
    {
        $this->app->instance(ChatProvider::class, new class implements ChatProvider
        {
            public function reply(string $message, array $history = [], ?string $model = null): string
            {
                return count($history).' prior turns';
            }
        });

        $this->postJson(route('chat.store'), ['message' => 'first'])->assertOk();

        $this->post(route('chat.conversations.store'))->assertRedirect(route('chat.index'));

        $this->postJson(route('chat.store'), ['message' => 'fresh start'])
            ->assertOk()
            ->assertJsonPath('data.assistant.content', '0 prior turns');

        $this->assertSame(2, Conversation::count());
    }

    #[Test]
    public function starting_a_new_thread_reuses_an_unused_one(): void
    {
        $this->get(route('chat.index'))->assertOk();

        $this->post(route('chat.conversations.store'))->assertRedirect(route('chat.index'));
        $this->post(route('chat.conversations.store'))->assertRedirect(route('chat.index'));

        $this->assertSame(1, Conversation::count(), 'Empty drafts are not multiplied.');
    }

    #[Test]
    public function it_lists_the_visitors_threads_in_the_sidebar(): void
    {
        $this->fakeProvider('hi');
        $this->postJson(route('chat.store'), ['message' => 'First thread'])->assertOk();
        $this->post(route('chat.conversations.store'));
        $this->postJson(route('chat.store'), ['message' => 'Second thread'])->assertOk();

        $this->get(route('chat.index'))
            ->assertOk()
            ->assertSee('First thread')
            ->assertSee('Second thread');
    }

    #[Test]
    public function it_opens_one_of_the_visitors_own_threads(): void
    {
        $conversation = $this->ownedConversation();
        $conversation->messages()->create(['role' => Role::User, 'content' => 'Older thread']);

        $this->asOwner()
            ->get(route('chat.show', $conversation))
            ->assertOk()
            ->assertSee('Older thread');
    }

    #[Test]
    public function it_hides_threads_belonging_to_another_visitor(): void
    {
        $someoneElse = Conversation::factory()->ownedBy('a-different-visitor')->create(['title' => 'Private thread']);
        $someoneElse->messages()->create(['role' => Role::User, 'content' => 'Private thread']);

        $this->asOwner()->get(route('chat.show', $someoneElse))->assertNotFound();
        $this->asOwner()->get(route('chat.index'))->assertOk()->assertDontSee('Private thread');
    }

    #[Test]
    public function it_refuses_to_delete_another_visitors_thread(): void
    {
        $someoneElse = Conversation::factory()->ownedBy('a-different-visitor')->create();

        $this->asOwner()->delete(route('chat.conversations.destroy', $someoneElse))->assertNotFound();

        $this->assertModelExists($someoneElse);
    }

    #[Test]
    public function it_deletes_a_thread_and_its_messages(): void
    {
        $conversation = $this->ownedConversation();
        $conversation->messages()->create(['role' => Role::User, 'content' => 'Goodbye']);

        $this->asOwner()
            ->delete(route('chat.conversations.destroy', $conversation))
            ->assertRedirect(route('chat.index'));

        $this->assertModelMissing($conversation);
        $this->assertSame(0, Message::count(), 'Messages cascade with the thread.');
    }

    #[Test]
    public function it_rejects_an_empty_message(): void
    {
        $this->postJson(route('chat.store'), ['message' => '   '])
            ->assertStatus(422)
            ->assertJsonValidationErrors('message');

        $this->assertSame(0, Message::count());
    }

    #[Test]
    public function it_rejects_an_overlong_message(): void
    {
        config(['chatbot.max_message_length' => 10]);

        $this->postJson(route('chat.store'), ['message' => str_repeat('a', 11)])
            ->assertStatus(422)
            ->assertJsonValidationErrors('message');
    }

    #[Test]
    public function it_returns_a_safe_error_when_the_provider_fails(): void
    {
        $this->app->instance(ChatProvider::class, new class implements ChatProvider
        {
            public function reply(string $message, array $history = [], ?string $model = null): string
            {
                throw ChatProviderException::requestFailed('gemini', 401, 'API key invalid: secret-key-123');
            }
        });

        $response = $this->postJson(route('chat.store'), ['message' => 'Hi']);

        $response->assertStatus(503)
            ->assertJsonPath('success', false)
            ->assertJsonPath('data.assistant', null);

        $this->assertStringNotContainsString('secret-key-123', $response->getContent() ?: '');
        $this->assertSame(1, Message::count(), 'The user message is kept even when the reply fails.');
    }

    #[Test]
    public function it_offers_local_and_cloud_models_in_the_picker(): void
    {
        $this->configureProviders();

        $this->get(route('chat.index'))
            ->assertOk()
            ->assertSeeInOrder(['Ollama', 'Local', 'Llama3.2', 'Qwen3 8b', 'Gemini', 'Cloud', 'Gemini 3.6 Flash', 'Gemini 3.5 Flash Lite'])
            ->assertSee('Powered by Ollama')
            ->assertSee('Runs locally on this machine with Ollama.');
    }

    #[Test]
    public function it_hides_gemini_until_an_api_key_is_set(): void
    {
        $this->configureProviders(geminiKey: null);

        $this->get(route('chat.index'))
            ->assertOk()
            ->assertSee('Llama3.2')
            ->assertDontSee('Gemini 3.6 Flash');

        $this->post(route('chat.model.store'), ['model' => 'gemini/gemini-3.6-flash'])
            ->assertSessionHasErrors('model');
    }

    #[Test]
    public function it_shows_the_provider_of_the_chosen_model(): void
    {
        $this->configureProviders();

        $this->post(route('chat.model.store'), ['model' => 'gemini/gemini-3.5-flash-lite'])
            ->assertRedirect();

        $this->get(route('chat.index'))
            ->assertOk()
            ->assertSee('Powered by Gemini')
            ->assertSee("Sent to Gemini's API.", false);
    }

    #[Test]
    public function it_sends_the_chosen_model_to_the_provider(): void
    {
        $this->configureProviders();
        $this->echoChosenModel();

        $this->post(route('chat.model.store'), ['model' => 'gemini/gemini-3.5-flash-lite'])
            ->assertRedirect();

        $this->postJson(route('chat.store'), ['message' => 'Hi'])
            ->assertOk()
            ->assertJsonPath('data.assistant.content', 'answered by gemini/gemini-3.5-flash-lite');
    }

    #[Test]
    public function it_defaults_to_the_first_model_of_the_default_provider(): void
    {
        $this->configureProviders();
        $this->echoChosenModel();

        $this->postJson(route('chat.store'), ['message' => 'Hi'])
            ->assertOk()
            ->assertJsonPath('data.assistant.content', 'answered by ollama/llama3.2');
    }

    #[Test]
    public function it_routes_a_gemini_choice_to_the_gemini_api(): void
    {
        $this->configureProviders();

        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [['content' => ['parts' => [['text' => 'from gemini']]]]],
            ]),
            'localhost:11434/*' => Http::response(['message' => ['role' => 'assistant', 'content' => 'from ollama']]),
        ]);

        $this->post(route('chat.model.store'), ['model' => 'gemini/gemini-3.5-flash-lite']);

        $this->postJson(route('chat.store'), ['message' => 'Hi'])
            ->assertOk()
            ->assertJsonPath('data.assistant.content', 'from gemini');

        Http::assertSent(static fn (Request $request): bool => str_contains($request->url(), 'gemini-3.5-flash-lite:generateContent'));
        Http::assertNotSent(static fn (Request $request): bool => str_contains($request->url(), 'localhost:11434'));
    }

    #[Test]
    public function it_routes_an_ollama_choice_to_the_local_server(): void
    {
        $this->configureProviders();

        Http::fake([
            'localhost:11434/*' => Http::response(['message' => ['role' => 'assistant', 'content' => 'from ollama']]),
            'generativelanguage.googleapis.com/*' => Http::response([], 500),
        ]);

        $this->post(route('chat.model.store'), ['model' => 'ollama/qwen3:8b']);

        $this->postJson(route('chat.store'), ['message' => 'Hi'])
            ->assertOk()
            ->assertJsonPath('data.assistant.content', 'from ollama');

        Http::assertSent(static fn (Request $request): bool => $request->url() === 'http://localhost:11434/api/chat'
            && $request->data()['model'] === 'qwen3:8b');
        Http::assertNotSent(static fn (Request $request): bool => str_contains($request->url(), 'googleapis'));
    }

    #[Test]
    public function it_rejects_a_model_that_is_not_configured(): void
    {
        $this->configureProviders();

        $this->post(route('chat.model.store'), ['model' => 'gemini/gemini-3.8-flash'])
            ->assertSessionHasErrors('model');

        $this->assertNull(session('chat.model'), 'An unlisted model is never stored.');
    }

    #[Test]
    public function it_rate_limits_repeated_requests(): void
    {
        $this->fakeProvider('hi');

        foreach (range(1, 20) as $ignored) {
            $this->postJson(route('chat.store'), ['message' => 'Hi'])->assertOk();
        }

        $this->postJson(route('chat.store'), ['message' => 'Hi'])
            ->assertStatus(429)
            ->assertJsonPath('success', false);
    }

    #[Test]
    public function it_exposes_a_csrf_token_for_the_client(): void
    {
        $this->get(route('chat.index'))
            ->assertOk()
            ->assertSee('name="csrf-token"', escape: false);

        // The web group supplies session state and CSRF verification.
        $this->assertContains('web', app('router')->getRoutes()->getByName('chat.store')->gatherMiddleware());
    }

    protected function tearDown(): void
    {
        RateLimiter::clear('chat');
        RateLimiter::clear('chat-ui');

        parent::tearDown();
    }

    private function ownedConversation(): Conversation
    {
        return Conversation::factory()->ownedBy(self::OWNER)->create();
    }

    /**
     * @param  array<string, mixed>  $session
     */
    private function asOwner(array $session = []): static
    {
        return $this->withSession([...$session, 'chat.owner_key' => self::OWNER]);
    }

    private function fakeProvider(string $reply): void
    {
        $this->app->instance(ChatProvider::class, new class($reply) implements ChatProvider
        {
            public function __construct(private readonly string $reply) {}

            /** @param  list<ChatTurn>  $history */
            public function reply(string $message, array $history = [], ?string $model = null): string
            {
                return $this->reply;
            }
        });
    }

    private function configureProviders(?string $geminiKey = 'test-key'): void
    {
        config([
            'chatbot.provider' => 'ollama',
            'chatbot.available_providers' => 'ollama,gemini',
            'chatbot.providers.ollama.base_url' => 'http://localhost:11434',
            'chatbot.providers.ollama.models' => 'llama3.2,qwen3:8b',
            'chatbot.providers.ollama.max_attempts' => 1,
            'chatbot.providers.gemini.key' => $geminiKey,
            'chatbot.providers.gemini.models' => 'gemini-3.6-flash,gemini-3.5-flash-lite',
            'chatbot.providers.gemini.max_attempts' => 1,
        ]);
    }

    private function echoChosenModel(): void
    {
        $this->app->instance(ChatProvider::class, new class implements ChatProvider
        {
            public function reply(string $message, array $history = [], ?string $model = null): string
            {
                return 'answered by '.($model ?? 'default');
            }
        });
    }
}
