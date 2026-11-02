<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Шаблон документа: .docx с плейсхолдерами {{ ... }}, лежащий на диске
 * (в проде — S3), плюс разобранный состав полей.
 *
 * Шаблоны не общие: каждый принадлежит одной организации.
 */
class DocumentTemplate extends Model
{
    protected $fillable = [
        'organization_id',
        'name',
        'description',
        'disk',
        'path',
        'original_name',
        'size',
        'checksum',
        'placeholders',
        'warnings',
    ];

    protected function casts(): array
    {
        return [
            'placeholders' => 'array',
            'warnings' => 'array',
            'size' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Organization, $this>
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /**
     * Шаблоны одной организации.
     *
     * @param  Builder<DocumentTemplate>  $query
     */
    public function scopeForOrganization(Builder $query, int $organizationId): void
    {
        $query->where('organization_id', $organizationId);
    }

    /**
     * Одиночные поля шаблона.
     *
     * @return array<int, string>
     */
    public function fields(): array
    {
        return $this->placeholders['fields'] ?? [];
    }

    /**
     * Табличные группы: имя группы => список колонок.
     *
     * @return array<string, array<int, string>>
     */
    public function tables(): array
    {
        return $this->placeholders['tables'] ?? [];
    }
}
