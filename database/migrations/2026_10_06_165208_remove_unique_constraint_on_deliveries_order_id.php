<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('deliveries', function (Blueprint $table) {
            // Phải drop foreign key TRƯỚC, vì unique index 'order_id'
            // đang được FK dùng làm index ngầm — MySQL không cho drop
            // unique index khi nó còn bị FK phụ thuộc (lỗi 1553).
            $table->dropForeign(['order_id']);

            // Giờ mới drop được unique index.
            $table->dropUnique(['order_id']);

            // Tạo lại index thường (không unique) để FK vẫn có index
            // hỗ trợ tra cứu nhanh — chỉ là không còn ràng buộc 1-1 nữa.
            $table->index('order_id');

            // Tạo lại foreign key, gắn trên index thường vừa tạo.
            $table->foreign('order_id')
                ->references('id')
                ->on('orders')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('deliveries', function (Blueprint $table) {
            $table->dropForeign(['order_id']);
            $table->dropIndex(['order_id']);

            $table->unique('order_id');

            $table->foreign('order_id')
                ->references('id')
                ->on('orders')
                ->cascadeOnDelete();
        });
    }
};
