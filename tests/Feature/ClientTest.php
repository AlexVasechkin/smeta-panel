<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Project;
use App\Models\User;
use Database\Seeders\ClientAttributeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ClientTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ClientAttributeSeeder::class);
        $this->actingAs(User::factory()->create());
    }

    public function test_it_lists_clients(): void
    {
        Client::factory()->count(3)->create();

        $this->get(route('clients.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('clients/index')
                ->has('clients.data', 3));
    }

    public function test_it_creates_a_client(): void
    {
        $this->post(route('clients.store'), [
            'type' => 'company',
            'name' => 'ООО Ромашка',
        ])->assertRedirect(route('clients.index'));

        $this->assertDatabaseHas('clients', ['name' => 'ООО Ромашка', 'type' => 'company']);
    }

    public function test_it_stores_name_parts_for_an_individual(): void
    {
        $this->post(route('clients.store'), [
            'type' => 'individual',
            'name' => 'Иван',
            'surname' => 'Петров',
            'father_name' => 'Сергеевич',
        ])->assertRedirect(route('clients.index'));

        $this->assertDatabaseHas('clients', [
            'type' => 'individual',
            'name' => 'Иван',
            'surname' => 'Петров',
            'father_name' => 'Сергеевич',
        ]);
    }

    public function test_it_requires_a_name(): void
    {
        $this->post(route('clients.store'), ['type' => 'individual', 'name' => ''])
            ->assertSessionHasErrors('name');
    }

    public function test_it_rejects_an_invalid_type(): void
    {
        $this->post(route('clients.store'), ['type' => 'unknown', 'name' => 'Иван'])
            ->assertSessionHasErrors('type');
    }

    public function test_it_updates_a_client(): void
    {
        $client = Client::factory()->create(['name' => 'Старое имя']);

        $this->put(route('clients.update', $client), [
            'type' => $client->type->value,
            'name' => 'Новое имя',
        ])->assertRedirect(route('clients.index'));

        $this->assertSame('Новое имя', $client->fresh()->name);
    }

    public function test_it_deletes_a_client_and_cascades_projects(): void
    {
        $client = Client::factory()->has(Project::factory()->count(2))->create();

        $this->delete(route('clients.destroy', $client))
            ->assertRedirect(route('clients.index'));

        $this->assertDatabaseMissing('clients', ['id' => $client->id]);
        $this->assertSame(0, Project::count());
    }

    public function test_it_blocks_guests(): void
    {
        auth()->logout();

        $this->get(route('clients.index'))->assertRedirect(route('login'));
    }

    public function test_it_stores_passport_data_for_an_individual(): void
    {
        $this->post(route('clients.store'), [
            'type' => 'individual',
            'name' => 'Иван Петров',
            'passport' => [
                'type' => 'Гражданина РФ',
                'series' => '4509',
                'number' => '123456',
                'issued_by' => 'ОУФМС по г. Москве',
                'issued_at' => '2015-06-20',
                'registration_address' => 'г. Москва, ул. Ленина, д. 1, кв. 2',
            ],
        ])->assertRedirect(route('clients.index'));

        $client = Client::firstWhere('name', 'Иван Петров');

        $this->assertSame('Гражданина РФ', $client->getEav('passport_type'));
        $this->assertSame('4509', $client->getEav('passport_series'));
        $this->assertSame('123456', $client->getEav('passport_number'));
        $this->assertSame('ОУФМС по г. Москве', $client->getEav('passport_issued_by'));
        $this->assertSame('2015-06-20', $client->getEav('passport_issued_at')->format('Y-m-d'));
        $this->assertSame('г. Москва, ул. Ленина, д. 1, кв. 2', $client->getEav('passport_registration_address'));
    }

    public function test_it_rejects_an_unknown_passport_type(): void
    {
        $this->post(route('clients.store'), [
            'type' => 'individual',
            'name' => 'Иван Петров',
            'passport' => ['type' => 'Гражданина США'],
        ])->assertSessionHasErrors('passport.type');
    }

    public function test_it_ignores_passport_data_for_a_company(): void
    {
        $this->post(route('clients.store'), [
            'type' => 'company',
            'name' => 'ООО Ромашка',
            'passport' => ['series' => '4509', 'number' => '123456'],
        ])->assertRedirect(route('clients.index'));

        $client = Client::firstWhere('name', 'ООО Ромашка');

        $this->assertSame(0, $client->attributeValues()->count());
    }

    public function test_it_updates_passport_data(): void
    {
        $client = Client::factory()->individual()->create();
        $client->setEav('passport_series', '1111');

        $this->put(route('clients.update', $client), [
            'type' => 'individual',
            'name' => $client->name,
            'passport' => ['series' => '2222', 'number' => '654321'],
        ])->assertRedirect(route('clients.index'));

        $this->assertSame('2222', $client->fresh()->getEav('passport_series'));
        $this->assertSame('654321', $client->fresh()->getEav('passport_number'));
    }

    public function test_show_exposes_passport_only_for_individuals(): void
    {
        $individual = Client::factory()->individual()->create();
        $individual->setEav('passport_series', '4509');
        $company = Client::factory()->company()->create();

        $this->get(route('clients.show', $individual))
            ->assertInertia(fn (Assert $page) => $page
                ->component('clients/show')
                ->where('client.passport.series', '4509'));

        $this->get(route('clients.show', $company))
            ->assertInertia(fn (Assert $page) => $page
                ->component('clients/show')
                ->where('client.passport', null));
    }
}
