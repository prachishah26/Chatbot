<?php

declare(strict_types=1);

namespace App\Chat\Providers;

use InvalidArgumentException;

/**
 * Immutable, validated snapshot of the Gemini provider settings.
 */
final readonly class GeminiConfig
{
    /**
     * Gemini 3.x reasons before answering; "low" keeps chat replies snappy.
     */
    public const THINKING_LEVELS = ['low', 'high'];

    public function __construct(
        public ?string $key,
        /** @var list<string> Preferred model first. */
        public array $models,
        public string $baseUrl,
        public int $timeout,
        public float $temperature,
        public int $maxOutputTokens,
        public ?string $thinkingLevel = null,
        public int $maxAttempts = 3,
        public int $retryDelayMs = 500,
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
     * Reads the fallback chain, accepting either an array or a comma-separated
     * string so it can be configured straight from the environment.
     *
     * @param  array<string, mixed>  $config
     * @return list<string>
     */
    private static function modelList(array $config): array
    {
        $models = $config['models'] ?? $config['model'] ?? 'gemini-3.6-flash';

        if (is_string($models)) {
            $models = explode(',', $models);
        }

        $names = array_values(array_unique(array_filter(
            array_map(static fn (mixed $model): string => trim((string) $model), (array) $models),
            static fn (string $model): bool => $model !== '',
        )));

        return $names;
    }

    /**
     * @param  array<string, mixed>  $config
     */
    public static function fromArray(array $config): self
    {
        $key = $config['key'] ?? null;
        $thinkingLevel = $config['thinking_level'] ?? null;

        return new self(
            key: is_string($key) && $key !== '' ? $key : null,
            models: self::modelList($config),
            baseUrl: (string) ($config['base_url'] ?? 'https://generativelanguage.googleapis.com/v1beta'),
            timeout: max(1, (int) ($config['timeout'] ?? 60)),
            temperature: (float) ($config['temperature'] ?? 0.7),
            maxOutputTokens: max(1, (int) ($config['max_output_tokens'] ?? 1024)),
            thinkingLevel: is_string($thinkingLevel) && $thinkingLevel !== '' ? $thinkingLevel : null,
            maxAttempts: max(1, (int) ($config['max_attempts'] ?? 3)),
            retryDelayMs: max(0, (int) ($config['retry_delay_ms'] ?? 500)),
        );
    }
}
