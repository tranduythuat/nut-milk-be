<?php

namespace App\Http\Middleware;

use App\Enums\UserRole;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserHasRole
{
    /**
     * @param string $roles Danh sách role cách nhau bởi dấu phẩy, vd "staff,admin"
     */
    public function handle(Request $request, Closure $next, string $roles): Response
    {
        $user = $request->user();

        if (! $user) {
            return response()->json([
                'success' => false,
                'message' => 'Cần đăng nhập để thực hiện thao tác này.',
            ], 401);
        }

        $allowed = array_map(
            fn(string $r) => UserRole::from(trim($r)),
            explode(',', $roles)
        );

        if (! in_array($user->role, $allowed, true)) {
            return response()->json([
                'success' => false,
                'message' => 'Bạn không có quyền thực hiện thao tác này.',
            ], 403);
        }

        return $next($request);
    }
}
