<?php

namespace App\Models;

use Database\Factories\AttributeValueFactory;
use Illuminate\Database\Eloquent\Casts\Attribute as CastsAttribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Значение EAV-свойства, привязанное к произвольной сущности.
 */
class AttributeValue extends Model
{
    /** @use HasFactory<AttributeValueFactory> */
    use HasFactory;

    protected $fillable = [
        'attribute_id',
        'value',
    ];

    /**
     * @return BelongsTo<Attribute, $this>
     */
    public function attribute(): BelongsTo
    {
        return $this->belongsTo(Attribute::class);
    }

    /**
     * Сущность-владелец значения (Client, Project, …).
     *
     * @return MorphTo<Model, $this>
     */
    public function entity(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Значение, приведённое к типу свойства (int, float, bool, Carbon, …).
     *
     * @return CastsAttribute<mixed, mixed>
     */
    protected function typed(): CastsAttribute
    {
        return CastsAttribute::make(
            get: fn () => $this->attribute?->type->cast($this->value),
            set: fn ($value) => ['value' => $this->attribute?->type->serialize($value)],
        );
    }
}
