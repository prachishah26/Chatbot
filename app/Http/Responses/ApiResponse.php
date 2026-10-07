<?php

declare(strict_types=1);

namespace App\Http\Responses;

use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * The one JSON envelope every endpoint answers with:
 * `{success: bool, data: mixed|null, error: string|null}`.
 *
 * The chat front end (resources/js/chat/api.js) relies on this shape, so build
 * JSON responses here rather than calling response()->json() directly.
 */
final class ApiResponse
{
    /**
     * @param  array<string, mixed>  $data
     */
    public static function success(array $data, int $status = Response::HTTP_OK): JsonResponse
    {
        return response()->json(['success' => true, 'data' => $data, 'error' => null], $status);
    }

    /**
     * @param  string  $error  Shown to the visitor, so never include upstream or internal detail.
     * @param  array<string, mixed>|null  $data
     */
    public static function failure(string $error, int $status, ?array $data = null): JsonResponse
    {
        return response()->json(['success' => false, 'data' => $data, 'error' => $error], $status);
    }
}
