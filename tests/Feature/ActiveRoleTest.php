<?php

namespace Tests\Feature;

use App\Http\Middleware\ResolveActiveRole;
use App\Models\Menu;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ActiveRoleTest extends TestCase
{
    use RefreshDatabase;

    private Role $reader;

    private Role $viewer;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $menu = Menu::factory()->route('users.index')->create(['code' => 'users']);

        // reader boleh membuka menu User, viewer tidak.
        $this->reader = Role::factory()->create(['name' => 'Reader']);
        $this->reader->menuAccesses()->create(['menu_id' => $menu->id, 'can_read' => true]);
        $this->viewer = Role::factory()->create(['name' => 'Viewer']);

        $this->user = User::factory()->create(['nik' => '5555']);
        $this->user->roles()->attach([$this->reader->id, $this->viewer->id]);
    }

    public function test_login_with_single_role_uses_it_automatically(): void
    {
        $single = User::factory()->create(['nik' => '1111']);
        $single->roles()->attach($this->reader);

        $this->post('/login', ['nik' => '1111', 'password' => 'password'])->assertRedirect(route('dashboard'));

        $this->get('/users')->assertOk();
        $this->assertSame($this->reader->id, session(ResolveActiveRole::SESSION_KEY));
    }

    public function test_login_with_multiple_roles_must_choose_role(): void
    {
        $this->post('/login', ['nik' => '5555', 'password' => 'password'])
            ->assertRedirect(route('active-role.create'));

        $this->get('/active-role')->assertOk()->assertSee('Reader')->assertSee('Viewer');

        // Belum memilih role: halaman lain diarahkan ke pilih role, API ditolak.
        $this->get('/dashboard')->assertRedirect(route('active-role.create'));
        $this->getJson('/api/users/datatable')->assertStatus(409);

        $this->post('/active-role', ['role_id' => $this->reader->id])->assertRedirect(route('dashboard'));
        $this->get('/users')->assertOk();
    }

    public function test_user_can_switch_role_without_logging_in_again(): void
    {
        $this->actingAs($this->user)->withSession([ResolveActiveRole::SESSION_KEY => $this->reader->id]);
        $this->get('/users')->assertOk();

        $this->post('/active-role', ['role_id' => $this->viewer->id])
            ->assertRedirect(route('dashboard'))
            ->assertSessionHas('success');

        $this->get('/users')->assertForbidden();
        $this->assertAuthenticatedAs($this->user);
    }

    public function test_cannot_choose_role_not_owned(): void
    {
        $other = Role::factory()->create();

        $this->actingAs($this->user)
            ->from('/active-role')
            ->post('/active-role', ['role_id' => $other->id])
            ->assertSessionHasErrors('role_id');

        $this->assertNull(session(ResolveActiveRole::SESSION_KEY));
    }

    public function test_revoked_active_role_is_dropped(): void
    {
        $this->actingAs($this->user)->withSession([ResolveActiveRole::SESSION_KEY => $this->reader->id]);

        $this->user->roles()->detach($this->reader);

        // Tinggal 1 role -> otomatis pindah ke role yang tersisa.
        $this->get('/users')->assertForbidden();
        $this->assertSame($this->viewer->id, session(ResolveActiveRole::SESSION_KEY));
    }

    public function test_navbar_lists_roles_for_switching(): void
    {
        $this->actingAs($this->user)->withSession([ResolveActiveRole::SESSION_KEY => $this->reader->id]);

        $this->get('/dashboard')
            ->assertOk()
            ->assertSee('Ganti role')
            ->assertSee(route('active-role.store'), escape: false);
    }
}
