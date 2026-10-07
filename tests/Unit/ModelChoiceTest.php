<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Chat\Llm\ModelChoice;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ModelChoiceTest extends TestCase
{
    #[Test]
    public function it_prefixes_the_id_with_the_provider(): void
    {
        $choice = ModelChoice::for('ollama', 'llama3.2:3b', 'Ollama', isLocal: true);

        $this->assertSame('ollama/llama3.2:3b', $choice->id);
        $this->assertSame('Llama3.2 3b', $choice->label);
        $this->assertSame('Ollama', $choice->providerLabel());
        $this->assertTrue($choice->isLocal());
    }

    #[Test]
    public function it_defaults_to_a_hosted_provider_named_after_its_key(): void
    {
        $choice = ModelChoice::for('gemini', 'gemini-3.5-flash-lite');

        $this->assertSame('Gemini 3.5 Flash Lite', $choice->label);
        $this->assertSame('Gemini', $choice->providerLabel());
        $this->assertFalse($choice->isLocal());
    }

    #[Test]
    public function it_round_trips_through_parse(): void
    {
        $choice = ModelChoice::for('ollama', 'hf.co/org/model:q4');

        $this->assertSame(['ollama', 'hf.co/org/model:q4'], ModelChoice::parse($choice->id));
    }

    #[Test]
    public function it_rejects_malformed_ids(): void
    {
        $this->assertNull(ModelChoice::parse(''));
        $this->assertNull(ModelChoice::parse('ollama/'));
        $this->assertNull(ModelChoice::parse('/llama3.2'));
        $this->assertNull(ModelChoice::parse('llama3.2'));
    }
}
