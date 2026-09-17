<?php

namespace Database\Factories;

use App\Models\DeliverySlot;
use Illuminate\Database\Eloquent\Factories\Factory;

class DeliverySlotFactory extends Factory
{
    protected $model = DeliverySlot::class;

    public function definition(): array
    {
        return [
            'name' => '08:00 - 10:00',
            'start_time' => '08:00',
            'end_time' => '10:00',
            'max_orders' => 100,
            'is_active' => true,
            'sort_order' => 1,
        ];
    }
}
