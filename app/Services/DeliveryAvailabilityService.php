<?php

namespace App\Services;

use App\Enums\DeliveryStatus;
use App\Models\Delivery;
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
            if ($this->isWorkingDay($date)) {
                $dates->push($date->copy());
            }

            $date->addDay();
        }

        return $dates;
    }

    public function getAvailableSlots(
        CarbonInterface $date
    ): Collection {
        if (! $this->isWorkingDay($date)) {
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
}
