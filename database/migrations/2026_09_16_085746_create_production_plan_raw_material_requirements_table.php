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
        Schema::create('production_plan_raw_material_requirements', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('production_plan_id');
            $table->unsignedBigInteger('raw_material_id');

            $table->decimal('required_quantity', 14, 4)->default(0);
            $table->decimal('reserved_quantity', 14, 4)->default(0);
            $table->decimal('consumed_quantity', 14, 4)->default(0);

            $table->timestamps();

            $table->foreign('production_plan_id', 'pprr_plan_fk')
                ->references('id')
                ->on('production_plans')
                ->cascadeOnDelete();

            $table->foreign('raw_material_id', 'pprr_mat_fk')
                ->references('id')
                ->on('raw_materials')
                ->restrictOnDelete();

            $table->unique(
                ['production_plan_id', 'raw_material_id'],
                'plan_raw_material_unique'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('production_plan_raw_material_requirements');
    }
};
