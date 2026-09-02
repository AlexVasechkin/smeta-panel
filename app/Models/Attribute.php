<?php

namespace App\Models;

use App\Enums\AttributeType;
use Database\Factories\AttributeFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Определение EAV-свойства (атрибута). Привязано к типу сущности-владельца.
 */
class Attribute extends Model
{
    /** @use HasFactory<AttributeFactory> */
    use HasFactory;

    protected $fillable = [
        'entity_type',
        'code',
        'name',
        'type',
        'unit',
        'options',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'type' => AttributeType::class,
            'options' => 'array',
        ];
    }

    /**
     * Ограничить свойства типом сущности (по классу модели или экземпляру).
     *
     * @param  Builder<Attribute>  $query
     */
    public function scopeForEntity(Builder $query, Model|string $entity): void
    {
        $type = $entity instanceof Model ? $entity->getMorphClass() : $entity;

        $query->where('entity_type', $type);
    }

    /**
     * @return HasMany<AttributeValue, $this>
     */
    public function values(): HasMany
    {
        return $this->hasMany(AttributeValue::class);
    }
}
