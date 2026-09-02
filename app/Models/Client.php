<?php

namespace App\Models;

use App\Enums\ClientType;
use App\Models\Concerns\HasEavAttributes;
use Database\Factories\ClientFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Client extends Model
{
    /** @use HasFactory<ClientFactory> */
    use HasEavAttributes, HasFactory;

    protected $fillable = [
        'type',
        'name',
        'surname',
        'father_name',
        'phone',
        'email',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'type' => ClientType::class,
        ];
    }

    /**
     * @return HasMany<Project, $this>
     */
    public function projects(): HasMany
    {
        return $this->hasMany(Project::class);
    }
}
