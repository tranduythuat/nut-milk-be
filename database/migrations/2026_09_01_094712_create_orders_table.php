<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();

            $table->string('order_number')
                ->unique();

            $table->foreignId('user_id')
                ->nullable()
                ->constrained()
                ->nullOnDelete();

            /*
             |--------------------------------------------------------------------------
             | Customer Information
             |--------------------------------------------------------------------------
             */

            $table->string('customer_name');

            $table->string('customer_phone');

            $table->text('customer_address');

            $table->text('note')
                ->nullable();

            /*
             |--------------------------------------------------------------------------
             | Pricing
             |--------------------------------------------------------------------------
             */

            $table->unsignedBigInteger('subtotal')
                ->default(0);

            $table->unsignedBigInteger('delivery_fee')
                ->default(0);

            $table->unsignedBigInteger('discount_amount')
                ->default(0);

            $table->unsignedBigInteger('total')
                ->default(0);

            /*
             |--------------------------------------------------------------------------
             | Order Status
             |--------------------------------------------------------------------------
             */

            $table->string('status')
                ->default('pending');

            $table->string('payment_status')
                ->default('pending');

            $table->string('payment_method')
                ->nullable();

            $table->timestamps();

            $table->index('status');

            $table->index('payment_status');

            $table->index('customer_phone');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
