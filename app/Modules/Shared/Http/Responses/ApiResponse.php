<?php

namespace App\Modules\Shared\Http\Responses;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Builds the standard success envelope: { "data": ..., "meta": { "request_id": ... } }.
 */
final class ApiResponse
{
    /**
     * @param  array<string, mixed>|JsonResource|array<int, mixed>  $data
     * @param  array<string, mixed>  $meta
     */
    public static function success(mixed $data = null, int $status = 200, array $meta = []): JsonResponse
    {
        $meta = ['request_id' => request()->header('X-Request-Id'), ...$meta];

        return response()->json([
            'data' => $data instanceof JsonResource ? $data->resolve() : $data,
            'meta' => $meta,
        ], $status);
    }
}
