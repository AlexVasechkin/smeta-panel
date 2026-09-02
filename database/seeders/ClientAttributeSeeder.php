<?php

namespace Database\Seeders;

use App\Models\Attribute;
use App\Models\Client;
use App\Support\Passport;
use Illuminate\Database\Seeder;

/**
 * EAV-свойства клиента: паспортные данные физлица.
 */
class ClientAttributeSeeder extends Seeder
{
    public function run(): void
    {
        $entityType = (new Client)->getMorphClass();
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
