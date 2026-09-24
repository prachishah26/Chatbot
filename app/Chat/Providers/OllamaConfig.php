<?php

declare(strict_types=1);

namespace App\Chat\Providers;

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

    public function __construct(
        /** @var list<string> Preferred model first. */
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

        if (! self::isHttpUrl($baseUrl)) {
            throw new InvalidArgumentException('The Ollama base URL must be an http:// or https:// endpoint.');
        }

        if ($maxAttempts < 1) {
            throw new InvalidArgumentException('At least one Ollama request attempt is required.');
        }
    }

    /**
     * @param  array<string, mixed>  $config
     */
    public static function fromArray(array $config): self
    {
        $keepAlive = $config['keep_alive'] ?? null;

        return new self(
            models: self::modelList($config),
            baseUrl: (string) ($config['base_url'] ?? self::DEFAULT_BASE_URL),
            timeout: max(1, (int) ($config['timeout'] ?? 120)),
            temperature: (float) ($config['temperature'] ?? 0.7),
            maxOutputTokens: max(1, (int) ($config['max_output_tokens'] ?? 1024)),
            keepAlive: is_string($keepAlive) && $keepAlive !== '' ? $keepAlive : null,
            think: self::optionalBool($config['think'] ?? null),
            maxAttempts: max(1, (int) ($config['max_attempts'] ?? 2)),
            retryDelayMs: max(0, (int) ($config['retry_delay_ms'] ?? 500)),
        );
    }

    public static function isHttpUrl(string $url): bool
    {
        return str_starts_with($url, 'http://') || str_starts_with($url, 'https://');
    }

    /**
     * Reads the model list from an array or a comma-separated string.
     *
     * @param  array<string, mixed>  $config
     * @return list<string>
     */
    private static function modelList(array $config): array
    {
        $models = $config['models'] ?? $config['model'] ?? 'llama3.2';

        if (is_string($models)) {
            $models = explode(',', $models);
        }

        return array_values(array_unique(array_filter(
            array_map(static fn (mixed $model): string => trim((string) $model), (array) $models),
            static fn (string $model): bool => $model !== '',
        )));
    }

    /**
     * Blank means "let the model decide", so thinking is only sent when set.
     */
    private static function optionalBool(mixed $value): ?bool
    {
        if ($value === null || $value === '') {
            return null;
        }

        return filter_var($value, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE);
    }
}
