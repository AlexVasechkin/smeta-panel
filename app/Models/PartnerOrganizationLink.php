<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Запись справочника «организация — организация-партнёр»: с кем может
 * работать организация-владелец (parent_organization_id).
 */
class PartnerOrganizationLink extends Model
{
    protected $fillable = [
        'parent_organization_id',
        'organization_id',
    ];

    /**
     * Организация-владелец справочника.
     *
     * @return BelongsTo<Organization, $this>
     */
    public function parentOrganization(): BelongsTo
    {
        return $this->belongsTo(Organization::class, 'parent_organization_id');
    }

    /**
     * Организация-партнёр.
     *
     * @return BelongsTo<Organization, $this>
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }
}
