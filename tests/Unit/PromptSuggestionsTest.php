<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Chat\Support\PromptSuggestions;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class PromptSuggestionsTest extends TestCase
{
    private const POOL = ['one', 'two', 'three', 'four', 'five', 'six'];

    #[Test]
    public function it_picks_the_requested_number_of_distinct_prompts(): void
    {
        $picked = PromptSuggestions::pick(self::POOL, 3);

        $this->assertCount(3, $picked);
        $this->assertSame($picked, array_values(array_unique($picked)));
        $this->assertSame([], array_diff($picked, self::POOL));
    }

    #[Test]
    public function it_varies_the_selection_between_calls(): void
    {
        $runs = [];

        for ($i = 0; $i < 40; $i++) {
            $runs[] = PromptSuggestions::pick(self::POOL, 3);
        }

        $this->assertGreaterThan(1, count(array_unique(array_map('serialize', $runs))));
    }

    #[Test]
    public function it_returns_every_prompt_when_the_pool_is_smaller_than_the_count(): void
    {
        $picked = PromptSuggestions::pick(['one', 'two'], 5);

        $this->assertCount(2, $picked);
        $this->assertSame(['one', 'two'], collect($picked)->sort()->values()->all());
    }

    #[Test]
    public function it_drops_blank_and_duplicate_entries(): void
    {
        $picked = PromptSuggestions::pick(['  Ask me  ', 'Ask me', '', '   '], 4);

        $this->assertSame(['Ask me'], $picked);
    }

    #[Test]
    public function it_returns_nothing_for_an_empty_pool_or_a_zero_count(): void
    {
        $this->assertSame([], PromptSuggestions::pick([], 3));
        $this->assertSame([], PromptSuggestions::pick(self::POOL, 0));
    }
}
