<?php

namespace Tests\Feature\Settings;

use App\Models\User;
use Database\Seeders\UserAttributeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProfileUpdateTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_page_is_displayed()
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->get('/settings/profile');

        $response->assertOk();
    }

    public function test_profile_information_can_be_updated()
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->patch('/settings/profile', [
                'name' => 'Test User',
                'email' => 'test@example.com',
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect('/settings/profile');

        $user->refresh();

        $this->assertSame('Test User', $user->name);
        $this->assertSame('test@example.com', $user->email);
        $this->assertNull($user->email_verified_at);
    }

    public function test_email_verification_status_is_unchanged_when_the_email_address_is_unchanged()
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->patch('/settings/profile', [
                'name' => 'Test User',
                'email' => $user->email,
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect('/settings/profile');

        $this->assertNotNull($user->refresh()->email_verified_at);
    }

    public function test_passport_data_is_stored_in_eav()
    {
        $this->seed(UserAttributeSeeder::class);

        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->patch('/settings/profile', [
                'name' => $user->name,
                'email' => $user->email,
                'passport' => [
                    'type' => 'Гражданина РФ',
                    'series' => '4509',
                    'number' => '123456',
                    'issued_by' => 'ОУФМС по г. Москве',
                    'issued_at' => '2015-06-20',
                ],
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect('/settings/profile');

        $user->refresh();

        $this->assertSame('Гражданина РФ', $user->getEav('passport_type'));
        $this->assertSame('4509', $user->getEav('passport_series'));
        $this->assertSame('123456', $user->getEav('passport_number'));
        $this->assertSame('ОУФМС по г. Москве', $user->getEav('passport_issued_by'));
        $this->assertSame('2015-06-20', $user->getEav('passport_issued_at')->format('Y-m-d'));
    }

    public function test_it_rejects_an_unknown_passport_type()
    {
        $this->seed(UserAttributeSeeder::class);

        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->from('/settings/profile')
            ->patch('/settings/profile', [
                'name' => $user->name,
                'email' => $user->email,
                'passport' => ['type' => 'Гражданина США'],
            ]);

        $response->assertSessionHasErrors('passport.type');
    }

    public function test_user_can_delete_their_account()
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->delete('/settings/profile', [
                'password' => 'password',
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect('/');

        $this->assertGuest();
        $this->assertNull($user->fresh());
    }

    public function test_correct_password_must_be_provided_to_delete_account()
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->from('/settings/profile')
            ->delete('/settings/profile', [
                'password' => 'wrong-password',
            ]);

        $response
            ->assertSessionHasErrors('password')
            ->assertRedirect('/settings/profile');

        $this->assertNotNull($user->fresh());
    }
}
