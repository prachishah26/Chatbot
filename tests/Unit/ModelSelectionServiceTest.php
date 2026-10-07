<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Chat\Llm\ModelChoice;
use App\Chat\Llm\ModelSelectionService;
use Illuminate\Session\ArraySessionHandler;
use Illuminate\Session\Store;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class ModelSelectionServiceTest extends TestCase
{
    private const MODELS = ['gemini-3.6-flash', 'gemini-3.5-flash-lite', 'gemini-3.1-flash-lite'];

    #[Test]
    public function it_falls_back_to_the_first_configured_model(): void
    {
        $this->assertSame('ollama/llama3.2', $this->preference()->current($this->newSession()));
    }

    #[Test]
    public function it_remembers_a_valid_choice(): void
    {
        $session = $this->newSession();
        $preference = $this->preference();

        $this->assertTrue($preference->choose($session, 'gemini/gemini-3.1-flash-lite'));
        $this->assertSame('gemini/gemini-3.1-flash-lite', $preference->current($session));
        $this->assertSame('gemini', $preference->currentChoice($session)->provider);
        $this->assertSame('gemini-3.1-flash-lite', $preference->currentChoice($session)->model);
    }

    #[Test]
    public function it_refuses_a_model_outside_the_whitelist(): void
    {
        $session = $this->newSession();
        $preference = $this->preference();

        $this->assertFalse($preference->choose($session, 'gemini/gemini-3.8-flash'));
        $this->assertFalse($preference->choose($session, 'gemini-3.6-flash'), 'A bare model without its provider is refused.');
        $this->assertSame('ollama/llama3.2', $preference->current($session));
    }

    #[Test]
    public function it_ignores_a_stored_model_that_has_been_removed_from_config(): void
    {
        $session = $this->newSession();
        $session->put('chat.model', 'a-retired-model');

        $this->assertSame('ollama/llama3.2', $this->preference()->current($session));
    }

    #[Test]
    public function it_labels_models_for_the_picker(): void
    {
        $labels = array_map(
            static fn (ModelChoice $choice): string => $choice->label,
            $this->preference()->options(),
        );

        $this->assertSame(
            ['Llama3.2', 'Qwen3 8b', 'Gemini 3.6 Flash', 'Gemini 3.5 Flash Lite', 'Gemini 3.1 Flash Lite'],
            $labels,
        );
    }

    #[Test]
    public function it_groups_models_by_provider(): void
    {
        $groups = $this->preference()->groupedOptions();

        $this->assertSame(['ollama', 'gemini'], array_keys($groups));
        $this->assertSame('Ollama', $groups['ollama'][0]->providerLabel());
        $this->assertTrue($groups['ollama'][0]->isLocal());
        $this->assertFalse($groups['gemini'][0]->isLocal());
    }

    #[Test]
    public function it_splits_an_id_on_the_first_separator_only(): void
    {
        $this->assertSame(['ollama', 'hf.co/org/model:q4'], ModelChoice::parse('ollama/hf.co/org/model:q4'));
        $this->assertNull(ModelChoice::parse('llama3.2'));
        $this->assertNull(ModelChoice::parse('/llama3.2'));
    }

    private function preference(): ModelSelectionService
    {
        return new ModelSelectionService([
            ...ModelChoice::listFor('ollama', ['llama3.2', 'qwen3:8b'], 'Ollama', isLocal: true),
            ...ModelChoice::listFor('gemini', self::MODELS, 'Gemini'),
        ]);
    }

    private function newSession(): Store
    {
        return new Store('testing', new ArraySessionHandler(120));
    }
}
