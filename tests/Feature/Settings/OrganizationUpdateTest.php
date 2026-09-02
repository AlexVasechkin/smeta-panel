<?php

namespace Tests\Feature\Settings;

use App\Models\Organization;
use App\Models\User;
use Database\Seeders\OrganizationAttributeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class OrganizationUpdateTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Пользователь-владелец организации.
     */
    private function userWithOrganization(): array
    {
        $user = User::factory()->create();
        $organization = Organization::create(['name' => 'Орг']);
        $organization->members()->create(['user_id' => $user->id, 'is_owner' => true]);

        return [$user, $organization];
    }

    public function test_organization_page_is_displayed(): void
    {
        [$user] = $this->userWithOrganization();

        $this->actingAs($user)
            ->get(route('organization.edit'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('settings/organization')
                ->has('organization')
                ->has('directorPassport')
                ->has('passportTypes'));
    }

    public function test_director_details_can_be_updated(): void
    {
        [$user, $organization] = $this->userWithOrganization();

        $this->actingAs($user)
            ->patch(route('organization.update'), [
                'name' => 'ООО Ромашка',
                'director_surname' => 'Иванов',
                'director_name' => 'Иван',
                'director_father_name' => 'Иванович',
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('organization.edit'));

        $this->assertDatabaseHas('organizations', [
            'id' => $organization->id,
            'name' => 'ООО Ромашка',
            'director_surname' => 'Иванов',
            'director_name' => 'Иван',
            'director_father_name' => 'Иванович',
        ]);
    }

    public function test_director_passport_is_stored_in_eav(): void
    {
        $this->seed(OrganizationAttributeSeeder::class);

        [$user, $organization] = $this->userWithOrganization();

        $this->actingAs($user)
            ->patch(route('organization.update'), [
                'passport' => [
                    'type' => 'Гражданина РФ',
                    'series' => '4509',
                    'number' => '123456',
                    'issued_by' => 'ОУФМС по г. Москве',
                    'issued_at' => '2015-06-20',
                    'registration_address' => 'г. Москва, ул. Ленина, д. 1, кв. 2',
                ],
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('organization.edit'));

        $organization->refresh();

        $this->assertSame('Гражданина РФ', $organization->getEav('passport_type'));
        $this->assertSame('4509', $organization->getEav('passport_series'));
        $this->assertSame('123456', $organization->getEav('passport_number'));
        $this->assertSame('ОУФМС по г. Москве', $organization->getEav('passport_issued_by'));
        $this->assertSame('2015-06-20', $organization->getEav('passport_issued_at')->format('Y-m-d'));
        $this->assertSame('г. Москва, ул. Ленина, д. 1, кв. 2', $organization->getEav('passport_registration_address'));
    }

    public function test_it_rejects_an_unknown_director_passport_type(): void
    {
        $this->seed(OrganizationAttributeSeeder::class);

        [$user] = $this->userWithOrganization();

        $this->actingAs($user)
            ->from(route('organization.edit'))
            ->patch(route('organization.update'), [
                'passport' => ['type' => 'Гражданина США'],
            ])
            ->assertSessionHasErrors('passport.type');
    }
}
