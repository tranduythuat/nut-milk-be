<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        /*
         * OrderStatus thu gọn còn: pending, confirmed, cancelled, completed.
         * 'processing' và 'shipping' KHÔNG CÒN tồn tại ở tầng Order —
         * trạng thái chi tiết đó giờ sống hoàn toàn ở Delivery.status
         * (mỗi đợt giao có vòng đời riêng: pending -> preparing ->
         * shipping -> delivered).
         *
         * Dữ liệu cũ: đơn đang 'processing'/'shipping' -> hạ về 'confirmed'
         * (coi như chưa hoàn tất), vì trạng thái thật đã nằm ở deliveries
         * rồi — Order chỉ là lớp tổng hợp.
         */
        DB::table('orders')
            ->whereIn('status', ['processing', 'shipping'])
            ->update(['status' => 'confirmed']);

        Schema::table('orders', function (Blueprint $table) {
            $table->timestamp('completed_at')->nullable()->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('completed_at');
        });
    }
};
