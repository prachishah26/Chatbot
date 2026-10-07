<?php

declare(strict_types=1);

namespace App\Providers;

use App\Http\Responses\ApiResponse;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * App-wide concerns. Chat domain bindings live in ChatServiceProvider.
 */
final class AppServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->registerRateLimiters();
    }

    /**
     * Named limiters referenced from routes/web.php as `throttle:<name>`.
     */
    private function registerRateLimiters(): void
    {
        // Caps how often a single visitor can call the AI provider.
        RateLimiter::for('chat', static fn (Request $request): Limit => Limit::perMinute(20)
            ->by((string) $request->ip())
            ->response(static fn (): JsonResponse => ApiResponse::failure(
                'You are sending messages too quickly. Please wait a moment.',
                Response::HTTP_TOO_MANY_REQUESTS,
            )));

        // Opening, clearing and deleting threads costs nothing upstream, so the
        // limit here only needs to stop runaway clients.
        RateLimiter::for('chat-ui', static fn (Request $request): Limit => Limit::perMinute(60)
            ->by((string) $request->ip()));

        /*
         | Sign-in and sign-up are the credential-guessing surface. Keying on the
         | submitted address as well as the IP slows an attacker working through
         | one account without locking out everyone behind a shared address.
         */
        RateLimiter::for('auth', static function (Request $request): array {
            $email = Str::lower((string) $request->input('email'));

            return [
                Limit::perMinute(5)->by($email.'|'.$request->ip()),

                // A backstop the attacker cannot shed by rotating source addresses.
                Limit::perHour(30)->by('auth-account:'.$email),
            ];
        });
    }
}
