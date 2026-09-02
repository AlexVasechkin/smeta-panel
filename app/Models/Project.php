<?php

namespace App\Models;

use App\Enums\ProjectStatus;
use App\Models\Concerns\HasEavAttributes;
use Database\Factories\ProjectFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Project extends Model
{
    /** @use HasFactory<ProjectFactory> */
    use HasEavAttributes, HasFactory;

    protected $fillable = [
        'client_id',
        'city_id',
        'name',
        'order_number',
        'address',
        'area',
        'rooms',
        'status',
        'start_date',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'status' => ProjectStatus::class,
            'area' => 'decimal:2',
            'start_date' => 'date',
        ];
    }

    protected static function booted(): void
    {
        // Номер заказа присваивается автоматически при создании.
        static::creating(function (Project $project) {
            if (blank($project->order_number)) {
                $project->order_number = static::generateOrderNumber();
            }
        });
    }

    /**
     * Следующий номер заказа вида «2026-0001» (сквозная нумерация в пределах года).
     */
    public static function generateOrderNumber(): string
    {
        $prefix = now()->year.'-';

        $lastSeq = static::query()
            ->where('order_number', 'like', $prefix.'%')
            ->pluck('order_number')
            ->map(fn (string $number) => (int) substr($number, strlen($prefix)))
            ->max() ?? 0;

        return $prefix.str_pad((string) ($lastSeq + 1), 4, '0', STR_PAD_LEFT);
    }

    /**
     * @return BelongsTo<Client, $this>
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    /**
     * @return BelongsTo<City, $this>
     */
    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class);
    }

    /**
     * @return HasMany<Document, $this>
     */
    public function documents(): HasMany
    {
        return $this->hasMany(Document::class)->latest('id');
    }

    /**
     * @return HasOne<Estimate, $this>
     */
    public function estimate(): HasOne
    {
        return $this->hasOne(Estimate::class);
    }
}
