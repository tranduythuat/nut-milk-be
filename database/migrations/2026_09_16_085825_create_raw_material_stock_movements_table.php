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
        Schema::create('raw_material_stock_movements', function (Blueprint $table) {
            $table->id();

            $table->foreignId('raw_material_id')
                ->constrained()
                ->cascadeOnDelete();

            // receive | reserve | release | consume | reverse_consume | adjustment
            $table->string('type');

            $table->decimal('quantity', 14, 4);
            $table->decimal('stock_before', 14, 4)->nullable();
            $table->decimal('stock_after', 14, 4)->nullable();

            $table->nullableMorphs('reference'); // vd: ProductionPlan

            $table->text('note')->nullable();

            $table->timestamps();

            $table->index(['raw_material_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('raw_material_stock_movements');
    }
};
