<?php

namespace App\Http\Middleware;

use App\Models\IdempotencyKey;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class EnsureIdempotency
{
    public function handle(Request $request, Closure $next): Response
    {
        $key = $request->header('Idempotency-Key');

        if (! $key) {
            return $next($request);
        }

        $existing = IdempotencyKey::query()
            ->where('key', $key)
            ->where('expires_at', '>', now())
            ->first();

        if ($existing) {
            return response()->json(
                $existing->response_body,
                $existing->response_status
            )->header('Idempotency-Replayed', 'true');
        }

        /** @var Response $response */
        $response = $next($request);

        // Chỉ cache các response thành công/lỗi nghiệp vụ có cấu trúc
        // JSON dự đoán được — không cache lỗi 500/validation ngẫu nhiên
        // để tránh khóa cứng một lỗi tạm thời vào key vĩnh viễn.
        if (in_array($response->getStatusCode(), [200, 201, 422], true)) {
            DB::transaction(function () use ($key, $request, $response) {
                IdempotencyKey::updateOrCreate(
                    ['key' => $key],
                    [
                        'path' => $request->path(),
                        'response_status' => $response->getStatusCode(),
                        'response_body' => json_decode($response->getContent(), true),
                        'expires_at' => now()->addHours(24),
                    ]
                );
            });
        }

        return $response;
    }
}
