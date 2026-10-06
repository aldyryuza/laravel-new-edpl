<?php

namespace Tests\Feature;

use App\Http\Middleware\EnsureUserIsActive;
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

    public function test_successful_login_records_last_login_time(): void
    {
        $this->freezeSecond();
        $user = User::factory()->create(['nik' => '1234567890']);

        $this->post('/login', ['nik' => '1234567890', 'password' => 'password']);

        $this->assertTrue($user->fresh()->last_login_at->equalTo(now()));
    }

    public function test_inactive_user_cannot_login_even_with_correct_password(): void
    {
        $user = User::factory()->create(['nik' => '1234567890', 'is_active' => false]);

        $this->from('/login')
            ->post('/login', ['nik' => '1234567890', 'password' => 'password'])
            ->assertRedirect('/login')
            ->assertSessionHasErrors(['nik' => EnsureUserIsActive::MESSAGE]);

        $this->assertGuest();
        $this->assertNull($user->fresh()->last_login_at);
    }

    public function test_inactive_user_with_wrong_password_gets_generic_message(): void
    {
        User::factory()->create(['nik' => '1234567890', 'is_active' => false]);

        $this->from('/login')
            ->post('/login', ['nik' => '1234567890', 'password' => 'salah'])
            ->assertSessionHasErrors(['nik' => 'NIK atau password salah.']);
    }

    public function test_user_deactivated_during_session_is_logged_out(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->get('/dashboard')->assertOk();

        $user->update(['is_active' => false]);

        $this->get('/dashboard')->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_deactivated_user_api_request_returns_401(): void
    {
        $user = User::factory()->create(['is_active' => false]);

        $this->actingAs($user)
            ->getJson('/api/users/datatable')
            ->assertUnauthorized()
            ->assertJsonPath('message', EnsureUserIsActive::MESSAGE);
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
