<?php

declare(strict_types=1);

namespace App\Chat\Providers;

use Illuminate\Contracts\Cache\Repository as Cache;

/**
 * Chooses which Gemini model to ask next.
 *
 * Google counts free-tier requests per model per day, so a model that has run
 * out is parked for a cooldown and the next one is tried instead. Parked models
 * are still returned last, so the pool never reports itself as empty.
 */
final readonly class ModelPool
{
    private const CACHE_PREFIX = 'chat.model.exhausted:';

    /**
     * @param  list<string>  $models  Preferred model first.
     */
    public function __construct(
        private Cache $cache,
        private array $models,
        private int $cooldownSeconds,
    ) {}

    /**
     * Every model, ready ones first.
     *
     * A preferred model is asked first, unless it is parked for exhausted quota
     * — then it waits its turn with the other cooling models rather than
     * spending a round trip that is known to fail.
     *
     * @return list<string>
     */
    public function ordered(?string $preferred = null): array
    {
        $ready = [];
        $cooling = [];

        foreach ($this->models as $model) {
            if ($this->cache->has(self::key($model))) {
                $cooling[] = $model;

                continue;
            }

            if ($model === $preferred) {
                array_unshift($ready, $model);

                continue;
            }

            $ready[] = $model;
        }

        return [...$ready, ...$cooling];
    }

    /**
     * Parks a model that has just reported an exhausted quota.
     */
    public function park(string $model): void
    {
        if ($this->cooldownSeconds > 0) {
            $this->cache->put(self::key($model), true, $this->cooldownSeconds);
        }
    }

    private static function key(string $model): string
    {
        return self::CACHE_PREFIX.$model;
    }
}
