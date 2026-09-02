<?php

namespace App\Models;

use App\Enums\DocumentType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class Document extends Model
{
    protected $fillable = [
        'project_id',
        'created_by',
        'type',
        'title',
        'disk',
        'path',
        'payload',
    ];

    protected function casts(): array
    {
        return [
            'type' => DocumentType::class,
            'payload' => 'array',
        ];
    }

    /**
     * @return BelongsTo<Project, $this>
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Диск хранилища, на котором лежит файл документа.
     */
    public function storage(): \Illuminate\Contracts\Filesystem\Filesystem
    {
        return Storage::disk($this->disk);
    }

    /**
     * Имя файла для скачивания.
     */
    public function fileName(): string
    {
        return basename($this->path);
    }
}
