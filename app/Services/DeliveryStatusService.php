<?php

namespace App\Services;

use App\Enums\DeliveryStatus;
use App\Models\Delivery;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class DeliveryStatusService
{
    public function updateStatus(
        Delivery $delivery,
        DeliveryStatus $newStatus,
        ?string $note = null
    ): Delivery {
        if (in_array($newStatus, [
            DeliveryStatus::SHIPPING,
            DeliveryStatus::DELIVERED,
        ], true)) {
            throw new RuntimeException(
                'Thao tác này phải được thực hiện thông qua OrderDeliveryWorkflowService.'
            );
        }

        return DB::transaction(function () use (
            $delivery,
            $newStatus,
            $note
        ) {
            $delivery = Delivery::query()
                ->lockForUpdate()
                ->findOrFail($delivery->id);

            $currentStatus = $delivery->status;

            if (! $this->canTransition(
                $currentStatus,
                $newStatus
            )) {
                throw new RuntimeException(
                    sprintf(
                        'Không thể chuyển giao hàng từ "%s" sang "%s".',
                        $currentStatus->value,
                        $newStatus->value
                    )
                );
            }

            $data = [
                'status' => $newStatus,
            ];

            if ($newStatus === DeliveryStatus::SHIPPING) {
                $data['shipped_at'] = now();
            }

            if ($newStatus === DeliveryStatus::DELIVERED) {
                $data['delivered_at'] = now();
            }

            $delivery->update($data);

            $delivery->statusHistories()->create([
                'status' => $newStatus,
                'note' => $note,
            ]);

            return $delivery->fresh([
                'order',
                'deliverySlot',
                'statusHistories',
            ]);
        });
    }

    public function cancel(
        Delivery $delivery,
        ?string $note = null
    ): Delivery {
        return DB::transaction(function () use (
            $delivery,
            $note
        ) {
            $delivery = Delivery::query()
                ->lockForUpdate()
                ->findOrFail($delivery->id);

            if (! in_array($delivery->status, [
                DeliveryStatus::PENDING,
                DeliveryStatus::PREPARING,
            ], true)) {
                throw new RuntimeException(
                    sprintf(
                        'Không thể hủy delivery đang ở trạng thái "%s".',
                        $delivery->status->label()
                    )
                );
            }

            $delivery->update([
                'status' => DeliveryStatus::CANCELLED,
            ]);

            $delivery->statusHistories()->create([
                'status' => DeliveryStatus::CANCELLED,
                'note' => $note ?? 'Delivery đã được hủy.',
            ]);

            return $delivery->fresh([
                'order',
                'deliverySlot',
                'statusHistories',
            ]);
        });
    }

    protected function canTransition(
        DeliveryStatus $currentStatus,
        DeliveryStatus $newStatus
    ): bool {
        return match ($currentStatus) {
            DeliveryStatus::PENDING =>
            $newStatus === DeliveryStatus::PREPARING,

            DeliveryStatus::PREPARING =>
            $newStatus === DeliveryStatus::SHIPPING,

            DeliveryStatus::SHIPPING =>
            in_array(
                $newStatus,
                [
                    DeliveryStatus::DELIVERED,
                    DeliveryStatus::FAILED,
                ],
                true
            ),

            DeliveryStatus::FAILED =>
            $newStatus === DeliveryStatus::PREPARING,

            DeliveryStatus::DELIVERED,
            DeliveryStatus::CANCELLED => false,
        };
    }
}
