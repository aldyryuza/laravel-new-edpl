<?php

namespace Tests\Feature;

use App\Models\Menu;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoleManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_role_can_be_created_with_unique_name(): void
    {
        $actor = $this->userWithAccess(['roles' => ['create']]);

        $this->actingAs($actor)->postJson('/api/roles', ['name' => 'Approver'])->assertCreated();

        $this->actingAs($actor)
            ->postJson('/api/roles', ['name' => 'Approver'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('name');
    }

    public function test_role_used_by_user_cannot_be_deleted(): void
    {
        $actor = $this->userWithAccess(['roles' => ['delete']]);
        $role = Role::factory()->create();
        User::factory()->create()->roles()->attach($role);

        $this->actingAs($actor)->deleteJson("/api/roles/{$role->id}")->assertUnprocessable();
        $this->assertModelExists($role);

        $role->users()->detach();

        $this->actingAs($actor)->deleteJson("/api/roles/{$role->id}")->assertOk();
        $this->assertModelMissing($role);
    }

    public function test_access_lists_every_menu_with_flags(): void
    {
        $actor = $this->userWithAccess(['roles' => ['read']]);
        $role = Role::factory()->create();
        $parent = Menu::factory()->create(['sort_order' => 1]);
        $child = Menu::factory()->create(['parent_id' => $parent->id]);
        $role->menuAccesses()->create(['menu_id' => $child->id, 'can_read' => true, 'can_approve' => true]);

        $response = $this->actingAs($actor)->getJson("/api/roles/{$role->id}/access")->assertOk();

        $rows = collect($response->json('data'))->keyBy('menu_id');
        $this->assertCount(Menu::query()->count(), $rows);
        $this->assertSame(1, $rows[$child->id]['depth']);
        $this->assertTrue($rows[$child->id]['can_read']);
        $this->assertTrue($rows[$child->id]['can_approve']);
        $this->assertFalse($rows[$child->id]['can_delete']);
        $this->assertFalse($rows[$parent->id]['can_read']);
    }

    public function test_saving_access_grants_permission_to_users_of_the_role(): void
    {
        $actor = $this->userWithAccess(['roles' => ['update']]);
        $menu = Menu::factory()->create(['code' => 'dpl']);
        $role = Role::factory()->create();
        $member = User::factory()->create();
        $member->roles()->attach($role);
        $member->setActiveRole($role);

        $this->assertFalse($member->can('dpl.approve'));

        $this->actingAs($actor)
            ->putJson("/api/roles/{$role->id}/access", [
                'access' => [['menu_id' => $menu->id, 'can_read' => true, 'can_approve' => true]],
            ])
            ->assertOk();

        $member->flushMenuAccess();
        $this->assertTrue($member->can('dpl.read'));
        $this->assertTrue($member->can('dpl.approve'));
        $this->assertFalse($member->can('dpl.delete'));
    }

    public function test_saving_access_requires_update_permission(): void
    {
        $role = Role::factory()->create();
        $menu = Menu::factory()->create();

        $this->actingAs($this->userWithAccess(['roles' => ['read']]))
            ->putJson("/api/roles/{$role->id}/access", ['access' => [['menu_id' => $menu->id, 'can_read' => true]]])
            ->assertForbidden();
    }

    public function test_only_active_role_access_applies(): void
    {
        $menu = Menu::factory()->create(['code' => 'report']);
        $reader = Role::factory()->create();
        $printer = Role::factory()->create();
        $reader->menuAccesses()->create(['menu_id' => $menu->id, 'can_read' => true]);
        $printer->menuAccesses()->create(['menu_id' => $menu->id, 'can_print' => true]);

        $user = User::factory()->create();
        $user->roles()->attach([$reader->id, $printer->id]);

        $this->assertFalse($user->can('report.read'), 'Tanpa role aktif tidak ada hak akses.');

        $user->setActiveRole($reader);
        $this->assertTrue($user->can('report.read'));
        $this->assertFalse($user->can('report.print'));

        $user->setActiveRole($printer);
        $this->assertFalse($user->can('report.read'));
        $this->assertTrue($user->can('report.print'));
    }
}
