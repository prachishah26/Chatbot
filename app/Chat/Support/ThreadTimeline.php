<?php

declare(strict_types=1);

namespace App\Chat\Support;

use App\Models\Conversation;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * Buckets threads into the date headings shown in the sidebar.
 */
final readonly class ThreadTimeline
{
    public const TODAY = 'Today';

    private const YESTERDAY = 'Yesterday';

    private const LAST_WEEK = 'Previous 7 days';

    private const LAST_MONTH = 'Previous 30 days';

    private const OLDER = 'Older';

    /**
     * Groups threads under their heading, newest bucket first. "Today" is always
     * present so a thread created after page load has somewhere to go.
     *
     * @param  Collection<int, Conversation>  $conversations
     * @return array<string, list<Conversation>>
     */
    public static function group(Collection $conversations, CarbonInterface $now): array
    {
        $groups = [self::TODAY => []];

        foreach ($conversations as $conversation) {
            $groups[self::headingFor($conversation, $now)][] = $conversation;
        }

        return array_filter(
            $groups,
            static fn (array $threads, string $heading): bool => $threads !== [] || $heading === self::TODAY,
            ARRAY_FILTER_USE_BOTH,
        );
    }

    private static function headingFor(Conversation $conversation, CarbonInterface $now): string
    {
        $updatedAt = $conversation->updated_at;
        $days = $updatedAt->startOfDay()->diffInDays($now->copy()->startOfDay());

        return match (true) {
            $days < 1 => self::TODAY,
            $days < 2 => self::YESTERDAY,
            $days < 8 => self::LAST_WEEK,
            $days < 31 => self::LAST_MONTH,
            default => self::OLDER,
        };
    }
}
