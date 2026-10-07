---
paths:
  - 'tests/**'
---

# Testing

## Layout

`tests/Unit/<Class>Test.php` for classes that need no HTTP kernel or database; `tests/Feature/` for routes, commands, container wiring and anything touching the DB. Keep both folders flat. Scaffold with `php artisan make:test --phpunit [--unit] <Name>Test --no-interaction`.

## Style

`final class …Test extends TestCase`, `declare(strict_types=1)`, a `#[Test]` attribute, and snake_case sentences for names (`it_hides_gemini_until_an_api_key_is_set`). Extend `PHPUnit\Framework\TestCase` when the test needs nothing from Laravel.

## Never call a real LLM

Fake HTTP with `Http::fake()` / `Http::fakeSequence()`, or bind a stub with `$this->app->instance(ChatProvider::class, new class implements ChatProvider { … })`. Build providers with `retryDelayMs: 0` so retries do not sleep.

## Configure before resolving

Chat singletons read config when first resolved. Call `config([...])` before the first request or `$this->app->make()` in a test.

## Test data

Use `Conversation::factory()`, `Message::factory()` and `User::factory()` rather than inserting rows by hand.

## When to add tests

Every new class gets a test file, and every bug fix gets a regression test. A refactor must keep all existing tests passing without weakening their assertions.
