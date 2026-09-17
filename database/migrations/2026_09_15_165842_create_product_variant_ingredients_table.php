<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_variant_ingredients', function (Blueprint $table) {
            $table->id();

            $table->foreignId('product_variant_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->foreignId('raw_material_id')
                ->constrained()
                ->restrictOnDelete();

            // Định lượng cần cho 1 đơn vị variant (vd: 1 chai 250ml cần 0.25 L sữa nền)
            $table->decimal('quantity', 14, 4);

            $table->timestamps();

            $table->unique(
                ['product_variant_id', 'raw_material_id'],
                'variant_ingredient_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_variant_ingredients');
    }
};
