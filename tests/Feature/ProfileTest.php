<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_page_is_available_without_menu_access(): void
    {
        $user = User::factory()->create(['nik' => '7777']);

        $this->actingAs($user)->get('/profile')->assertOk()->assertSee('7777');
    }

    public function test_user_can_update_own_profile_but_not_nik(): void
    {
        $user = User::factory()->create(['nik' => '7777']);

        $this->actingAs($user)
            ->putJson('/api/profile', ['nik' => '9999', 'name' => 'Nama Baru', 'email' => 'baru@example.com'])
            ->assertOk()
            ->assertJsonPath('data.name', 'Nama Baru');

        $user->refresh();
        $this->assertSame('Nama Baru', $user->name);
        $this->assertSame('baru@example.com', $user->email);
        $this->assertSame('7777', $user->nik);
    }

    public function test_email_must_be_unique_except_own(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        $this->actingAs($user)
            ->putJson('/api/profile', ['name' => $user->name, 'email' => $other->email])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('email');

        $this->actingAs($user)
            ->putJson('/api/profile', ['name' => $user->name, 'email' => $user->email])
            ->assertOk();
    }

    public function test_password_change_requires_current_password(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->putJson('/api/profile/password', [
                'current_password' => 'salah',
                'password' => 'passwordbaru',
                'password_confirmation' => 'passwordbaru',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('current_password');

        $this->actingAs($user)
            ->putJson('/api/profile/password', [
                'current_password' => 'password',
                'password' => 'passwordbaru',
                'password_confirmation' => 'passwordbaru',
            ])
            ->assertOk();

        $this->assertTrue(Hash::check('passwordbaru', $user->fresh()->password));
    }
}
