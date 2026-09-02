<?php

namespace App\Models\Concerns;

use App\Models\Attribute;
use App\Models\AttributeValue;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * Добавляет модели хранение произвольных EAV-свойств.
 */
trait HasEavAttributes
{
    /**
     * @return MorphMany<AttributeValue, $this>
     */
    public function attributeValues(): MorphMany
    {
        return $this->morphMany(AttributeValue::class, 'entity');
    }

    /**
     * Значение свойства по коду, приведённое к его типу (или null).
     */
    public function getEav(string $code): mixed
    {
        return $this->attributeValues()
            ->with('attribute')
            ->whereHas('attribute', fn ($query) => $query->where('code', $code))
            ->first()
            ?->typed;
    }

    /**
     * Установить (или обновить) значение свойства по коду.
     * Свойство должно быть объявлено для типа этой сущности.
     */
    public function setEav(string $code, mixed $value): AttributeValue
    {
        $attribute = Attribute::query()
            ->forEntity($this)
            ->where('code', $code)
            ->firstOrFail();

        return $this->attributeValues()->updateOrCreate(
            ['attribute_id' => $attribute->id],
            ['value' => $attribute->type->serialize($value)],
        );
    }

    /**
     * Все свойства, объявленные для типа этой сущности.
     *
     * @return Collection<int, Attribute>
     */
    public function availableAttributes(): Collection
    {
        return Attribute::query()
            ->forEntity($this)
            ->orderBy('sort_order')
            ->get();
    }

    /**
     * Карта всех заданных свойств: [код => типизированное значение].
     *
     * @return array<string, mixed>
     */
    public function eav(): array
    {
        return $this->attributeValues()
            ->with('attribute')
            ->get()
            ->mapWithKeys(fn (AttributeValue $value) => [$value->attribute->code => $value->typed])
            ->all();
    }
}
