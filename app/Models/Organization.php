<?php

namespace App\Models;

use App\Enums\OrganizationType;
use App\Models\Concerns\HasEavAttributes;
use Database\Factories\OrganizationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOneThrough;

class Organization extends Model
{
    /** @use HasFactory<OrganizationFactory> */
    use HasEavAttributes, HasFactory;

    protected $fillable = [
        'type',
        'name',
        'legal_name',
        'inn',
        'kpp',
        'ogrn',
        'address',
        'phone',
        'email',
        'director_name',
        'director_surname',
        'director_father_name',
        'bank_name',
        'bank_bik',
        'bank_account',
        'bank_corr_account',
    ];

    protected function casts(): array
    {
        return [
            'type' => OrganizationType::class,
        ];
    }

    /**
     * @return HasMany<OrganizationMember, $this>
     */
    public function members(): HasMany
    {
        return $this->hasMany(OrganizationMember::class);
    }

    /**
     * @return BelongsToMany<User, $this>
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'organization_members')
            ->withPivot('is_owner')
            ->withTimestamps();
    }

    /**
     * Владелец организации (единственный участник с is_owner = true).
     *
     * @return HasOneThrough<User, OrganizationMember, $this>
     */
    public function owner(): HasOneThrough
    {
        return $this->hasOneThrough(
            User::class,
            OrganizationMember::class,
            'organization_id',
            'id',
            'id',
            'user_id',
        )->where('organization_members.is_owner', true);
    }

    /**
     * Записи справочника: с какими организациями может работать эта организация.
     *
     * @return HasMany<PartnerOrganizationLink, $this>
     */
    public function partnerLinks(): HasMany
    {
        return $this->hasMany(PartnerOrganizationLink::class, 'parent_organization_id');
    }

    /**
     * Организации-партнёры из справочника этой организации.
     *
     * @return BelongsToMany<Organization, $this>
     */
    public function partners(): BelongsToMany
    {
        return $this->belongsToMany(
            self::class,
            'partner_organization_links',
            'parent_organization_id',
            'organization_id',
        )->withTimestamps();
    }

    /**
     * Организации, в справочниках которых числится эта организация.
     *
     * @return BelongsToMany<Organization, $this>
     */
    public function partnerOf(): BelongsToMany
    {
        return $this->belongsToMany(
            self::class,
            'partner_organization_links',
            'organization_id',
            'parent_organization_id',
        )->withTimestamps();
    }
}
