<?php

namespace Tests\Feature;

use App\Http\Middleware\ResolveActiveRole;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OnlineUserTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('broadcasting.default', 'reverb');
        config()->set('broadcasting.connections.reverb.key', 'test-key');
        config()->set('broadcasting.connections.reverb.secret', 'test-secret');
        config()->set('broadcasting.connections.reverb.app_id', '1');

        // phpunit.xml memakai broadcaster `null` saat boot; daftarkan ulang channel ke reverb.
        require base_path('routes/channels.php');
    }

    public function test_presence_payload_contains_active_role(): void
    {
        $user = User::factory()->create(['nik' => '42', 'name' => 'Ani']);
        $user->setActiveRole(Role::factory()->create(['name' => 'Admin Gudang']));

        $this->assertSame(
            ['id' => $user->id, 'nik' => '42', 'name' => 'Ani', 'role' => 'Admin Gudang', 'page' => '/users', 'title' => 'User'],
            $user->toOnlinePresence('/users', 'User'),
        );
    }

    public function test_presence_page_info_from_browser_is_sanitized(): void
    {
        $user = User::factory()->create();

        $payload = $user->toOnlinePresence('https://evil.example/x', '<b>Judul</b>');

        $this->assertNull($payload['page']);
        $this->assertSame('Judul', $payload['title']);
    }

    public function test_logged_in_user_is_authorized_for_presence_channel(): void
    {
        $user = $this->userWithAccess([]);

        $response = $this->actingAs($user)
            ->postJson('/broadcasting/auth', [
                'socket_id' => '1234.5678',
                'channel_name' => 'presence-online',
                'page' => '/dashboard',
                'title' => 'Dashboard',
            ])
            ->assertOk();

        $this->assertStringStartsWith('test-key:', $response->json('auth'));

        $info = json_decode($response->json('channel_data'), true)['user_info'];
        $this->assertSame($user->name, $info['name']);
        $this->assertSame('/dashboard', $info['page']);
        $this->assertSame('Dashboard', $info['title']);
    }

    public function test_guest_is_not_authorized_for_presence_channel(): void
    {
        $this->postJson('/broadcasting/auth', ['socket_id' => '1234.5678', 'channel_name' => 'presence-online'])
            ->assertForbidden();
    }

    public function test_online_users_page_requires_access(): void
    {
        $this->actingAs($this->userWithAccess([]))->get('/online-users')->assertForbidden();

        $this->actingAs($this->userWithAccess(['online-users' => ['read']]))
            ->get('/online-users')
            ->assertOk()
            ->assertSee('online-users.js')
            ->assertSee('data-online-count', escape: false);
    }

    public function test_every_logged_in_page_joins_realtime_but_navbar_count_needs_access(): void
    {
        $this->actingAs($this->userWithAccess([]))
            ->get('/dashboard')
            ->assertSee('id="realtime-config"', escape: false)
            ->assertSee('"key":"test-key"', escape: false)
            ->assertSee('js/realtime.js', escape: false)
            ->assertDontSee('data-online-count', escape: false);
    }

    public function test_realtime_is_not_loaded_before_role_is_chosen(): void
    {
        $user = User::factory()->create();
        $user->roles()->attach(Role::factory()->count(2)->create());

        $this->actingAs($user)
            ->get('/active-role')
            ->assertOk()
            ->assertDontSee('id="realtime-config"', escape: false);

        $this->assertNull(session(ResolveActiveRole::SESSION_KEY));
    }
}
