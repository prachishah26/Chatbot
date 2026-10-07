<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Chat\Llm\Gemini\GeminiConfig;
use App\Chat\Llm\Ollama\OllamaConfig;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ProviderConfigTest extends TestCase
{
    #[Test]
    public function ollama_falls_back_to_sensible_defaults(): void
    {
        $config = OllamaConfig::fromArray([]);

        $this->assertSame(['llama3.2'], $config->models);
        $this->assertSame('llama3.2', $config->defaultModel());
        $this->assertSame('http://localhost:11434/api/chat', $config->url('/api/chat'));
        $this->assertNull($config->keepAlive);
        $this->assertNull($config->think);
    }

    #[Test]
    public function ollama_reads_values_as_they_arrive_from_the_environment(): void
    {
        $config = OllamaConfig::fromArray([
            'base_url' => 'http://ollama.internal:11434/',
            'models' => 'qwen3:8b, llama3.2',
            'keep_alive' => '10m',
            'think' => 'false',
            'max_attempts' => '0',
        ]);

        $this->assertSame(['qwen3:8b', 'llama3.2'], $config->models);
        $this->assertSame('http://ollama.internal:11434/api/generate', $config->url('/api/generate'));
        $this->assertSame('10m', $config->keepAlive);
        $this->assertFalse($config->think);
        $this->assertSame(1, $config->maxAttempts, 'At least one attempt is always made.');
    }

    #[Test]
    public function ollama_rejects_a_non_http_base_url(): void
    {
        $this->expectException(InvalidArgumentException::class);

        OllamaConfig::fromArray(['base_url' => 'file:///var/run/ollama.sock']);
    }

    #[Test]
    public function ollama_requires_at_least_one_model(): void
    {
        $this->expectException(InvalidArgumentException::class);

        OllamaConfig::fromArray(['models' => ' , ']);
    }

    #[Test]
    public function gemini_treats_a_blank_key_as_missing(): void
    {
        $this->assertFalse(GeminiConfig::fromArray(['key' => ''])->hasKey());
        $this->assertTrue(GeminiConfig::fromArray(['key' => 'abc'])->hasKey());
    }

    #[Test]
    public function gemini_converts_the_cooldown_to_seconds(): void
    {
        $this->assertSame(600, GeminiConfig::fromArray(['model_cooldown_minutes' => '10'])->cooldownSeconds);
    }

    #[Test]
    public function gemini_only_talks_to_an_https_endpoint(): void
    {
        $this->expectException(InvalidArgumentException::class);

        GeminiConfig::fromArray(['base_url' => 'http://generativelanguage.googleapis.com/v1beta']);
    }

    #[Test]
    public function gemini_rejects_an_unknown_thinking_level(): void
    {
        $this->expectException(InvalidArgumentException::class);

        GeminiConfig::fromArray(['thinking_level' => 'extreme']);
    }
}
