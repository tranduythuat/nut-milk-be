<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('order_item_components', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_item_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->foreignId('product_variant_id')
                ->nullable()
                ->constrained()
                ->nullOnDelete();

            // Snapshot tên variant tại thời điểm đặt hàng
            $table->string('variant_name');

            // Snapshot attributes, ví dụ:
            // {"volume_ml":250}
            $table->json('attributes')->nullable();

            // Số lượng variant trong 1 combo
            $table->unsignedInteger('quantity')->default(1);

            $table->timestamps();

            $table->index('product_variant_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('order_item_components');
    }
};
