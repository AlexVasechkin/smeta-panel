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
        // Определения EAV-свойств (справочник атрибутов).
        Schema::create('attributes', function (Blueprint $table) {
            $table->id();
            $table->string('entity_type');             // тип сущности-владельца (morph class), напр. App\Models\Project
            $table->string('code');                    // машинное имя, напр. floor_area
            $table->string('name');                    // человекочитаемое название
            $table->string('type')->default('string'); // App\Enums\AttributeType
            $table->string('unit')->nullable();        // ед. изм.: м², шт, м.п. …
            $table->json('options')->nullable();       // варианты для типа "select"
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            // Код уникален в пределах типа сущности.
            $table->unique(['entity_type', 'code']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('attributes');
    }
};
