<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Chat\Presentation\ConversationTimeline;
use App\Models\Conversation;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class ConversationTimelineTest extends TestCase
{
    private Carbon $now;

    #[Test]
    public function it_always_offers_a_today_bucket(): void
    {
        $groups = ConversationTimeline::group(new Collection, $this->now);

        $this->assertSame(['Today' => []], $groups);
    }

    #[Test]
    public function it_files_threads_under_the_right_heading(): void
    {
        $groups = ConversationTimeline::group(new Collection([
            $this->threadUpdated($this->now->copy()->subHours(2)),
            $this->threadUpdated($this->now->copy()->subDay()),
            $this->threadUpdated($this->now->copy()->subDays(4)),
            $this->threadUpdated($this->now->copy()->subDays(20)),
            $this->threadUpdated($this->now->copy()->subDays(200)),
        ]), $this->now);

        $this->assertSame(
            ['Today', 'Yesterday', 'Previous 7 days', 'Previous 30 days', 'Older'],
            array_keys($groups),
        );

        foreach ($groups as $heading => $threads) {
            $this->assertCount(1, $threads, "One thread belongs under [{$heading}].");
        }
    }

    #[Test]
    public function it_drops_empty_buckets_other_than_today(): void
    {
        $groups = ConversationTimeline::group(
            new Collection([$this->threadUpdated($this->now->copy()->subDays(3))]),
            $this->now,
        );

        $this->assertSame(['Today', 'Previous 7 days'], array_keys($groups));
        $this->assertSame([], $groups['Today']);
    }

    #[Test]
    public function it_counts_by_calendar_day_not_elapsed_hours(): void
    {
        // Ten minutes earlier, but on the previous calendar day.
        $groups = ConversationTimeline::group(
            new Collection([$this->threadUpdated($this->now->copy()->startOfDay()->subMinutes(10))]),
            $this->now,
        );

        $this->assertSame([], $groups['Today']);
        $this->assertCount(1, $groups['Yesterday']);
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->now = Carbon::parse('2026-09-16 10:00:00');
    }

    private function threadUpdated(Carbon $updatedAt): Conversation
    {
        $conversation = new Conversation;
        $conversation->updated_at = $updatedAt;

        return $conversation;
    }
}
