<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_items', function (Blueprint $table) {
            $table->id();

            $table->foreignId('order_id')
                ->constrained()
                ->cascadeOnDelete();

            /*
             |--------------------------------------------------------------------------
             | Original references
             |--------------------------------------------------------------------------
             */

            $table->foreignId('product_variant_id')
                ->nullable()
                ->constrained()
                ->nullOnDelete();

            $table->foreignId('combo_id')
                ->nullable()
                ->constrained()
                ->nullOnDelete();

            /*
             |--------------------------------------------------------------------------
             | Snapshot product information
             |--------------------------------------------------------------------------
             */

            $table->string('item_name');

            $table->string('variant_name')
                ->nullable();

            $table->json('attributes')
                ->nullable();

            /*
             |--------------------------------------------------------------------------
             | Pricing
             |--------------------------------------------------------------------------
             */

            $table->unsignedInteger('quantity');

            $table->unsignedBigInteger('unit_price');

            $table->unsignedBigInteger('total_price');

            $table->timestamps();

            $table->index('product_variant_id');

            $table->index('combo_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_items');
    }
};
