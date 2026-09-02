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
        // Значения EAV-свойств. Полиморфная привязка к любой сущности (entity),
        // значение хранится строкой и приводится к типу через AttributeType.
        Schema::create('attribute_values', function (Blueprint $table) {
            $table->id();
            $table->foreignId('attribute_id')->constrained()->cascadeOnDelete();
            $table->morphs('entity'); // entity_type + entity_id (+ индекс)
            $table->text('value')->nullable();
            $table->timestamps();

            // Одно значение свойства на сущность.
            $table->unique(['attribute_id', 'entity_type', 'entity_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('attribute_values');
    }
};
