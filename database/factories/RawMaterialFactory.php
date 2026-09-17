<?php

namespace Database\Factories;

use App\Models\RawMaterial;
use Illuminate\Database\Eloquent\Factories\Factory;

class RawMaterialFactory extends Factory
{
    protected $model = RawMaterial::class;

    public function definition(): array
    {
        return [
            'name' => ucfirst($this->faker->unique()->word()) . ' nguyên liệu',
            'code' => strtoupper($this->faker->unique()->bothify('RM-####')),
            'unit' => 'l',
            'stock' => 1000,
            'reserved_quantity' => 0,
            'min_stock' => 10,
            'is_active' => true,
        ];
    }
}
