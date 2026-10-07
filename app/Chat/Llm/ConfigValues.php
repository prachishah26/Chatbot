<?php

declare(strict_types=1);

namespace App\Chat\Llm;

/**
 * Normalises raw values from config/chatbot.php (which mostly come straight
 * from the environment) into the types the provider configs need.
 */
final class ConfigValues
{
    /**
     * Reads a list from an array or a comma-separated string, dropping blanks
     * and duplicates while keeping the configured order.
     *
     * @return list<string>
     */
    public static function list(mixed $value): array
    {
        if (is_string($value)) {
            $value = explode(',', $value);
        }

        return array_values(array_unique(array_filter(
            array_map(static fn (mixed $item): string => trim((string) $item), (array) $value),
            static fn (string $item): bool => $item !== '',
        )));
    }

    /**
     * A blank string counts as "not set".
     */
    public static function optionalString(mixed $value): ?string
    {
        return is_string($value) && trim($value) !== '' ? $value : null;
    }

    /**
     * Blank means "let the upstream decide"; anything unrecognisable is treated the same.
     */
    public static function optionalBool(mixed $value): ?bool
    {
        if ($value === null || $value === '') {
            return null;
        }

        return filter_var($value, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE);
    }

    public static function isHttpUrl(string $url): bool
    {
        return str_starts_with($url, 'http://') || str_starts_with($url, 'https://');
    }
}
