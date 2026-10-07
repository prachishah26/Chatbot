<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Http\Responses\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class ApiResponseTest extends TestCase
{
    #[Test]
    public function success_wraps_the_data_in_the_envelope(): void
    {
        $response = ApiResponse::success(['id' => 1]);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame(['success' => true, 'data' => ['id' => 1], 'error' => null], $response->getData(true));
    }

    #[Test]
    public function failure_carries_the_error_status_and_optional_data(): void
    {
        $response = ApiResponse::failure('Try again.', 503, ['user' => ['id' => 2]]);

        $this->assertSame(503, $response->getStatusCode());
        $this->assertSame(['success' => false, 'data' => ['user' => ['id' => 2]], 'error' => 'Try again.'], $response->getData(true));
    }

    #[Test]
    public function the_chat_rate_limiter_answers_in_the_same_envelope(): void
    {
        $limit = RateLimiter::limiter('chat')(Request::create('/chat/messages', 'POST'));

        $response = ($limit->responseCallback)();

        $this->assertSame(429, $response->getStatusCode());
        $this->assertSame(false, $response->getData(true)['success']);
        $this->assertNull($response->getData(true)['data']);
        $this->assertIsString($response->getData(true)['error']);
    }
}
