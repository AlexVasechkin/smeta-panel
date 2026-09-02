<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Models\Concerns\HasEavAttributes;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOneThrough;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasEavAttributes, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * @return HasMany<OrganizationMember, $this>
     */
    public function memberships(): HasMany
    {
        return $this->hasMany(OrganizationMember::class);
    }

    /**
     * @return BelongsToMany<Organization, $this>
     */
    public function organizations(): BelongsToMany
    {
        return $this->belongsToMany(Organization::class, 'organization_members')
            ->withPivot('is_owner')
            ->withTimestamps();
    }

    /**
     * Организация, которой владеет пользователь (создана при регистрации).
     *
     * @return HasOneThrough<Organization, OrganizationMember, $this>
     */
    public function ownedOrganization(): HasOneThrough
    {
        return $this->hasOneThrough(
            Organization::class,
            OrganizationMember::class,
            'user_id',
            'id',
            'id',
            'organization_id',
        )->where('organization_members.is_owner', true);
    }

    /**
     * Текущая организация пользователя: собственная либо первая, где он состоит.
     */
    public function currentOrganization(): ?Organization
    {
        return $this->ownedOrganization ?? $this->organizations()->first();
    }
}
