<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Database\Seeder;

class ProductSeeder extends Seeder
{
    public function run(): void
    {
        $category = Category::where('slug', 'sua-hat')->firstOrFail();

        $products = [
            [
                'name' => 'Sữa óc chó',
                'slug' => 'sua-oc-cho',
                'description' => 'Sữa óc chó thơm béo, giàu dinh dưỡng.',
                'sort_order' => 1,
                'variants' => [
                    [
                        'name' => '250ml',
                        'attributes' => [
                            'volume' => 250,
                            'unit' => 'ml',
                        ],
                        'price' => 25000,
                    ],
                    [
                        'name' => '500ml',
                        'attributes' => [
                            'volume' => 500,
                            'unit' => 'ml',
                        ],
                        'price' => 35000,
                    ],
                    [
                        'name' => '1L',
                        'attributes' => [
                            'volume' => 1,
                            'unit' => 'L',
                        ],
                        'price' => 60000,
                    ],
                ],
            ],

            [
                'name' => 'Sữa hạnh nhân',
                'slug' => 'sua-hanh-nhan',
                'description' => 'Sữa hạnh nhân thơm nhẹ, vị béo tự nhiên.',
                'sort_order' => 2,
                'variants' => [
                    [
                        'name' => '250ml',
                        'attributes' => [
                            'volume' => 250,
                            'unit' => 'ml',
                        ],
                        'price' => 25000,
                    ],
                    [
                        'name' => '500ml',
                        'attributes' => [
                            'volume' => 500,
                            'unit' => 'ml',
                        ],
                        'price' => 35000,
                    ],
                ],
            ],

            [
                'name' => 'Sữa mắc ca',
                'slug' => 'sua-mac-ca',
                'description' => 'Sữa mắc ca béo mịn, thơm đặc trưng.',
                'sort_order' => 3,
                'variants' => [
                    [
                        'name' => '250ml',
                        'attributes' => [
                            'volume' => 250,
                            'unit' => 'ml',
                        ],
                        'price' => 30000,
                    ],
                    [
                        'name' => '500ml',
                        'attributes' => [
                            'volume' => 500,
                            'unit' => 'ml',
                        ],
                        'price' => 40000,
                    ],
                ],
            ],
        ];

        foreach ($products as $productData) {
            $variants = $productData['variants'];

            unset($productData['variants']);

            $product = Product::create([
                ...$productData,
                'category_id' => $category->id,
                'is_active' => true,
            ]);

            foreach ($variants as $index => $variantData) {
                ProductVariant::create([
                    ...$variantData,
                    'product_id' => $product->id,
                    'stock' => 100,
                    'is_active' => true,
                    'sort_order' => $index + 1,
                ]);
            }
        }
    }
}
