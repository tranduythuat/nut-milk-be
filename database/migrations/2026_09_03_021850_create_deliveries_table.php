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
        Schema::create('deliveries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')
                ->unique()
                ->constrained()
                ->cascadeOnDelete();

            $table->foreignId('delivery_slot_id')
                ->nullable()
                ->constrained()
                ->nullOnDelete();

            $table->date('delivery_date');

            $table->string('status')->default('pending');

            $table->string('address');

            $table->string('recipient_name');

            $table->string('recipient_phone');

            $table->text('note')->nullable();

            $table->timestamp('shipped_at')->nullable();

            $table->timestamp('delivered_at')->nullable();

            $table->timestamps();

            $table->index([
                'delivery_date',
                'delivery_slot_id',
                'status',
            ]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('deliveries');
    }
};
