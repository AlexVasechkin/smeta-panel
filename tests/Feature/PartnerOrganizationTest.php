<?php

namespace Tests\Feature;

use App\Enums\OrganizationType;
use App\Models\Organization;
use App\Models\User;
use Database\Seeders\OrganizationAttributeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PartnerOrganizationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Пользователь-владелец организации: владелец справочника партнёров.
     *
     * @return array{0: User, 1: Organization}
     */
    private function userWithOrganization(): array
    {
        $user = User::factory()->create();
        $organization = Organization::create(['name' => 'Своя орг']);
        $organization->members()->create(['user_id' => $user->id, 'is_owner' => true]);

        return [$user, $organization];
    }

    /**
     * Организация-партнёр в справочнике переданной организации.
     */
    private function partnerFor(Organization $parent, array $attributes = []): Organization
    {
        $partner = Organization::create($attributes + [
            'type' => OrganizationType::Ooo->value,
            'name' => 'ООО Партнёр',
        ]);

        $parent->partnerLinks()->create(['organization_id' => $partner->id]);

        return $partner;
    }

    /**
     * Минимальный корректный набор полей формы.
     */
    private function payload(array $overrides = []): array
    {
        return $overrides + [
            'type' => OrganizationType::Ooo->value,
            'name' => 'ООО Ромашка',
        ];
    }

    public function test_guests_cannot_see_the_directory(): void
    {
        $this->get(route('partners.index'))->assertRedirect(route('login'));
    }

    public function test_directory_lists_only_own_partners(): void
    {
        [$user, $organization] = $this->userWithOrganization();
        $this->partnerFor($organization, ['name' => 'Свой партнёр']);

        // Партнёр чужой организации в справочник попадать не должен.
        $otherOrganization = Organization::create(['name' => 'Чужая орг']);
        $this->partnerFor($otherOrganization, ['name' => 'Чужой партнёр']);

        $this->actingAs($user)
            ->get(route('partners.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('partners/index')
                ->has('organizationTypes')
                ->has('partners.data', 1)
                ->where('partners.data.0.name', 'Свой партнёр'));
    }

    public function test_directory_can_be_filtered_by_search_and_type(): void
    {
        [$user, $organization] = $this->userWithOrganization();
        $this->partnerFor($organization, ['name' => 'ООО Ромашка', 'inn' => '7701234567']);
        $this->partnerFor($organization, ['name' => 'ИП Петров', 'type' => OrganizationType::Ip->value]);

        $this->actingAs($user)
            ->get(route('partners.index', ['search' => 'Ромашка']))
            ->assertInertia(fn (Assert $page) => $page
                ->has('partners.data', 1)
                ->where('partners.data.0.name', 'ООО Ромашка'));

        $this->actingAs($user)
            ->get(route('partners.index', ['type' => OrganizationType::Ip->value]))
            ->assertInertia(fn (Assert $page) => $page
                ->has('partners.data', 1)
                ->where('partners.data.0.name', 'ИП Петров'));
    }

    public function test_create_page_is_displayed(): void
    {
        [$user] = $this->userWithOrganization();

        $this->actingAs($user)
            ->get(route('partners.create'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('partners/create')
                ->has('organizationTypes', 2)
                ->has('passportTypes'));
    }

    public function test_organization_is_stored_and_linked_to_the_users_organization(): void
    {
        [$user, $organization] = $this->userWithOrganization();

        $this->actingAs($user)
            ->post(route('partners.store'), $this->payload([
                'legal_name' => 'Общество с ограниченной ответственностью «Ромашка»',
                'inn' => '7701234567',
                'kpp' => '770101001',
                'ogrn' => '1027700132195',
                'director_surname' => 'Иванов',
                'director_name' => 'Иван',
                'director_father_name' => 'Иванович',
                'bank_bik' => '044525225',
            ]))
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('partners.index'));

        $this->assertDatabaseHas('organizations', [
            'name' => 'ООО Ромашка',
            'type' => OrganizationType::Ooo->value,
            'inn' => '7701234567',
            'director_surname' => 'Иванов',
        ]);

        $partner = Organization::where('name', 'ООО Ромашка')->sole();

        $this->assertDatabaseHas('partner_organization_links', [
            'parent_organization_id' => $organization->id,
            'organization_id' => $partner->id,
        ]);

        $this->assertTrue($organization->partners()->whereKey($partner->id)->exists());
    }

    public function test_director_passport_is_stored_in_eav(): void
    {
        $this->seed(OrganizationAttributeSeeder::class);

        [$user] = $this->userWithOrganization();

        $this->actingAs($user)
            ->post(route('partners.store'), $this->payload([
                'passport' => [
                    'type' => 'Гражданина РФ',
                    'series' => '4509',
                    'number' => '123456',
                    'issued_by' => 'ОУФМС по г. Москве',
                    'issued_at' => '2015-06-20',
                    'registration_address' => 'г. Москва, ул. Ленина, д. 1',
                ],
            ]))
            ->assertSessionHasNoErrors();

        $partner = Organization::where('name', 'ООО Ромашка')->sole();

        $this->assertSame('Гражданина РФ', $partner->getEav('passport_type'));
        $this->assertSame('4509', $partner->getEav('passport_series'));
        $this->assertSame('123456', $partner->getEav('passport_number'));
        $this->assertSame('ОУФМС по г. Москве', $partner->getEav('passport_issued_by'));
        $this->assertSame('2015-06-20', $partner->getEav('passport_issued_at')->format('Y-m-d'));
        $this->assertSame('г. Москва, ул. Ленина, д. 1', $partner->getEav('passport_registration_address'));
    }

    public function test_it_requires_a_type_and_a_name(): void
    {
        [$user] = $this->userWithOrganization();

        $this->actingAs($user)
            ->from(route('partners.create'))
            ->post(route('partners.store'), ['type' => '', 'name' => ''])
            ->assertSessionHasErrors(['type', 'name']);
    }

    public function test_it_rejects_an_unknown_type(): void
    {
        [$user] = $this->userWithOrganization();

        $this->actingAs($user)
            ->from(route('partners.create'))
            ->post(route('partners.store'), $this->payload(['type' => 'zao']))
            ->assertSessionHasErrors('type');
    }

    public function test_inn_length_must_match_the_type(): void
    {
        [$user] = $this->userWithOrganization();

        // У ООО ИНН из 10 цифр, 12-значный — ошибка.
        $this->actingAs($user)
            ->from(route('partners.create'))
            ->post(route('partners.store'), $this->payload(['inn' => '770123456789']))
            ->assertSessionHasErrors('inn');

        // У ИП — наоборот.
        $this->actingAs($user)
            ->from(route('partners.create'))
            ->post(route('partners.store'), $this->payload([
                'type' => OrganizationType::Ip->value,
                'inn' => '7701234567',
            ]))
            ->assertSessionHasErrors('inn');

        $this->actingAs($user)
            ->post(route('partners.store'), $this->payload([
                'type' => OrganizationType::Ip->value,
                'inn' => '770123456789',
                'ogrn' => '304770000000019',
            ]))
            ->assertSessionHasNoErrors();
    }

    public function test_it_rejects_a_duplicate_inn_in_the_same_directory(): void
    {
        [$user, $organization] = $this->userWithOrganization();
        $this->partnerFor($organization, ['inn' => '7701234567']);

        $this->actingAs($user)
            ->from(route('partners.create'))
            ->post(route('partners.store'), $this->payload(['inn' => '7701234567']))
            ->assertSessionHasErrors('inn');
    }

    public function test_kpp_is_dropped_for_an_entrepreneur(): void
    {
        [$user] = $this->userWithOrganization();

        $this->actingAs($user)
            ->post(route('partners.store'), $this->payload([
                'type' => OrganizationType::Ip->value,
                'name' => 'ИП Петров',
                'kpp' => '770101001',
            ]))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('organizations', [
            'name' => 'ИП Петров',
            'kpp' => null,
        ]);
    }

    public function test_partner_can_be_updated(): void
    {
        $this->seed(OrganizationAttributeSeeder::class);

        [$user, $organization] = $this->userWithOrganization();
        $partner = $this->partnerFor($organization);

        $this->actingAs($user)
            ->put(route('partners.update', $partner), $this->payload([
                'name' => 'ООО Новое имя',
                'phone' => '+7 999 000-00-00',
                'passport' => ['series' => '1234'],
            ]))
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('partners.index'));

        $partner->refresh();

        $this->assertSame('ООО Новое имя', $partner->name);
        $this->assertSame('+7 999 000-00-00', $partner->phone);
        $this->assertSame('1234', $partner->getEav('passport_series'));
    }

    public function test_show_and_edit_pages_are_displayed(): void
    {
        [$user, $organization] = $this->userWithOrganization();
        $partner = $this->partnerFor($organization);

        $this->actingAs($user)
            ->get(route('partners.show', $partner))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('partners/show')
                ->where('partner.name', 'ООО Партнёр')
                ->where('partner.type_label', 'ООО')
                ->has('partner.passport'));

        $this->actingAs($user)
            ->get(route('partners.edit', $partner))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('partners/edit')
                ->where('partner.name', 'ООО Партнёр')
                ->has('organizationTypes'));
    }

    public function test_a_partner_of_another_organization_is_not_accessible(): void
    {
        [$user] = $this->userWithOrganization();

        $otherOrganization = Organization::create(['name' => 'Чужая орг']);
        $foreign = $this->partnerFor($otherOrganization);

        $this->actingAs($user)->get(route('partners.show', $foreign))->assertNotFound();
        $this->actingAs($user)->get(route('partners.edit', $foreign))->assertNotFound();
        $this->actingAs($user)->put(route('partners.update', $foreign), $this->payload())->assertNotFound();
        $this->actingAs($user)->delete(route('partners.destroy', $foreign))->assertNotFound();
    }

    public function test_partner_is_removed_together_with_its_link_and_eav_values(): void
    {
        $this->seed(OrganizationAttributeSeeder::class);

        [$user, $organization] = $this->userWithOrganization();
        $partner = $this->partnerFor($organization);
        $partner->setEav('passport_series', '4509');

        $this->actingAs($user)
            ->delete(route('partners.destroy', $partner))
            ->assertRedirect(route('partners.index'));

        $this->assertDatabaseMissing('partner_organization_links', ['organization_id' => $partner->id]);
        $this->assertDatabaseMissing('organizations', ['id' => $partner->id]);
        $this->assertDatabaseMissing('attribute_values', [
            'entity_type' => $partner->getMorphClass(),
            'entity_id' => $partner->id,
        ]);
    }

    public function test_removing_a_partner_keeps_an_organization_that_is_still_in_use(): void
    {
        [$user, $organization] = $this->userWithOrganization();

        // Одна и та же организация в справочниках двух разных организаций.
        $otherOrganization = Organization::create(['name' => 'Другая орг']);
        $partner = $this->partnerFor($organization);
        $otherOrganization->partnerLinks()->create(['organization_id' => $partner->id]);

        $this->actingAs($user)->delete(route('partners.destroy', $partner));

        $this->assertDatabaseHas('organizations', ['id' => $partner->id]);
        $this->assertDatabaseHas('partner_organization_links', [
            'parent_organization_id' => $otherOrganization->id,
            'organization_id' => $partner->id,
        ]);
        $this->assertDatabaseMissing('partner_organization_links', [
            'parent_organization_id' => $organization->id,
            'organization_id' => $partner->id,
        ]);
    }

    public function test_a_user_without_an_organization_cannot_use_the_directory(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('partners.index'))->assertNotFound();
        $this->actingAs($user)->post(route('partners.store'), $this->payload())->assertForbidden();
    }
}
