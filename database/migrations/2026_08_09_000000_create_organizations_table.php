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
        Schema::create('organizations', function (Blueprint $table) {
            $table->id();
            $table->string('name')->nullable();              // краткое наименование
            $table->string('legal_name')->nullable();        // полное юридическое наименование
            $table->string('inn', 12)->nullable();           // ИНН
            $table->string('kpp', 9)->nullable();            // КПП
            $table->string('ogrn', 15)->nullable();          // ОГРН / ОГРНИП
            $table->string('address')->nullable();           // юридический адрес
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->string('director_name')->nullable();     // ФИО руководителя
            $table->string('bank_name')->nullable();         // наименование банка
            $table->string('bank_bik', 9)->nullable();       // БИК
            $table->string('bank_account', 20)->nullable();  // расчётный счёт
            $table->string('bank_corr_account', 20)->nullable(); // корреспондентский счёт
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('organizations');
    }
};
