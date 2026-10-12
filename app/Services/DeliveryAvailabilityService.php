<?php

namespace App\Services;

use App\Enums\ProductionStatus;
use App\Models\ProductionPlan;
use App\Enums\DeliveryStatus;
use App\Enums\OrderStatus;
use App\Models\Delivery;
use App\Models\DeliveryItem;
use App\Models\DeliverySlot;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

class DeliveryAvailabilityService
{
    public function getAvailableDates(): Collection
    {
        $dates = collect();

        $date = $this->getEarliestDeliveryDate();

        $days = config('delivery.available_days', 14);

        while ($dates->count() < $days) {
            if ($this->isWorkingDay($date) && $this->isDateOpenForOrders($date)) {
                $dates->push($date->copy());
            }

            $date->addDay();
        }

        return $dates;
    }

    public function getAvailableSlots(
        CarbonInterface $date
    ): Collection {
        if (! $this->isWorkingDay($date) || ! $this->isDateOpenForOrders($date)) {
            return collect();
        }

        $slots = DeliverySlot::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get();

        $booked = Delivery::query()
            ->whereDate('delivery_date', $date)
            ->whereNotIn('status', [
                DeliveryStatus::CANCELLED->value,
                DeliveryStatus::FAILED->value,
            ])
            ->selectRaw(
                'delivery_slot_id, COUNT(*) as total'
            )
            ->groupBy('delivery_slot_id')
            ->pluck('total', 'delivery_slot_id');

        return $slots->map(function ($slot) use ($booked) {
            $bookedOrders = (int) (
                $booked[$slot->id] ?? 0
            );

            $remainingOrders = $slot->max_orders === null
                ? null
                : max(
                    $slot->max_orders - $bookedOrders,
                    0
                );

            return [
                'id' => $slot->id,
                'name' => $slot->name,
                'start_time' => $slot->start_time,
                'end_time' => $slot->end_time,
                'max_orders' => $slot->max_orders,
                'booked_orders' => $bookedOrders,
                'remaining_orders' => $remainingOrders,
                'available' =>
                $slot->max_orders === null
                    || $remainingOrders > 0,
            ];
        });
    }

    public function isSlotAvailable(
        CarbonInterface $date,
        int $slotId
    ): bool {
        if (! $this->isWorkingDay($date)) {
            return false;
        }

        $slot = DeliverySlot::query()
            ->whereKey($slotId)
            ->where('is_active', true)
            ->first();

        if (! $slot) {
            return false;
        }

        if ($slot->max_orders === null) {
            return true;
        }

        $bookedOrders = Delivery::query()
            ->whereDate('delivery_date', $date)
            ->where('delivery_slot_id', $slot->id)
            ->whereNotIn('status', [
                DeliveryStatus::CANCELLED->value,
                DeliveryStatus::FAILED->value,
            ])
            ->count();

        return $bookedOrders < $slot->max_orders;
    }

    protected function getEarliestDeliveryDate(): Carbon
    {
        $now = now();

        $date = $now->copy()
            ->startOfDay()
            ->addDays(
                config(
                    'delivery.minimum_delivery_days',
                    1
                )
            );

        $cutoffTime = config(
            'delivery.cutoff_time',
            '18:00'
        );

        if ($now->format('H:i') >= $cutoffTime) {
            $date->addDay();
        }

        while (! $this->isWorkingDay($date)) {
            $date->addDay();
        }

        return $date;
    }

    protected function isWorkingDay(
        CarbonInterface $date
    ): bool {
        return in_array(
            $date->dayOfWeek,
            config('delivery.working_days', []),
            true
        );
    }

    public function validateDeliverySelection(
        string $date,
        int $slotId
    ): void {
        $deliveryDate = Carbon::createFromFormat(
            'Y-m-d',
            $date
        )->startOfDay();

        $earliestDate = $this->getEarliestDeliveryDate();

        if ($deliveryDate->lt($earliestDate)) {
            throw new \RuntimeException(
                'Ngày giao hàng không hợp lệ.'
            );
        }

        if (! $this->isWorkingDay($deliveryDate)) {
            throw new \RuntimeException(
                'Ngày này không có lịch giao hàng.'
            );
        }

        $this->assertDateOpenForOrders($deliveryDate);

        if (! $this->isSlotAvailable(
            $deliveryDate,
            $slotId
        )) {
            throw new \RuntimeException(
                'Khung giờ giao hàng đã hết chỗ.'
            );
        }
    }

    public function reserveSlot(
        CarbonInterface $date,
        int $slotId
    ): DeliverySlot {
        $slot = DeliverySlot::query()
            ->whereKey($slotId)
            ->where('is_active', true)
            ->lockForUpdate()
            ->first();

        if (! $slot) {
            throw new \RuntimeException(
                'Khung giờ giao hàng không tồn tại.'
            );
        }

        $bookedOrders = Delivery::query()
            ->whereDate('delivery_date', $date)
            ->where('delivery_slot_id', $slot->id)
            ->whereNotIn('status', [
                DeliveryStatus::CANCELLED->value,
                DeliveryStatus::FAILED->value,
            ])
            ->count();

        if (
            $slot->max_orders !== null
            && $bookedOrders >= $slot->max_orders
        ) {
            throw new \RuntimeException(
                'Khung giờ giao hàng đã hết chỗ.'
            );
        }

        return $slot;
    }

    /** Tổng số chai đã nhận cho ngày này (đơn PENDING + CONFIRMED, đợt chưa hủy). */
    public function bookedUnits(CarbonInterface $date): int
    {
        $items = DeliveryItem::query()
            ->with('orderItem.components')
            ->whereHas('delivery', function ($q) use ($date) {
                $q->whereDate('delivery_date', $date)
                    ->where('status', '!=', DeliveryStatus::CANCELLED->value)
                    ->whereHas('order', fn($o) => $o->whereIn('status', [
                        OrderStatus::PENDING->value,
                        OrderStatus::CONFIRMED->value,
                        OrderStatus::COMPLETED->value,
                    ]));
            })
            ->get();

        return $items->sum(function (DeliveryItem $item) {
            $orderItem = $item->orderItem;

            // Sản phẩm lẻ: 1 đơn vị = 1 chai. Combo: số chai trong 1 bộ.
            $unitsPerItem = $orderItem->product_variant_id
                ? 1
                : (int) $orderItem->components->sum('quantity');

            return $unitsPerItem * $item->quantity;
        });
    }

    public function assertCapacityAvailable(CarbonInterface $date, int $additionalUnits): void
    {
        $capacity = config('production.daily_capacity');

        if ($capacity === null || $capacity === '') {
            return;
        }

        $capacity = (int) $capacity;
        $remaining = max(0, $capacity - $this->bookedUnits($date));

        if ($additionalUnits > $remaining) {
            throw new \RuntimeException(sprintf(
                'Ngày giao %s chỉ còn nhận được %d sản phẩm (bạn đang đặt %d). Vui lòng giảm số lượng hoặc chọn ngày khác.',
                $date->format('d/m/Y'),
                $remaining,
                $additionalUnits
            ));
        }
    }

    public function isDateOpenForOrders(CarbonInterface $date): bool
    {
        return ! ProductionPlan::query()
            ->whereDate('production_date', $date)
            ->where('status', '!=', ProductionStatus::DRAFT->value)
            ->exists();
    }

    public function assertDateOpenForOrders(CarbonInterface $date): void
    {
        if (! $this->isDateOpenForOrders($date)) {
            throw new \RuntimeException(sprintf(
                'Ngày giao %s đã chốt kế hoạch sản xuất, không nhận thêm đơn.',
                $date->format('d/m/Y')
            ));
        }
    }
}
