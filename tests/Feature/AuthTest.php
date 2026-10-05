<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_page_is_shown_to_guest(): void
    {
        $this->get('/login')->assertOk()->assertSee('name="nik"', escape: false);
    }

    public function test_user_can_login_with_nik_and_password(): void
    {
        $user = User::factory()->create(['nik' => '1234567890']);

        $response = $this->post('/login', ['nik' => '1234567890', 'password' => 'password']);

        $response->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($user);
    }

    public function test_login_fails_with_wrong_password(): void
    {
        User::factory()->create(['nik' => '1234567890']);

        $response = $this->from('/login')->post('/login', ['nik' => '1234567890', 'password' => 'salah']);

        $response->assertRedirect('/login')->assertSessionHasErrors('nik');
        $this->assertGuest();
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get('/dashboard')->assertRedirect(route('login'));
    }

    public function test_guest_api_request_returns_401(): void
    {
        $this->getJson('/api/users/datatable')->assertUnauthorized();
    }

    public function test_user_can_logout(): void
    {
        $this->actingAs(User::factory()->create())
            ->post('/logout')
            ->assertRedirect(route('login'));

        $this->assertGuest();
    }
}
