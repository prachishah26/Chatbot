<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Chat\Exceptions\ChatProviderException;
use App\Chat\Providers\ModelPool;
use App\Chat\Providers\OllamaChatProvider;
use App\Chat\Providers\OllamaConfig;
use App\Chat\Support\ChatTurn;
use Illuminate\Cache\ArrayStore;
use Illuminate\Cache\Repository as CacheRepository;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class OllamaChatProviderTest extends TestCase
{
    private const ENDPOINT = 'http://localhost:11434/api/chat';

    #[Test]
    public function it_returns_the_trimmed_reply_text(): void
    {
        Http::fake([self::ENDPOINT => Http::response($this->ollamaResponse('  Hello!  '))]);

        $this->assertSame('Hello!', $this->provider()->reply('Hi'));
    }

    #[Test]
    public function it_sends_the_system_prompt_history_and_new_message(): void
    {
        Http::fake([self::ENDPOINT => Http::response($this->ollamaResponse('ok'))]);

        $this->provider()->reply('How are you?', [
            ChatTurn::user('Hello'),
            ChatTurn::assistant('Hi there'),
        ]);

        Http::assertSent(function (Request $request): bool {
            $body = $request->data();

            $this->assertSame('llama3.2', $body['model']);
            $this->assertFalse($body['stream']);
            $this->assertSame([
                ['role' => 'system', 'content' => 'Be brief.'],
                ['role' => 'user', 'content' => 'Hello'],
                ['role' => 'assistant', 'content' => 'Hi there'],
                ['role' => 'user', 'content' => 'How are you?'],
            ], $body['messages']);
            $this->assertSame(['temperature' => 0.7, 'num_predict' => 1024], $body['options']);
            $this->assertSame('10m', $body['keep_alive']);
            $this->assertArrayNotHasKey('think', $body, 'Thinking is only sent when configured.');

            return true;
        });
    }

    #[Test]
    public function it_sends_the_thinking_flag_when_configured(): void
    {
        Http::fake([self::ENDPOINT => Http::response($this->ollamaResponse('ok'))]);

        $this->provider(think: 'false')->reply('Hi');

        Http::assertSent(fn (Request $request): bool => $request->data()['think'] === false);
    }

    #[Test]
    public function it_asks_the_chosen_model_first(): void
    {
        Http::fake([self::ENDPOINT => Http::response($this->ollamaResponse('ok'))]);

        $this->provider(models: ['llama3.2', 'qwen3:8b'])->reply('Hi', [], 'qwen3:8b');

        Http::assertSent(fn (Request $request): bool => $request->data()['model'] === 'qwen3:8b');
    }

    #[Test]
    public function it_falls_back_when_a_model_has_not_been_pulled(): void
    {
        Http::fake([self::ENDPOINT => Http::sequence()
            ->push(['error' => "model 'llama3.2' not found"], 404)
            ->push($this->ollamaResponse('from the fallback'))]);

        $reply = $this->provider(models: ['llama3.2', 'qwen3:8b'])->reply('Hi');

        $this->assertSame('from the fallback', $reply);
        Http::assertSentCount(2);
    }

    #[Test]
    public function it_does_not_mutate_the_history_it_is_given(): void
    {
        Http::fake([self::ENDPOINT => Http::response($this->ollamaResponse('ok'))]);

        $history = [ChatTurn::user('Hello')];
        $copy = $history;

        $this->provider()->reply('Next', $history);

        $this->assertEquals($copy, $history);
    }

    #[Test]
    public function it_reports_an_http_failure(): void
    {
        Http::fake([self::ENDPOINT => Http::response(['error' => 'bad request'], 400)]);

        $this->expectException(ChatProviderException::class);
        $this->expectExceptionMessage('HTTP 400');

        $this->provider()->reply('Hi');
    }

    #[Test]
    public function it_retries_a_transient_server_error(): void
    {
        Http::fake([self::ENDPOINT => Http::sequence()
            ->push(['error' => 'loading model'], 503)
            ->push($this->ollamaResponse('ready now'))]);

        $this->assertSame('ready now', $this->provider(maxAttempts: 2)->reply('Hi'));
    }

    #[Test]
    public function it_reports_an_unreachable_server(): void
    {
        Http::fake(static fn () => throw new ConnectionException('Connection refused'));

        $this->expectException(ChatProviderException::class);
        $this->expectExceptionMessage('ollama serve');

        $this->provider()->reply('Hi');
    }

    #[Test]
    public function it_reports_an_empty_reply(): void
    {
        Http::fake([self::ENDPOINT => Http::response([
            'message' => ['role' => 'assistant', 'content' => ''],
            'done_reason' => 'length',
        ])]);

        $this->expectException(ChatProviderException::class);
        $this->expectExceptionMessage('Done reason: length.');

        $this->provider()->reply('Hi');
    }

    #[Test]
    public function it_reads_a_comma_separated_model_list(): void
    {
        $config = OllamaConfig::fromArray(['models' => ' llama3.2 , qwen3:8b,,llama3.2']);

        $this->assertSame(['llama3.2', 'qwen3:8b'], $config->models);
    }

    #[Test]
    public function it_accepts_a_plain_http_local_endpoint(): void
    {
        $config = OllamaConfig::fromArray(['base_url' => 'http://127.0.0.1:11434']);

        $this->assertSame('http://127.0.0.1:11434', $config->baseUrl);
    }

    #[Test]
    public function it_rejects_a_non_http_base_url(): void
    {
        $this->expectException(InvalidArgumentException::class);

        OllamaConfig::fromArray(['base_url' => 'file:///etc/passwd']);
    }

    #[Test]
    public function it_rejects_an_empty_model_list(): void
    {
        $this->expectException(InvalidArgumentException::class);

        OllamaConfig::fromArray(['models' => ' , ']);
    }

    /**
     * @param  list<string>  $models
     */
    private function provider(
        array $models = ['llama3.2'],
        ?string $think = null,
        int $maxAttempts = 1,
    ): OllamaChatProvider {
        $config = OllamaConfig::fromArray([
            'base_url' => 'http://localhost:11434',
            'models' => $models,
            'timeout' => 5,
            'temperature' => 0.7,
            'max_output_tokens' => 1024,
            'keep_alive' => '10m',
            'think' => $think,
            'max_attempts' => $maxAttempts,
            'retry_delay_ms' => 0,
        ]);

        return new OllamaChatProvider(
            $this->app->make(HttpFactory::class),
            $config,
            new ModelPool(new CacheRepository(new ArrayStore), $config->models, 0),
            'Be brief.',
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function ollamaResponse(string $text): array
    {
        return [
            'model' => 'llama3.2',
            'message' => ['role' => 'assistant', 'content' => $text],
            'done' => true,
            'done_reason' => 'stop',
        ];
    }
}
