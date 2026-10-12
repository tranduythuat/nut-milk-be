<?php

namespace Database\Seeders;

use App\Models\RawMaterial;
use App\Services\RawMaterialInventoryService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class RawMaterialSeeder extends Seeder
{
    public function run(RawMaterialInventoryService $inventory): void
    {
        // unit LUÔN là base unit (g / ml / cai) - xem config/units.php
        $materials = [
            ['code' => 'RM-WALNUT',     'name' => 'Hạt óc chó',     'unit' => 'g',   'stock' => 5000,  'min_stock' => 1000],
            ['code' => 'RM-ALMOND',     'name' => 'Hạt hạnh nhân',  'unit' => 'g',   'stock' => 3000,  'min_stock' => 1000],
            ['code' => 'RM-MACADAMIA',  'name' => 'Hạt mắc ca',     'unit' => 'g',   'stock' => 3000,  'min_stock' => 1000],
            ['code' => 'RM-WATER',      'name' => 'Nước lọc',       'unit' => 'ml',  'stock' => 50000, 'min_stock' => 10000],
            ['code' => 'RM-BOTTLE250',  'name' => 'Chai 250ml',     'unit' => 'cai', 'stock' => 200,   'min_stock' => 50],
            ['code' => 'RM-BOTTLE500',  'name' => 'Chai 500ml',     'unit' => 'cai', 'stock' => 100,   'min_stock' => 50],
            // Cố ý để dưới mức tối thiểu để thử lệnh raw-materials:check-low-stock
            ['code' => 'RM-BOTTLE1000', 'name' => 'Chai 1L',        'unit' => 'cai', 'stock' => 40,    'min_stock' => 50],
        ];

        foreach ($materials as $data) {
            $material = RawMaterial::updateOrCreate(
                ['code' => $data['code']],
                [
                    'name' => $data['name'],
                    'unit' => $data['unit'],
                    'min_stock' => $data['min_stock'],
                    'is_active' => true,
                ]
            );

            // Chỉ nhập kho lần đầu, tránh nhân đôi tồn khi seed lại.
            if ($material->wasRecentlyCreated) {
                DB::transaction(fn() => $inventory->receive(
                    $material,
                    (float) $data['stock'],
                    null,
                    null,
                    'Tồn kho ban đầu (seeder).'
                ));
            }
        }
    }
}
