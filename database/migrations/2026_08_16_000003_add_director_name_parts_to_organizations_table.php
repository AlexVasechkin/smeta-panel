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
        Schema::table('organizations', function (Blueprint $table) {
            // ФИО руководителя по частям (director_name — имя).
            $table->string('director_surname')->nullable()->after('director_name');
            $table->string('director_father_name')->nullable()->after('director_surname');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('organizations', function (Blueprint $table) {
            $table->dropColumn(['director_surname', 'director_father_name']);
        });
    }
};
