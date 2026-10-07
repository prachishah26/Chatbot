<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Chat\Contracts\ChatProvider;
use App\Chat\Llm\ModelSelectionService;
use App\Chat\Llm\Ollama\OllamaWarmer;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\TestCase;

final class ChatServiceProviderTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'chatbot.provider' => 'ollama',
            'chatbot.available_providers' => 'ollama,gemini',
            'chatbot.providers.ollama.models' => 'llama3.2',
            'chatbot.providers.gemini.models' => 'gemini-3.6-flash',
            'chatbot.providers.gemini.key' => 'test-key',
        ]);
    }

    #[Test]
    public function it_offers_every_usable_provider_with_the_default_first(): void
    {
        config(['chatbot.available_providers' => 'gemini,ollama']);

        $groups = $this->app->make(ModelSelectionService::class)->groupedOptions();

        $this->assertSame(['ollama', 'gemini'], array_keys($groups));
        $this->assertTrue($groups['ollama'][0]->isLocal());
        $this->assertSame('Gemini', $groups['gemini'][0]->providerLabel());
    }

    #[Test]
    public function it_hides_a_hosted_provider_without_credentials(): void
    {
        config(['chatbot.providers.gemini.key' => null]);

        $this->assertSame(['ollama/llama3.2'], $this->app->make(ModelSelectionService::class)->allowed());
    }

    #[Test]
    public function it_keeps_the_default_provider_even_without_credentials(): void
    {
        config(['chatbot.provider' => 'gemini', 'chatbot.providers.gemini.key' => null]);

        $this->assertSame(['gemini/gemini-3.6-flash', 'ollama/llama3.2'], $this->app->make(ModelSelectionService::class)->allowed());
    }

    #[Test]
    public function a_broken_gemini_block_without_a_key_does_not_break_an_ollama_install(): void
    {
        config([
            'chatbot.providers.gemini.key' => null,
            'chatbot.providers.gemini.base_url' => 'http://not-https.test',
        ]);

        $this->assertSame(['ollama/llama3.2'], $this->app->make(ModelSelectionService::class)->allowed());
        $this->assertInstanceOf(ChatProvider::class, $this->app->make(ChatProvider::class));
    }

    #[Test]
    public function it_ignores_an_unknown_provider_in_the_list(): void
    {
        config(['chatbot.available_providers' => 'ollama,openai']);

        $this->assertSame(['ollama/llama3.2'], $this->app->make(ModelSelectionService::class)->allowed());
    }

    #[Test]
    public function it_refuses_to_boot_with_an_unknown_default_provider(): void
    {
        config(['chatbot.provider' => 'openai']);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Unsupported chat provider [openai]');

        $this->app->make(ChatProvider::class);
    }

    #[Test]
    public function the_warmer_reads_the_ollama_settings(): void
    {
        config(['chatbot.providers.ollama.models' => 'qwen3:8b,llama3.2']);

        $this->assertSame('qwen3:8b', $this->app->make(OllamaWarmer::class)->defaultModel());
    }
}
