<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Chat\ModelPreference;
use App\Chat\Providers\ModelPool;
use App\Chat\Support\ModelChoice;
use Illuminate\Cache\ArrayStore;
use Illuminate\Cache\Repository as CacheRepository;
use Illuminate\Session\ArraySessionHandler;
use Illuminate\Session\Store;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class ModelPreferenceTest extends TestCase
{
    private const MODELS = ['gemini-3.6-flash', 'gemini-3.5-flash-lite', 'gemini-3.1-flash-lite'];

    #[Test]
    public function it_falls_back_to_the_first_configured_model(): void
    {
        $this->assertSame('gemini-3.6-flash', $this->preference()->current($this->newSession()));
    }

    #[Test]
    public function it_remembers_a_valid_choice(): void
    {
        $session = $this->newSession();
        $preference = $this->preference();

        $this->assertTrue($preference->choose($session, 'gemini-3.1-flash-lite'));
        $this->assertSame('gemini-3.1-flash-lite', $preference->current($session));
    }

    #[Test]
    public function it_refuses_a_model_outside_the_whitelist(): void
    {
        $session = $this->newSession();
        $preference = $this->preference();

        $this->assertFalse($preference->choose($session, 'gemini-3.8-flash'));
        $this->assertSame('gemini-3.6-flash', $preference->current($session));
    }

    #[Test]
    public function it_ignores_a_stored_model_that_has_been_removed_from_config(): void
    {
        $session = $this->newSession();
        $session->put('chat.model', 'a-retired-model');

        $this->assertSame('gemini-3.6-flash', $this->preference()->current($session));
    }

    #[Test]
    public function it_labels_models_for_the_picker(): void
    {
        $labels = array_map(
            static fn (ModelChoice $choice): string => $choice->label,
            $this->preference()->options(),
        );

        $this->assertSame(
            ['Gemini 3.6 Flash', 'Gemini 3.5 Flash Lite', 'Gemini 3.1 Flash Lite'],
            $labels,
        );
    }

    #[Test]
    public function the_pool_asks_the_preferred_model_first(): void
    {
        $pool = $this->pool();

        $this->assertSame(
            ['gemini-3.1-flash-lite', 'gemini-3.6-flash', 'gemini-3.5-flash-lite'],
            $pool->ordered('gemini-3.1-flash-lite'),
        );
    }

    #[Test]
    public function the_pool_keeps_config_order_without_a_preference(): void
    {
        $this->assertSame(self::MODELS, $this->pool()->ordered());
    }

    #[Test]
    public function the_pool_does_not_hoist_a_model_that_is_out_of_quota(): void
    {
        $pool = $this->pool();
        $pool->park('gemini-3.1-flash-lite');

        $order = $pool->ordered('gemini-3.1-flash-lite');

        $this->assertSame('gemini-3.6-flash', $order[0], 'A parked favourite does not waste the first attempt.');
        $this->assertSame('gemini-3.1-flash-lite', end($order), 'It is still tried, just last.');
    }

    #[Test]
    public function the_pool_ignores_a_preference_it_does_not_hold(): void
    {
        $this->assertSame(self::MODELS, $this->pool()->ordered('something-else'));
    }

    private function preference(): ModelPreference
    {
        return new ModelPreference(self::MODELS);
    }

    private function pool(): ModelPool
    {
        return new ModelPool(new CacheRepository(new ArrayStore), self::MODELS, 1800);
    }

    private function newSession(): Store
    {
        return new Store('testing', new ArraySessionHandler(120));
    }
}
