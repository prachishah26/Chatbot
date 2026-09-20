<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Chat\Exceptions\ChatProviderException;
use App\Chat\Providers\GeminiChatProvider;
use App\Chat\Providers\GeminiConfig;
use App\Chat\Providers\ModelPool;
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

final class GeminiChatProviderTest extends TestCase
{
    private const ENDPOINT = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-3.6-flash:generateContent';

    private const FALLBACK_ENDPOINT = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-3.5-flash-lite:generateContent';

    #[Test]
    public function it_returns_the_reply_text(): void
    {
        Http::fake([self::ENDPOINT => Http::response($this->geminiResponse('Hello!'))]);

        $this->assertSame('Hello!', $this->provider()->reply('Hi'));
    }

    #[Test]
    public function it_joins_multi_part_replies_and_trims_them(): void
    {
        Http::fake([self::ENDPOINT => Http::response([
            'candidates' => [['content' => ['parts' => [['text' => '  one '], ['text' => 'two  ']]]]],
        ])]);

        $this->assertSame('one two', $this->provider()->reply('Hi'));
    }

    #[Test]
    public function it_sends_the_system_prompt_history_and_new_message(): void
    {
        Http::fake([self::ENDPOINT => Http::response($this->geminiResponse('ok'))]);

        $this->provider()->reply('How are you?', [
            ChatTurn::user('Hello'),
            ChatTurn::assistant('Hi there'),
        ]);

        Http::assertSent(function (Request $request): bool {
            $body = $request->data();

            $this->assertSame('Be brief.', $body['systemInstruction']['parts'][0]['text']);
            $this->assertSame([
                ['role' => 'user', 'parts' => [['text' => 'Hello']]],
                ['role' => 'model', 'parts' => [['text' => 'Hi there']]],
                ['role' => 'user', 'parts' => [['text' => 'How are you?']]],
            ], $body['contents']);
            $this->assertSame(0.7, $body['generationConfig']['temperature']);
            $this->assertSame(1024, $body['generationConfig']['maxOutputTokens']);
            $this->assertSame('low', $body['generationConfig']['thinkingConfig']['thinkingLevel']);

            return $request->hasHeader('x-goog-api-key', 'test-key');
        });
    }

    #[Test]
    public function it_does_not_mutate_the_history_it_is_given(): void
    {
        Http::fake([self::ENDPOINT => Http::response($this->geminiResponse('ok'))]);

        $history = [ChatTurn::user('Hello')];
        $this->provider()->reply('Next', $history);

        $this->assertCount(1, $history);
        $this->assertSame('Hello', $history[0]->text);
    }

    #[Test]
    public function it_fails_fast_without_an_api_key(): void
    {
        Http::fake();

        $this->expectException(ChatProviderException::class);

        try {
            $this->provider(key: null)->reply('Hi');
        } finally {
            Http::assertNothingSent();
        }
    }

    #[Test]
    public function it_reports_an_http_failure(): void
    {
        Http::fake([self::ENDPOINT => Http::response(['error' => ['message' => 'API key not valid']], 400)]);

        $this->expectException(ChatProviderException::class);
        $this->expectExceptionMessageMatches('/returned HTTP 400/');

        $this->provider()->reply('Hi');
    }

    #[Test]
    public function it_reports_an_unreachable_provider(): void
    {
        Http::fake(fn () => throw new ConnectionException('timed out'));

        $this->expectException(ChatProviderException::class);
        $this->expectExceptionMessageMatches('/Could not reach/');

        $this->provider()->reply('Hi');
    }

    #[Test]
    public function it_reports_a_blocked_or_empty_reply(): void
    {
        Http::fake([self::ENDPOINT => Http::response([
            'candidates' => [['content' => ['parts' => []], 'finishReason' => 'SAFETY']],
        ])]);

        $this->expectException(ChatProviderException::class);
        $this->expectExceptionMessageMatches('/SAFETY/');

        $this->provider()->reply('Hi');
    }

    #[Test]
    public function it_reads_a_comma_separated_model_chain(): void
    {
        $config = GeminiConfig::fromArray(['models' => 'first, second ,, first ']);

        $this->assertSame(['first', 'second'], $config->models);
    }

    #[Test]
    public function it_rejects_an_empty_model_chain(): void
    {
        $this->expectException(InvalidArgumentException::class);

        GeminiConfig::fromArray(['models' => '  ,  ']);
    }

    #[Test]
    public function it_rejects_an_insecure_base_url(): void
    {
        $this->expectException(InvalidArgumentException::class);

        GeminiConfig::fromArray(['base_url' => 'http://generativelanguage.googleapis.com/v1beta']);
    }

    #[Test]
    public function it_treats_a_blank_key_as_missing(): void
    {
        $this->assertNull(GeminiConfig::fromArray(['key' => ''])->key);
    }

    #[Test]
    public function it_omits_the_thinking_config_when_no_level_is_set(): void
    {
        Http::fake([self::ENDPOINT => Http::response($this->geminiResponse('ok'))]);

        $this->provider(thinkingLevel: null)->reply('Hi');

        Http::assertSent(fn (Request $request): bool => ! isset($request->data()['generationConfig']['thinkingConfig']));
    }

    #[Test]
    public function it_rejects_an_unknown_thinking_level(): void
    {
        $this->expectException(InvalidArgumentException::class);

        GeminiConfig::fromArray(['thinking_level' => 'medium']);
    }

    #[Test]
    public function it_retries_when_the_free_tier_sheds_load(): void
    {
        Http::fakeSequence()
            ->push(['error' => ['message' => 'high demand']], 503)
            ->push($this->geminiResponse('Recovered!'));

        $this->assertSame('Recovered!', $this->provider(maxAttempts: 3)->reply('Hi'));

        Http::assertSentCount(2);
    }

    #[Test]
    public function it_retries_a_timeout_mid_generation(): void
    {
        $attempts = 0;

        Http::fake(function () use (&$attempts) {
            $attempts++;

            if ($attempts === 1) {
                throw new ConnectionException('Operation timed out after 60001 milliseconds');
            }

            return Http::response($this->geminiResponse('Second try'));
        });

        $this->assertSame('Second try', $this->provider(maxAttempts: 2)->reply('Hi'));
        $this->assertSame(2, $attempts);
    }

    #[Test]
    public function it_falls_back_to_the_next_model_when_a_quota_is_exhausted(): void
    {
        Http::fake([
            self::ENDPOINT => Http::response(['error' => ['message' => 'quota exceeded']], 429),
            self::FALLBACK_ENDPOINT => Http::response($this->geminiResponse('From the backup model')),
        ]);

        $reply = $this->provider(models: ['gemini-3.6-flash', 'gemini-3.5-flash-lite'])->reply('Hi');

        $this->assertSame('From the backup model', $reply);
        Http::assertSentCount(2);
    }

    #[Test]
    public function it_falls_back_when_a_model_has_been_retired(): void
    {
        Http::fake([
            self::ENDPOINT => Http::response(['error' => ['message' => 'no longer available']], 404),
            self::FALLBACK_ENDPOINT => Http::response($this->geminiResponse('Still here')),
        ]);

        $this->assertSame(
            'Still here',
            $this->provider(models: ['gemini-3.6-flash', 'gemini-3.5-flash-lite'])->reply('Hi'),
        );
    }

    #[Test]
    public function it_skips_a_model_it_has_already_seen_run_out(): void
    {
        $config = GeminiConfig::fromArray([
            'key' => 'test-key',
            'models' => ['gemini-3.6-flash', 'gemini-3.5-flash-lite'],
            'retry_delay_ms' => 0,
            'max_attempts' => 1,
        ]);

        $pool = new ModelPool(new CacheRepository(new ArrayStore), $config->models, 1800);
        $pool->park('gemini-3.6-flash');

        Http::fake([self::FALLBACK_ENDPOINT => Http::response($this->geminiResponse('Straight to backup'))]);

        $provider = new GeminiChatProvider($this->app->make(HttpFactory::class), $config, $pool, 'Be brief.');

        $this->assertSame('Straight to backup', $provider->reply('Hi'));
        Http::assertSentCount(1);
    }

    #[Test]
    public function it_reports_the_last_failure_when_every_model_is_exhausted(): void
    {
        Http::fake([
            self::ENDPOINT => Http::response(['error' => ['message' => 'quota exceeded']], 429),
            self::FALLBACK_ENDPOINT => Http::response(['error' => ['message' => 'quota exceeded']], 429),
        ]);

        $this->expectException(ChatProviderException::class);
        $this->expectExceptionMessageMatches('/returned HTTP 429/');

        $this->provider(models: ['gemini-3.6-flash', 'gemini-3.5-flash-lite'])->reply('Hi');
    }

    #[Test]
    public function it_gives_up_after_the_configured_attempts(): void
    {
        Http::fake([self::ENDPOINT => Http::response(['error' => ['message' => 'high demand']], 503)]);

        $this->expectException(ChatProviderException::class);
        $this->expectExceptionMessageMatches('/returned HTTP 503/');

        try {
            $this->provider(maxAttempts: 3)->reply('Hi');
        } finally {
            Http::assertSentCount(3);
        }
    }

    #[Test]
    public function it_does_not_retry_a_client_error(): void
    {
        Http::fake([self::ENDPOINT => Http::response(['error' => ['message' => 'API key not valid']], 400)]);

        try {
            $this->provider(maxAttempts: 3)->reply('Hi');
            $this->fail('Expected a ChatProviderException.');
        } catch (ChatProviderException) {
            Http::assertSentCount(1);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function geminiResponse(string $text): array
    {
        return ['candidates' => [['content' => ['parts' => [['text' => $text]]]]]];
    }

    /**
     * @param  list<string>  $models
     */
    private function provider(
        ?string $key = 'test-key',
        ?string $thinkingLevel = 'low',
        int $maxAttempts = 1,
        array $models = ['gemini-3.6-flash'],
    ): GeminiChatProvider {
        $config = GeminiConfig::fromArray([
            'key' => $key,
            'models' => $models,
            'base_url' => 'https://generativelanguage.googleapis.com/v1beta',
            'timeout' => 5,
            'temperature' => 0.7,
            'max_output_tokens' => 1024,
            'thinking_level' => $thinkingLevel,
            'max_attempts' => $maxAttempts,
            'retry_delay_ms' => 0,
        ]);

        return new GeminiChatProvider(
            $this->app->make(HttpFactory::class),
            $config,
            new ModelPool(new CacheRepository(new ArrayStore), $config->models, 1800),
            'Be brief.',
        );
    }
}
