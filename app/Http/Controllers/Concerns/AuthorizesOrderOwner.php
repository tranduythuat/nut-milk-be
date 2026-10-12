<?php

namespace App\Http\Controllers\Concerns;

use App\Models\Order;
use Illuminate\Http\Request;

trait AuthorizesOrderOwner
{
    /**
     * Staff/admin (có Bearer token) được qua; khách phải gửi đúng SĐT của đơn.
     * Trả 404 (không phải 403) để không lộ việc đơn có tồn tại.
     */
    protected function authorizeOrderOwnerOrStaff(Request $request, Order $order): void
    {
        // Route công khai không có auth middleware nên phải chỉ rõ guard sanctum.
        if ($request->user('sanctum')?->isStaff()) {
            return;
        }

        if (! $order->phoneMatches((string) $request->input('phone'))) {
            abort(404, 'Không tìm thấy đơn hàng.');
        }
    }
}
