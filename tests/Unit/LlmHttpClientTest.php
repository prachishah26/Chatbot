<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Chat\Exceptions\ChatProviderException;
use App\Chat\Llm\LlmHttpClient;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class LlmHttpClientTest extends TestCase
{
    private const URL = 'https://llm.test/v1/chat';

    #[Test]
    public function it_posts_json_with_the_configured_headers_and_returns_the_body(): void
    {
        Http::fake([self::URL => Http::response(['answer' => 42])]);

        $body = $this->endpoint(headers: ['x-api-key' => 'secret'])->post(self::URL, ['q' => 'life']);

        $this->assertSame(['answer' => 42], $body);
        Http::assertSent(static fn (Request $request): bool => $request->hasHeader('x-api-key', 'secret')
            && $request->isJson()
            && $request->data() === ['q' => 'life']);
    }

    #[Test]
    public function it_retries_a_transient_failure_until_it_succeeds(): void
    {
        Http::fakeSequence(self::URL)
            ->push('overloaded', 503)
            ->push(['ok' => true]);

        $this->assertSame(['ok' => true], $this->endpoint(maxAttempts: 3)->post(self::URL, []));
        Http::assertSentCount(2);
    }

    #[Test]
    public function it_gives_up_after_the_last_attempt_with_the_upstream_status(): void
    {
        Http::fake([self::URL => Http::response('overloaded', 503)]);

        try {
            $this->endpoint(maxAttempts: 2)->post(self::URL, []);
            $this->fail('Expected a ChatProviderException.');
        } catch (ChatProviderException $e) {
            $this->assertSame(503, $e->status);
        }

        Http::assertSentCount(2);
    }

    #[Test]
    public function it_does_not_retry_a_client_error(): void
    {
        Http::fake([self::URL => Http::response('quota', 429)]);

        try {
            $this->endpoint(maxAttempts: 3)->post(self::URL, []);
            $this->fail('Expected a ChatProviderException.');
        } catch (ChatProviderException $e) {
            $this->assertTrue($e->isQuotaExhausted());
        }

        Http::assertSentCount(1);
    }

    #[Test]
    public function it_appends_the_hint_when_the_server_is_unreachable(): void
    {
        Http::fake(static fn () => throw new ConnectionException('Connection refused'));

        $this->expectException(ChatProviderException::class);
        $this->expectExceptionMessage('Connection refused Is the server up?');

        $this->endpoint(hint: 'Is the server up?')->post(self::URL, []);
    }

    /**
     * @param  array<string, string>  $headers
     */
    private function endpoint(int $maxAttempts = 1, array $headers = [], string $hint = ''): LlmHttpClient
    {
        return new LlmHttpClient($this->app->make(HttpFactory::class), 'test', 5, $maxAttempts, 0, $headers, $hint);
    }
}
