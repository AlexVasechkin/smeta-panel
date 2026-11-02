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
        // Справочник организаций, с которыми может работать организация-владелец.
        // Обе стороны связи — записи таблицы organizations.
        Schema::create('partner_organization_links', function (Blueprint $table) {
            $table->id();

            // Организация-владелец справочника (своя организация пользователя).
            $table->foreignId('parent_organization_id')
                ->constrained('organizations')
                ->cascadeOnDelete();

            // Организация-партнёр.
            $table->foreignId('organization_id')
                ->constrained('organizations')
                ->cascadeOnDelete();

            $table->timestamps();

            // Один партнёр добавляется в справочник организации только один раз.
            $table->unique(['parent_organization_id', 'organization_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('partner_organization_links');
    }
};
