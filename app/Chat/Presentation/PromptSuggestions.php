<?php

declare(strict_types=1);

namespace App\Chat\Presentation;

/**
 * Chooses the starter prompts shown on an empty thread. A fresh selection is
 * drawn on every page load so the greeting does not look the same twice.
 */
final readonly class PromptSuggestions
{
    /**
     * Draws up to $count distinct prompts from the pool, in random order.
     *
     * @param  array<array-key, mixed>  $pool
     * @return list<string>
     */
    public static function pick(array $pool, int $count): array
    {
        $options = self::clean($pool);

        if ($options === [] || $count < 1) {
            return [];
        }

        $keys = (array) array_rand($options, min($count, count($options)));
        shuffle($keys);

        return array_map(static fn (int $key): string => $options[$key], $keys);
    }

    /**
     * Keeps only non-empty, trimmed, distinct strings; anything else in config
     * is ignored rather than rendered as a broken chip.
     *
     * @param  array<array-key, mixed>  $pool
     * @return list<string>
     */
    private static function clean(array $pool): array
    {
        $trimmed = array_map(
            static fn (mixed $prompt): string => is_string($prompt) ? trim($prompt) : '',
            array_values($pool),
        );

        return array_values(array_unique(array_filter(
            $trimmed,
            static fn (string $prompt): bool => $prompt !== '',
        )));
    }
}
