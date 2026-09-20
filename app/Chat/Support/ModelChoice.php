<?php

declare(strict_types=1);

namespace App\Chat\Support;

/**
 * One selectable model, paired with a label fit for the picker.
 */
final readonly class ModelChoice
{
    public function __construct(
        public string $id,
        public string $label,
    ) {}

    public static function fromId(string $id): self
    {
        return new self($id, self::humanise($id));
    }

    /**
     * @param  list<string>  $ids
     * @return list<self>
     */
    public static function listFrom(array $ids): array
    {
        return array_map(static fn (string $id): self => self::fromId($id), array_values($ids));
    }

    /**
     * "gemini-3.5-flash-lite" reads better as "Gemini 3.5 Flash Lite".
     */
    private static function humanise(string $id): string
    {
        $words = array_map(
            static fn (string $part): string => ucfirst($part),
            explode('-', $id),
        );

        return implode(' ', $words);
    }
}
