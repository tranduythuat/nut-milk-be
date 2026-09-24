<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Bảng quy đổi cố định, KHÔNG phụ thuộc config runtime — migration
     * phải tự chứa để chạy ổn định dù config/units.php sau này đổi.
     */
    protected array $factors = [
        'g'   => 1,
        'kg'  => 1000,
        'ml'  => 1,
        'l'   => 1000,
        'cai' => 1,
    ];

    protected array $baseUnitByLegacy = [
        'g'   => 'g',
        'kg'  => 'g',
        'ml'  => 'ml',
        'l'   => 'ml',
        'cai' => 'cai',
    ];

    public function up(): void
    {
        $materials = DB::table('raw_materials')->get();

        foreach ($materials as $material) {
            $unit = $material->unit;

            if (! array_key_exists($unit, $this->factors)) {
                // Đơn vị lạ ngoài bảng quy đổi đã biết -> bỏ qua,
                // cần xử lý thủ công (kiểm tra log migration).
                continue;
            }

            $factor = $this->factors[$unit];

            if ($factor === 1) {
                // Đã là base unit, không cần quy đổi.
                continue;
            }

            $baseUnit = $this->baseUnitByLegacy[$unit];

            DB::transaction(function () use ($material, $factor, $baseUnit) {
                DB::table('raw_materials')
                    ->where('id', $material->id)
                    ->update([
                        'stock' => $material->stock * $factor,
                        'reserved_quantity' => $material->reserved_quantity * $factor,
                        'min_stock' => $material->min_stock !== null
                            ? $material->min_stock * $factor
                            : null,
                        'unit' => $baseUnit,
                    ]);

                DB::table('product_variant_ingredients')
                    ->where('raw_material_id', $material->id)
                    ->update(['quantity' => DB::raw("quantity * {$factor}")]);

                DB::table('production_plan_raw_material_requirements')
                    ->where('raw_material_id', $material->id)
                    ->update([
                        'required_quantity' => DB::raw("required_quantity * {$factor}"),
                        'reserved_quantity' => DB::raw("reserved_quantity * {$factor}"),
                        'consumed_quantity' => DB::raw("consumed_quantity * {$factor}"),
                    ]);

                DB::table('raw_material_stock_movements')
                    ->where('raw_material_id', $material->id)
                    ->update([
                        'quantity' => DB::raw("quantity * {$factor}"),
                        'stock_before' => DB::raw("stock_before * {$factor}"),
                        'stock_after' => DB::raw("stock_after * {$factor}"),
                    ]);
            });
        }
    }

    public function down(): void
    {
        // KHÔNG hỗ trợ rollback tự động — quy đổi ngược có nguy cơ mất
        // độ chính xác thông tin đơn vị gốc. Nếu cần revert, khôi phục
        // từ backup trước khi chạy migration này.
    }
};
