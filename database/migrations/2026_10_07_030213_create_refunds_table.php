<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('refunds', function (Blueprint $table) {
            $table->id();

            $table->foreignId('order_id')
                ->constrained()
                ->cascadeOnDelete();

            // Đợt giao bị hủy sinh ra khoản hoàn này. Nullable để dự
            // phòng trường hợp hoàn tiền không gắn với 1 delivery cụ
            // thể (vd điều chỉnh thủ công khác) trong tương lai.
            $table->foreignId('delivery_id')
                ->nullable()
                ->constrained()
                ->nullOnDelete();

            $table->unsignedBigInteger('amount');

            /*
             * cod_adjustment: chưa hề thu tiền (COD/pending/failed) ->
             *   chỉ là điều chỉnh giảm số tiền SẼ thu, không có dòng
             *   tiền thật di chuyển -> tự động completed ngay khi tạo.
             * cash_refund: tiền đã thu thật (payment_status=paid) ->
             *   cần nhân viên CHỦ ĐỘNG chuyển khoản lại cho khách rồi
             *   mới xác nhận hoàn tất.
             */
            $table->string('type'); // cod_adjustment | cash_refund

            $table->string('status')->default('pending'); // pending | completed

            $table->text('note')->nullable();

            $table->timestamp('processed_at')->nullable();

            $table->timestamps();

            $table->index(['order_id', 'created_at']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('refunds');
    }
};
