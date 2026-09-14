<?php

namespace Database\Seeders;


use App\Models\DeliverySlot;
use Illuminate\Database\Seeder;

class DeliverySlotSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $slots = [
            [
                'name' => '08:00 - 10:00',
                'start_time' => '08:00',
                'end_time' => '10:00',
                'max_orders' => 10,
                'sort_order' => 1,
            ],
            [
                'name' => '10:00 - 12:00',
                'start_time' => '10:00',
                'end_time' => '12:00',
                'max_orders' => 10,
                'sort_order' => 2,
            ],
            [
                'name' => '14:00 - 16:00',
                'start_time' => '14:00',
                'end_time' => '16:00',
                'max_orders' => 10,
                'sort_order' => 3,
            ],
            [
                'name' => '16:00 - 18:00',
                'start_time' => '16:00',
                'end_time' => '18:00',
                'max_orders' => 10,
                'sort_order' => 4,
            ],
        ];

        foreach ($slots as $slot) {
            DeliverySlot::updateOrCreate(
                [
                    'start_time' => $slot['start_time'],
                    'end_time' => $slot['end_time'],
                ],
                [
                    ...$slot,
                    'is_active' => true,
                ]
            );
        }
    }
}
