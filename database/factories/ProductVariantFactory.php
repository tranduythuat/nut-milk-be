<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Database\Eloquent\Factories\Factory;

class ProductVariantFactory extends Factory
{
    protected $model = ProductVariant::class;

    public function definition(): array
    {
        return [
            'product_id' => Product::factory(),
            'name' => '250ml',
            'attributes' => ['volume' => 250, 'unit' => 'ml'],
            'price' => 25000,
            'stock' => 0,
            'is_active' => true,
            'sort_order' => 0,
        ];
    }
}
