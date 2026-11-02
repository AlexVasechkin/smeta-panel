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
        // Хранилище шаблонов документов: сам .docx лежит на диске (S3),
        // в таблице — карточка шаблона и разобранный состав плейсхолдеров.
        Schema::create('document_templates', function (Blueprint $table) {
            $table->id();

            // Шаблоны не общие: каждый принадлежит организации-владельцу.
            $table->foreignId('organization_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->string('name');
            $table->text('description')->nullable();

            // Диск из config/filesystems.php и ключ объекта на нём.
            $table->string('disk');
            $table->string('path');

            // Имя файла, под которым шаблон загрузили, — для скачивания.
            $table->string('original_name');
            $table->unsignedBigInteger('size');

            // sha256 нормализованного файла: видно, менялся ли шаблон.
            $table->string('checksum', 64);

            // Результат разбора: {"fields": [...], "tables": {"works": [...]}}.
            $table->json('placeholders');

            // Замечания парсера (плейсхолдер вне таблицы, битое имя и т.п.).
            $table->json('warnings');

            $table->timestamps();

            // В пределах организации имя шаблона уникально.
            $table->unique(['organization_id', 'name']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('document_templates');
    }
};
