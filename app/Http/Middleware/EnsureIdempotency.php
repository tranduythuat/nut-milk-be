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

        // 1) Khóa theo cả path, tránh dùng nhầm key cho endpoint khác
        $existing = IdempotencyKey::query()
            ->where('key', $key)
            ->where('path', $request->path())
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

        // 2) Chỉ cache 200/201. Nếu cache cả 422, khách sửa form rồi gửi lại
        //    cùng key sẽ nhận lại lỗi cũ trong 24h.
        if (in_array($response->getStatusCode(), [200, 201], true)) {
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
