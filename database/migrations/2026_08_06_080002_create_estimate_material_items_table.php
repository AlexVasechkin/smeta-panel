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
        // Позиции сметы отделочных материалов (без подкатегорий).
        Schema::create('estimate_material_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('estimate_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('position')->default(0);
            $table->string('name');
            $table->string('unit')->nullable();
            $table->decimal('quantity', 12, 3)->default(0);
            $table->decimal('price', 12, 2)->default(0);
            $table->decimal('total', 14, 2)->default(0); // quantity * price
            $table->timestamps();

            $table->index(['estimate_id', 'position']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('estimate_material_items');
    }
};
