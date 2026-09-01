<?php

namespace Database\Seeders;

use App\Models\Combo;
use App\Models\ProductVariant;
use Illuminate\Database\Seeder;

class ComboSeeder extends Seeder
{
    public function run(): void
    {
        $walnut250 = ProductVariant::query()
            ->whereHas('product', function ($query) {
                $query->where('slug', 'sua-oc-cho');
            })
            ->where('name', '250ml')
            ->firstOrFail();

        $almond250 = ProductVariant::query()
            ->whereHas('product', function ($query) {
                $query->where('slug', 'sua-hanh-nhan');
            })
            ->where('name', '250ml')
            ->firstOrFail();

        $macadamia250 = ProductVariant::query()
            ->whereHas('product', function ($query) {
                $query->where('slug', 'sua-mac-ca');
            })
            ->where('name', '250ml')
            ->firstOrFail();

        $walnut500 = ProductVariant::query()
            ->whereHas('product', function ($query) {
                $query->where('slug', 'sua-oc-cho');
            })
            ->where('name', '500ml')
            ->firstOrFail();

        $almond500 = ProductVariant::query()
            ->whereHas('product', function ($query) {
                $query->where('slug', 'sua-hanh-nhan');
            })
            ->where('name', '500ml')
            ->firstOrFail();

        $macadamia500 = ProductVariant::query()
            ->whereHas('product', function ($query) {
                $query->where('slug', 'sua-mac-ca');
            })
            ->where('name', '500ml')
            ->firstOrFail();

        $healthyCombo = Combo::create([
            'name' => 'Combo Healthy 3 Chai',
            'slug' => 'combo-healthy-3-chai',
            'description' => 'Combo 3 loại sữa hạt thơm ngon, phù hợp cho bữa sáng và bổ sung dinh dưỡng mỗi ngày.',
            'price' => 70000,
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $healthyCombo->items()->createMany([
            [
                'product_variant_id' => $walnut250->id,
                'quantity' => 1,
            ],
            [
                'product_variant_id' => $almond250->id,
                'quantity' => 1,
            ],
            [
                'product_variant_id' => $macadamia250->id,
                'quantity' => 1,
            ],
        ]);

        $energyCombo = Combo::create([
            'name' => 'Combo Năng Lượng 5 Chai',
            'slug' => 'combo-nang-luong-5-chai',
            'description' => 'Combo sữa hạt dung tích lớn, phù hợp sử dụng trong ngày.',
            'price' => 160000,
            'is_active' => true,
            'sort_order' => 2,
        ]);

        $energyCombo->items()->createMany([
            [
                'product_variant_id' => $walnut500->id,
                'quantity' => 2,
            ],
            [
                'product_variant_id' => $almond500->id,
                'quantity' => 2,
            ],
            [
                'product_variant_id' => $macadamia500->id,
                'quantity' => 1,
            ],
        ]);
    }
}
