<?php

namespace App\Models;

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
}
