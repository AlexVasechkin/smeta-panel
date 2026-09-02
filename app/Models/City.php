<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class City extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'organization_id',
        'name',
        'sort_order',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'sort_order' => 'integer',
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
     * @return HasMany<Project, $this>
     */
    public function projects(): HasMany
    {
        return $this->hasMany(Project::class);
    }

    /**
     * Только активные города.
     *
     * @param  Builder<City>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    /**
     * Города, доступные организации: системные (organization_id = null)
     * плюс собственные города переданной организации.
     *
     * @param  Builder<City>  $query
     */
    public function scopeAvailableFor(Builder $query, ?int $organizationId): void
    {
        $query->where(function (Builder $q) use ($organizationId) {
            $q->whereNull('organization_id');

            if ($organizationId !== null) {
                $q->orWhere('organization_id', $organizationId);
            }
        });
    }

    /**
     * Порядок отображения: сначала системные города, затем пользовательские;
     * внутри каждой группы — по sort_order, затем по алфавиту.
     *
     * @param  Builder<City>  $query
     */
    public function scopeOrdered(Builder $query): void
    {
        $query->orderByRaw('organization_id is not null')
            ->orderBy('sort_order')
            ->orderBy('name');
    }
}
