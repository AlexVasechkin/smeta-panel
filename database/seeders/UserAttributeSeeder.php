<?php

namespace Database\Seeders;

use App\Models\Attribute;
use App\Models\User;
use App\Support\Passport;
use Illuminate\Database\Seeder;

/**
 * EAV-свойства пользователя: паспортные данные.
 */
class UserAttributeSeeder extends Seeder
{
    public function run(): void
    {
        $entityType = (new User)->getMorphClass();
        $sortOrder = 0;

        foreach (Passport::FIELDS as $definition) {
            Attribute::updateOrCreate(
                ['entity_type' => $entityType, 'code' => $definition['code']],
                [
                    'name' => $definition['name'],
                    'type' => $definition['type'],
                    'options' => $definition['options'] ?? null,
                    'sort_order' => $sortOrder++,
                ],
            );
        }
    }
}
