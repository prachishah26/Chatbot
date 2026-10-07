<?php

declare(strict_types=1);

namespace App\Chat\Llm\Gemini;

use App\Chat\Llm\ConfigValues;
use InvalidArgumentException;

/**
 * Immutable, validated snapshot of the Gemini provider settings.
 */
final readonly class GeminiConfig
{
    public const DEFAULT_BASE_URL = 'https://generativelanguage.googleapis.com/v1beta';

    public const DEFAULT_MODEL = 'gemini-3.6-flash';

    /**
     * Gemini 3.x reasons before answering; "low" keeps chat replies snappy.
     */
    public const THINKING_LEVELS = ['low', 'high'];

    /**
     * @param  list<string>  $models  Preferred model first.
     */
    public function __construct(
        public ?string $key,
        public array $models,
        public string $baseUrl,
        public int $timeout,
        public float $temperature,
        public int $maxOutputTokens,
        public ?string $thinkingLevel = null,
        public int $maxAttempts = 3,
        public int $retryDelayMs = 500,
        public int $cooldownSeconds = 1800,
    ) {
        if ($models === []) {
            throw new InvalidArgumentException('At least one Gemini model name is required.');
        }

        if (! str_starts_with($baseUrl, 'https://')) {
            throw new InvalidArgumentException('The Gemini base URL must be an https:// endpoint.');
        }

        if ($thinkingLevel !== null && ! in_array($thinkingLevel, self::THINKING_LEVELS, true)) {
            throw new InvalidArgumentException(
                'The Gemini thinking level must be one of: '.implode(', ', self::THINKING_LEVELS).'.',
            );
        }

        if ($maxAttempts < 1) {
            throw new InvalidArgumentException('At least one Gemini request attempt is required.');
        }
    }

    /**
     * @param  array<string, mixed>  $config  The `chatbot.providers.gemini` block.
     */
    public static function fromArray(array $config): self
    {
        return new self(
            key: ConfigValues::optionalString($config['key'] ?? null),
            models: ConfigValues::list($config['models'] ?? $config['model'] ?? self::DEFAULT_MODEL),
            baseUrl: (string) ($config['base_url'] ?? self::DEFAULT_BASE_URL),
            timeout: max(1, (int) ($config['timeout'] ?? 60)),
            temperature: (float) ($config['temperature'] ?? 0.7),
            maxOutputTokens: max(1, (int) ($config['max_output_tokens'] ?? 1024)),
            thinkingLevel: ConfigValues::optionalString($config['thinking_level'] ?? null),
            maxAttempts: max(1, (int) ($config['max_attempts'] ?? 3)),
            retryDelayMs: max(0, (int) ($config['retry_delay_ms'] ?? 500)),
            cooldownSeconds: max(0, (int) ($config['model_cooldown_minutes'] ?? 30)) * 60,
        );
    }

    public function hasKey(): bool
    {
        return $this->key !== null && $this->key !== '';
    }
}
