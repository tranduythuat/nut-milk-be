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
        protected DeliveryStatusService $deliveryStatusService,
        protected RawMaterialInventoryService $rawMaterialInventoryService,
    ) {}

    public function updateStatus(
        ProductionPlan $plan,
        ProductionStatus $newStatus,
        ?string $note = null
    ): ProductionPlan {
        return DB::transaction(function () use ($plan, $newStatus, $note) {
            $plan = ProductionPlan::query()->lockForUpdate()->findOrFail($plan->id);
            $currentStatus = $plan->status;

            if ($newStatus === ProductionStatus::COMPLETED) {
                throw new RuntimeException('Vui lòng sử dụng thao tác hoàn thành kế hoạch sản xuất.');
            }

            if (! $this->canTransition($currentStatus, $newStatus)) {
                throw new RuntimeException(sprintf(
                    'Không thể chuyển kế hoạch sản xuất từ "%s" sang "%s".',
                    $currentStatus->value,
                    $newStatus->value
                ));
            }

            // Xác nhận kế hoạch: kiểm tra + giữ chỗ nguyên liệu (bước 7 trong quy trình).
            if ($newStatus === ProductionStatus::PLANNED) {
                $this->reserveRawMaterials($plan);
            }

            // Hủy kế hoạch đã giữ chỗ: hoàn lại nguyên liệu.
            if ($newStatus === ProductionStatus::CANCELLED && $currentStatus === ProductionStatus::PLANNED) {
                $this->releaseRawMaterials($plan);
            }

            $plan->update([
                'status' => $newStatus,
                'note' => $note ?? $plan->note,
            ]);

            return $plan->fresh([
                'items.productVariant.product',
                'rawMaterialRequirements.rawMaterial',
            ]);
        });
    }

    protected function reserveRawMaterials(ProductionPlan $plan): void
    {
        $plan->load('rawMaterialRequirements.rawMaterial');

        foreach ($plan->rawMaterialRequirements as $requirement) {
            $this->rawMaterialInventoryService->reserve(
                $requirement->rawMaterial,
                (float) $requirement->required_quantity,
                $plan,
                "Giữ nguyên liệu cho kế hoạch sản xuất ngày {$plan->production_date->toDateString()}."
            );

            $requirement->update(['reserved_quantity' => $requirement->required_quantity]);
        }
    }

    protected function releaseRawMaterials(ProductionPlan $plan): void
    {
        $plan->load('rawMaterialRequirements.rawMaterial');

        foreach ($plan->rawMaterialRequirements as $requirement) {
            if ((float) $requirement->reserved_quantity <= 0) {
                continue;
            }

            $this->rawMaterialInventoryService->release(
                $requirement->rawMaterial,
                (float) $requirement->reserved_quantity,
                $plan,
                'Hủy giữ nguyên liệu do hủy kế hoạch sản xuất.'
            );

            $requirement->update(['reserved_quantity' => 0]);
        }
    }

    protected function canTransition(ProductionStatus $currentStatus, ProductionStatus $newStatus): bool
    {
        return match ($currentStatus) {
            ProductionStatus::DRAFT => in_array($newStatus, [ProductionStatus::PLANNED, ProductionStatus::CANCELLED], true),
            ProductionStatus::PLANNED => in_array($newStatus, [ProductionStatus::IN_PROGRESS, ProductionStatus::CANCELLED], true),
            ProductionStatus::IN_PROGRESS => $newStatus === ProductionStatus::COMPLETED,
            ProductionStatus::COMPLETED, ProductionStatus::CANCELLED => false,
        };
    }
}
