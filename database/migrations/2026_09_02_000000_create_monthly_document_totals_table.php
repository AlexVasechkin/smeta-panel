<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Помесячный агрегат сумм по документам определённых типов (акты выполненных
     * работ, акты приёма-передачи денег и т.п.). Обновляется наблюдателем Document
     * (created/deleted) пересчётом соответствующего (type, месяц).
     */
    public function up(): void
    {
        Schema::create('monthly_document_totals', function (Blueprint $table) {
            $table->id();
            $table->string('type');                      // App\Enums\DocumentType
            $table->date('period');                      // первый день месяца
            $table->decimal('total', 15, 2)->default(0); // сумма по документам типа за месяц
            $table->timestamps();

            $table->unique(['type', 'period']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('monthly_document_totals');
    }
};
