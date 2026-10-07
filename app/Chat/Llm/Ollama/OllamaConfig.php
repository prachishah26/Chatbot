<?php

declare(strict_types=1);

namespace App\Chat\Llm\Ollama;

use App\Chat\Llm\ConfigValues;
use InvalidArgumentException;

/**
 * Immutable, validated snapshot of the Ollama provider settings.
 *
 * Ollama runs on the same machine or private network, so plain http:// is
 * accepted here, unlike the hosted providers.
 */
final readonly class OllamaConfig
{
    public const DEFAULT_BASE_URL = 'http://localhost:11434';

    public const DEFAULT_MODEL = 'llama3.2';

    /**
     * @param  list<string>  $models  Preferred model first.
     */
    public function __construct(
        public array $models,
        public string $baseUrl,
        public int $timeout,
        public float $temperature,
        public int $maxOutputTokens,
        public ?string $keepAlive = null,
        public ?bool $think = null,
        public int $maxAttempts = 2,
        public int $retryDelayMs = 500,
    ) {
        if ($models === []) {
            throw new InvalidArgumentException('At least one Ollama model name is required.');
        }

        if (! ConfigValues::isHttpUrl($baseUrl)) {
            throw new InvalidArgumentException('The Ollama base URL must be an http:// or https:// endpoint.');
        }

        if ($maxAttempts < 1) {
            throw new InvalidArgumentException('At least one Ollama request attempt is required.');
        }
    }

    /**
     * @param  array<string, mixed>  $config  The `chatbot.providers.ollama` block.
     */
    public static function fromArray(array $config): self
    {
        return new self(
            models: ConfigValues::list($config['models'] ?? $config['model'] ?? self::DEFAULT_MODEL),
            baseUrl: (string) ($config['base_url'] ?? self::DEFAULT_BASE_URL),
            timeout: max(1, (int) ($config['timeout'] ?? 120)),
            temperature: (float) ($config['temperature'] ?? 0.7),
            maxOutputTokens: max(1, (int) ($config['max_output_tokens'] ?? 1024)),
            keepAlive: ConfigValues::optionalString($config['keep_alive'] ?? null),
            think: ConfigValues::optionalBool($config['think'] ?? null),
            maxAttempts: max(1, (int) ($config['max_attempts'] ?? 2)),
            retryDelayMs: max(0, (int) ($config['retry_delay_ms'] ?? 500)),
        );
    }

    /**
     * The model a visitor gets unless they pick another.
     */
    public function defaultModel(): string
    {
        return $this->models[0];
    }

    public function url(string $path): string
    {
        return rtrim($this->baseUrl, '/').$path;
    }
}
