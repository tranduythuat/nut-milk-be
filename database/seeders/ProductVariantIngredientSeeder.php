<?php

namespace Database\Seeders;

use App\Models\ProductVariant;
use App\Models\ProductVariantIngredient;
use App\Models\RawMaterial;
use Illuminate\Database\Seeder;

class ProductVariantIngredientSeeder extends Seeder
{
    public function run(): void
    {
        $materials = RawMaterial::pluck('id', 'code');

        // [product slug][variant name] => [raw material code => định lượng / 1 chai]
        $recipes = [
            'sua-oc-cho' => [
                '250ml' => ['RM-WALNUT' => 40,  'RM-WATER' => 220, 'RM-BOTTLE250'  => 1],
                '500ml' => ['RM-WALNUT' => 80,  'RM-WATER' => 440, 'RM-BOTTLE500'  => 1],
                '1L'    => ['RM-WALNUT' => 160, 'RM-WATER' => 880, 'RM-BOTTLE1000' => 1],
            ],
            'sua-hanh-nhan' => [
                '250ml' => ['RM-ALMOND' => 35, 'RM-WATER' => 220, 'RM-BOTTLE250' => 1],
                '500ml' => ['RM-ALMOND' => 70, 'RM-WATER' => 440, 'RM-BOTTLE500' => 1],
            ],
            'sua-mac-ca' => [
                '250ml' => ['RM-MACADAMIA' => 40, 'RM-WATER' => 220, 'RM-BOTTLE250' => 1],
                '500ml' => ['RM-MACADAMIA' => 80, 'RM-WATER' => 440, 'RM-BOTTLE500' => 1],
            ],
        ];

        foreach ($recipes as $productSlug => $variants) {
            foreach ($variants as $variantName => $ingredients) {
                $variant = ProductVariant::query()
                    ->whereHas('product', fn($q) => $q->where('slug', $productSlug))
                    ->where('name', $variantName)
                    ->firstOrFail();

                foreach ($ingredients as $code => $quantity) {
                    ProductVariantIngredient::updateOrCreate(
                        [
                            'product_variant_id' => $variant->id,
                            'raw_material_id' => $materials[$code], // lỗi ngay nếu sai code
                        ],
                        ['quantity' => $quantity]
                    );
                }
            }
        }
    }
}
