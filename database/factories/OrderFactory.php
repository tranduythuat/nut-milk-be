<?php

namespace Database\Factories;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\Order;
use Illuminate\Database\Eloquent\Factories\Factory;

class OrderFactory extends Factory
{
    protected $model = Order::class;

    public function definition(): array
    {
        return [
            'order_number' => 'NM-' . now()->format('YmdHis') . '-' . strtoupper($this->faker->bothify('????')),
            'customer_name' => $this->faker->name(),
            'customer_phone' => $this->faker->phoneNumber(),
            'customer_address' => $this->faker->address(),
            'subtotal' => 0,
            'delivery_fee' => 0,
            'discount_amount' => 0,
            'total' => 0,
            'status' => OrderStatus::CONFIRMED,
            'payment_status' => PaymentStatus::PENDING->value,
        ];
    }
}
