<?php

namespace App\Services;

use App\Enums\ProductionStatus;
use App\Models\ProductionPlan;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class ProductionStatusService
{
    public function __construct(
        protected ProductionQuantityService $productionQuantityService,
        protected DeliveryStatusService $deliveryStatusService
    ) {}
    public function updateStatus(
        ProductionPlan $plan,
        ProductionStatus $newStatus,
        ?string $note = null
    ): ProductionPlan {
        return DB::transaction(function () use (
            $plan,
            $newStatus,
            $note
        ) {
            $plan = ProductionPlan::query()
                ->lockForUpdate()
                ->findOrFail($plan->id);

            $currentStatus = $plan->status;

            if ($newStatus === ProductionStatus::COMPLETED) {
                throw new RuntimeException(
                    'Vui lòng sử dụng thao tác hoàn thành kế hoạch sản xuất.'
                );
            }

            if (! $this->canTransition($currentStatus, $newStatus)) {
                throw new RuntimeException(
                    sprintf(
                        'Không thể chuyển kế hoạch sản xuất từ "%s" sang "%s".',
                        $currentStatus->value,
                        $newStatus->value
                    )
                );
            }

            $plan->update([
                'status' => $newStatus,
                'note' => $note ?? $plan->note,
            ]);

            return $plan->fresh([
                'items.productVariant.product',
            ]);
        });
    }

    protected function canTransition(
        ProductionStatus $currentStatus,
        ProductionStatus $newStatus
    ): bool {
        return match ($currentStatus) {
            ProductionStatus::DRAFT => in_array(
                $newStatus,
                [
                    ProductionStatus::PLANNED,
                    ProductionStatus::CANCELLED,
                ],
                true
            ),

            ProductionStatus::PLANNED => in_array(
                $newStatus,
                [
                    ProductionStatus::IN_PROGRESS,
                    ProductionStatus::CANCELLED,
                ],
                true
            ),

            ProductionStatus::IN_PROGRESS => $newStatus === ProductionStatus::COMPLETED,

            ProductionStatus::COMPLETED,
            ProductionStatus::CANCELLED => false,
        };
    }
}
