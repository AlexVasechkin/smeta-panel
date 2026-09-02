<?php

namespace Database\Seeders;

use App\Models\Attribute;
use App\Models\Organization;
use App\Support\Passport;
use Illuminate\Database\Seeder;

/**
 * EAV-свойства организации: паспортные данные руководителя.
 */
class OrganizationAttributeSeeder extends Seeder
{
    public function run(): void
    {
        $entityType = (new Organization)->getMorphClass();
        $sortOrder = 0;

        foreach (Passport::extendedFields() as $definition) {
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
