<?php

namespace Database\Seeders;

use App\Models\City;
use Illuminate\Database\Seeder;

class CitySeeder extends Seeder
{
    /**
     * Города-миллионники России (население свыше 1 млн).
     * Москва и Санкт-Петербург выведены наверх через sort_order,
     * остальные — со значением по умолчанию (500) и сортировкой по алфавиту.
     */
    public function run(): void
    {
        $cities = [
            ['name' => 'Москва', 'sort_order' => 10],
            ['name' => 'Санкт-Петербург', 'sort_order' => 20],
            ['name' => 'Новосибирск'],
            ['name' => 'Екатеринбург'],
            ['name' => 'Казань'],
            ['name' => 'Нижний Новгород'],
            ['name' => 'Красноярск'],
            ['name' => 'Челябинск'],
            ['name' => 'Самара'],
            ['name' => 'Уфа'],
            ['name' => 'Ростов-на-Дону'],
            ['name' => 'Краснодар'],
            ['name' => 'Омск'],
            ['name' => 'Воронеж'],
            ['name' => 'Пермь'],
            ['name' => 'Волгоград'],
        ];

        foreach ($cities as $city) {
            City::updateOrCreate(
                ['name' => $city['name']],
                ['sort_order' => $city['sort_order'] ?? 500, 'is_active' => true],
            );
        }
    }
}
