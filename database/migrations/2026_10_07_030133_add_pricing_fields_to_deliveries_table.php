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
        Schema::table('deliveries', function (Blueprint $table) {
            /*
             * Giá trị tiền hàng của ĐÚNG đợt giao này (tổng
             * quantity * unit_price của các delivery_items thuộc
             * đợt này). Tính và lưu CỐ ĐỊNH tại thời điểm checkout
             * — không tính lại động, để làm cơ sở hoàn tiền chính
             * xác khi đợt này bị hủy, kể cả nếu giá sản phẩm sau
             * này thay đổi.
             */
            $table->unsignedBigInteger('item_subtotal')->default(0)->after('note');

            /*
             * Phí giao hàng CỦA RIÊNG đợt này (không phải tổng phí
             * cả đơn) — vì chia nhiều đợt có thể phát sinh phí vận
             * chuyển riêng cho mỗi đợt.
             */
            $table->unsignedBigInteger('delivery_fee')->default(0)->after('item_subtotal');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('deliveries', function (Blueprint $table) {
            $table->dropColumn(['item_subtotal', 'delivery_fee']);
        });
    }
};
