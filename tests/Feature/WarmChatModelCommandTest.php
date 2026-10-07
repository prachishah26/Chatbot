<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class WarmChatModelCommandTest extends TestCase
{
    private const ENDPOINT = 'http://localhost:11434/api/generate';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'chatbot.provider' => 'ollama',
            'chatbot.providers.ollama.base_url' => 'http://localhost:11434',
            'chatbot.providers.ollama.models' => 'llama3.2,qwen3:8b',
            'chatbot.providers.ollama.keep_alive' => '10m',
        ]);
    }

    #[Test]
    public function it_loads_the_default_model_into_memory(): void
    {
        Http::fake([self::ENDPOINT => Http::response(['model' => 'llama3.2', 'done' => true, 'done_reason' => 'load'])]);

        $this->artisan('chat:warm')
            ->expectsOutputToContain('llama3.2 is loaded')
            ->assertSuccessful();

        Http::assertSentCount(1);
        Http::assertSent(function (Request $request): bool {
            $this->assertSame(self::ENDPOINT, $request->url());
            $this->assertSame(['model' => 'llama3.2', 'keep_alive' => '10m'], $request->data());

            return true;
        });
    }

    #[Test]
    public function it_does_not_block_the_dev_server_when_ollama_is_down(): void
    {
        Http::fake(static fn () => throw new ConnectionException('Connection refused'));

        $this->artisan('chat:warm')
            ->expectsOutputToContain('Could not preload llama3.2')
            ->assertSuccessful();
    }

    #[Test]
    public function it_reports_a_model_that_has_not_been_pulled(): void
    {
        Http::fake([self::ENDPOINT => Http::response(['error' => "model 'llama3.2' not found"], 404)]);

        $this->artisan('chat:warm')
            ->expectsOutputToContain('ollama pull llama3.2')
            ->assertSuccessful();
    }

    #[Test]
    public function it_skips_hosted_providers(): void
    {
        config(['chatbot.provider' => 'gemini']);
        Http::fake();

        $this->artisan('chat:warm')
            ->expectsOutputToContain('Nothing to warm')
            ->assertSuccessful();

        Http::assertNothingSent();
    }

    #[Test]
    public function a_hosted_only_install_skips_warming_even_with_blank_ollama_settings(): void
    {
        config(['chatbot.provider' => 'gemini', 'chatbot.providers.ollama.base_url' => '']);

        $this->artisan('chat:warm')
            ->expectsOutputToContain('Nothing to warm')
            ->assertSuccessful();
    }
}
