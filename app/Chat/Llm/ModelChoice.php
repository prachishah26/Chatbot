<?php

declare(strict_types=1);

namespace App\Chat\Llm;

/**
 * One selectable model, paired with a label fit for the picker.
 *
 * The id carries the provider as well as the model ("ollama/llama3.2"), so a
 * single choice tells the app both where to send a message and which model to
 * ask for.
 */
final readonly class ModelChoice
{
    private const SEPARATOR = '/';

    public function __construct(
        public string $id,
        public string $label,
        public string $provider,
        public string $model,
        private string $providerLabel,
        private bool $local,
    ) {}

    public static function for(string $provider, string $model, ?string $providerLabel = null, bool $isLocal = false): self
    {
        return new self(
            $provider.self::SEPARATOR.$model,
            self::humanise($model),
            $provider,
            $model,
            $providerLabel ?? ucfirst($provider),
            $isLocal,
        );
    }

    /**
     * @param  list<string>  $models
     * @return list<self>
     */
    public static function listFor(string $provider, array $models, ?string $providerLabel = null, bool $isLocal = false): array
    {
        return array_map(
            static fn (string $model): self => self::for($provider, $model, $providerLabel, $isLocal),
            array_values($models),
        );
    }

    /**
     * Splits an id into provider and model. Only the first separator counts,
     * because Ollama model names may themselves contain one ("hf.co/org/model").
     *
     * @return array{0: string, 1: string}|null
     */
    public static function parse(string $id): ?array
    {
        $parts = explode(self::SEPARATOR, $id, 2);

        if (count($parts) !== 2 || $parts[0] === '' || $parts[1] === '') {
            return null;
        }

        return [$parts[0], $parts[1]];
    }

    public function providerLabel(): string
    {
        return $this->providerLabel;
    }

    /**
     * Whether prompts sent to this model stay on this machine.
     */
    public function isLocal(): bool
    {
        return $this->local;
    }

    /**
     * "gemini-3.5-flash-lite" reads better as "Gemini 3.5 Flash Lite", and
     * Ollama's "llama3.2:3b" as "Llama3.2 3b".
     */
    private static function humanise(string $model): string
    {
        $words = array_map(
            static fn (string $part): string => ucfirst($part),
            preg_split('/[-:]/', $model) ?: [$model],
        );

        return implode(' ', $words);
    }
}
