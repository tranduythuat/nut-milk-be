<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('delivery_items', function (Blueprint $table) {
            $table->id();

            $table->foreignId('delivery_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->foreignId('order_item_id')
                ->constrained()
                ->cascadeOnDelete();

            /*
             * Số lượng của order_item này được giao TRONG ĐỢT NÀY.
             * - Với sản phẩm lẻ: số chai.
             * - Với combo: số BỘ combo (KHÔNG BAO GIỜ là số lẻ component
             *   trong 1 bộ — combo luôn đi trọn vẹn theo đơn vị).
             *
             * Bất biến bắt buộc (enforce ở tầng service trong transaction,
             * không enforce được bằng DB constraint vì cần SUM theo nhóm):
             *   SUM(quantity) theo order_item_id, qua TẤT CẢ delivery
             *   chưa bị hủy, PHẢI BẰNG ĐÚNG order_items.quantity.
             */
            $table->unsignedInteger('quantity');

            $table->timestamps();

            $table->unique(['delivery_id', 'order_item_id'], 'delivery_item_unique');
            $table->index('order_item_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('delivery_items');
    }
};
