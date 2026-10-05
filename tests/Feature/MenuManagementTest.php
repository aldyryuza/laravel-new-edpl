<?php

namespace Tests\Feature;

use App\Models\Menu;
use App\Models\Role;
use App\Models\RoleMenuAccess;
use App\Models\User;
use Database\Seeders\AccessControlSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MenuManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_menu_can_be_created(): void
    {
        $this->actingAs($this->userWithAccess(['menus' => ['create']]))
            ->postJson('/api/menus', [
                'code' => 'purchase-request',
                'title' => 'Purchase Request',
                'route' => 'purchase-requests.index',
                'sort_order' => 5,
                'is_active' => true,
            ])
            ->assertCreated();

        $this->assertDatabaseHas('menus', ['code' => 'purchase-request', 'parent_id' => null]);
    }

    public function test_menu_code_cannot_contain_dot(): void
    {
        $this->actingAs($this->userWithAccess(['menus' => ['create']]))
            ->postJson('/api/menus', ['code' => 'users.list', 'title' => 'X', 'sort_order' => 0, 'is_active' => true])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('code');
    }

    public function test_parent_cannot_be_own_descendant(): void
    {
        $parent = Menu::factory()->create();
        $child = Menu::factory()->create(['parent_id' => $parent->id]);

        $this->actingAs($this->userWithAccess(['menus' => ['update']]))
            ->putJson("/api/menus/{$parent->id}", [
                'parent_id' => $child->id,
                'code' => $parent->code,
                'title' => $parent->title,
                'sort_order' => 0,
                'is_active' => true,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('parent_id');
    }

    public function test_menu_with_children_cannot_be_deleted(): void
    {
        $actor = $this->userWithAccess(['menus' => ['delete']]);
        $parent = Menu::factory()->create();
        $child = Menu::factory()->create(['parent_id' => $parent->id]);
        Role::factory()->create()->menuAccesses()->create(['menu_id' => $child->id, 'can_read' => true]);

        $this->actingAs($actor)->deleteJson("/api/menus/{$parent->id}")->assertUnprocessable();

        $this->actingAs($actor)->deleteJson("/api/menus/{$child->id}")->assertOk();
        $this->assertModelMissing($child);
        $this->assertDatabaseMissing('role_menu_accesses', ['menu_id' => $child->id]);
    }

    public function test_sidebar_shows_only_menus_user_can_read(): void
    {
        $group = Menu::factory()->create(['code' => 'settings', 'title' => 'Grup Pengaturan']);
        Menu::factory()->route('users.index')->create(['code' => 'users', 'title' => 'Menu User', 'parent_id' => $group->id]);
        Menu::factory()->route('roles.index')->create(['code' => 'roles', 'title' => 'Menu Role', 'parent_id' => $group->id]);
        Menu::factory()->route('menus.index')->create(['code' => 'menus', 'title' => 'Menu Menu']);
        Menu::factory()->create(['code' => 'empty-group', 'title' => 'Grup Kosong']);
        Menu::factory()->route('example.index')->inactive()->create(['code' => 'example', 'title' => 'Menu Nonaktif']);

        $user = $this->userWithAccess(['users' => ['read'], 'roles' => ['create'], 'example' => ['read']]);

        $this->actingAs($user)->get('/dashboard')
            ->assertSee('Grup Pengaturan')
            ->assertSee('Menu User')
            ->assertDontSee('Menu Role')
            ->assertDontSee('Menu Menu')
            ->assertDontSee('Grup Kosong')
            ->assertDontSee('Menu Nonaktif');
    }

    public function test_management_pages_render_for_administrator(): void
    {
        $this->seed(AccessControlSeeder::class);
        $this->actingAs(User::query()->firstWhere('nik', 'admin'));
        $role = Role::query()->firstWhere('name', Role::ADMINISTRATOR);

        $this->get('/users')->assertOk()->assertSee('users.js');
        $this->get('/roles')->assertOk()->assertSee('roles.js');
        $this->get("/roles/{$role->id}/access")->assertOk()->assertSee('role-access.js');
        $this->get('/menus')->assertOk()->assertSee('menus.js');
    }

    public function test_seeder_is_idempotent_and_gives_administrator_full_access(): void
    {
        $this->seed(AccessControlSeeder::class);
        $this->seed(AccessControlSeeder::class);

        $admin = User::query()->firstWhere('nik', 'admin');
        $role = Role::query()->firstWhere('name', Role::ADMINISTRATOR);

        $this->assertSame(1, User::query()->count());
        $this->assertTrue($admin->roles->contains($role));
        $this->assertSame(Menu::query()->count(), $role->menuAccesses()->count());

        $admin->setActiveRole($role);

        foreach (RoleMenuAccess::ACTIONS as $action) {
            $this->assertTrue($admin->can("users.{$action}"));
            $this->assertTrue($admin->can("menus.{$action}"));
        }
    }
}
