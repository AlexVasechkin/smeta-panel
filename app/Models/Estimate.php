<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Смета объекта: позиции работ и отделочных материалов.
 */
class Estimate extends Model
{
    protected $fillable = [
        'project_id',
    ];

    /**
     * @return BelongsTo<Project, $this>
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * @return HasMany<EstimateWorkItem, $this>
     */
    public function workItems(): HasMany
    {
        return $this->hasMany(EstimateWorkItem::class)->orderBy('position');
    }

    /**
     * @return HasMany<EstimateMaterialItem, $this>
     */
    public function materialItems(): HasMany
    {
        return $this->hasMany(EstimateMaterialItem::class)->orderBy('position');
    }
}
