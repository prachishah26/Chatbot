<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Chat\Llm\ConfigValues;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ConfigValuesTest extends TestCase
{
    #[Test]
    public function it_reads_a_list_from_a_comma_separated_string(): void
    {
        $this->assertSame(['a', 'b', 'c'], ConfigValues::list(' a, b ,,c, a '));
    }

    #[Test]
    public function it_reads_a_list_from_an_array(): void
    {
        $this->assertSame(['a', 'b'], ConfigValues::list(['a', '', ' b ']));
    }

    #[Test]
    public function an_empty_value_is_an_empty_list(): void
    {
        $this->assertSame([], ConfigValues::list(''));
        $this->assertSame([], ConfigValues::list(null));
    }

    #[Test]
    public function a_blank_string_is_not_set(): void
    {
        $this->assertNull(ConfigValues::optionalString(''));
        $this->assertNull(ConfigValues::optionalString('   '));
        $this->assertNull(ConfigValues::optionalString(10));
        $this->assertSame('10m', ConfigValues::optionalString('10m'));
    }

    #[Test]
    public function it_reads_an_optional_boolean(): void
    {
        $this->assertNull(ConfigValues::optionalBool(null));
        $this->assertNull(ConfigValues::optionalBool(''));
        $this->assertNull(ConfigValues::optionalBool('maybe'));
        $this->assertTrue(ConfigValues::optionalBool('true'));
        $this->assertFalse(ConfigValues::optionalBool('0'));
    }

    #[Test]
    public function it_recognises_http_urls(): void
    {
        $this->assertTrue(ConfigValues::isHttpUrl('http://localhost:11434'));
        $this->assertTrue(ConfigValues::isHttpUrl('https://example.test'));
        $this->assertFalse(ConfigValues::isHttpUrl('file:///etc/passwd'));
    }
}
