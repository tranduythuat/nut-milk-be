<?php

namespace App\Services;

use App\Enums\DeliveryStatus;
use App\Enums\OrderStatus;
use App\Enums\ProductionStatus;
use App\Models\Order;
use App\Models\ProductionPlan;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class ProductionCompletionService
{
    public function complete(
        ProductionPlan $plan,
        ?string $note = null
    ): ProductionPlan {
        return DB::transaction(function () use ($plan, $note) {

            $plan = ProductionPlan::query()
                ->lockForUpdate()
                ->findOrFail($plan->id);

            // 1. Phải đang in_progress
            if ($plan->status !== ProductionStatus::IN_PROGRESS) {
                throw new RuntimeException(
                    'Chỉ kế hoạch đang sản xuất mới có thể hoàn thành.'
                );
            }

            // 2. Kiểm tra đủ sản lượng
            $plan->load('items');

            if ($plan->items->isEmpty()) {
                throw new RuntimeException(
                    'Kế hoạch sản xuất chưa có sản phẩm.'
                );
            }

            $isCompleted = $plan->items->every(
                fn($item) =>
                $item->produced_quantity >=
                    $item->planned_quantity
            );

            if (! $isCompleted) {
                throw new RuntimeException(
                    'Chưa sản xuất đủ số lượng theo kế hoạch.'
                );
            }

            // 3. Hoàn thành Production Plan
            $plan->update([
                'status' => ProductionStatus::COMPLETED,
                'note' => $note ?? $plan->note,
            ]);

            // 4. Tìm các Order giao trong ngày
            $orders = Order::query()
                ->with('delivery')
                ->whereHas('delivery', function ($query) use ($plan) {
                    $query->whereDate(
                        'delivery_date',
                        $plan->production_date
                    );
                })
                ->whereIn('status', [
                    OrderStatus::CONFIRMED->value,
                    OrderStatus::PROCESSING->value,
                ])
                ->get();

            // 5. Cho Delivery bắt đầu chuẩn bị
            foreach ($orders as $order) {
                $delivery = $order->delivery;

                if (! $delivery) {
                    continue;
                }

                if (
                    $delivery->status ===
                    DeliveryStatus::PENDING
                ) {
                    $delivery->update([
                        'status' => DeliveryStatus::PREPARING,
                    ]);

                    $delivery->statusHistories()->create([
                        'status' => DeliveryStatus::PREPARING,
                        'note' => 'Sản xuất hoàn tất, bắt đầu chuẩn bị giao hàng.',
                    ]);
                }
            }

            return $plan->fresh([
                'items.productVariant.product',
            ]);
        });
    }
}
