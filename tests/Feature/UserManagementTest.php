<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_page_requires_read_access(): void
    {
        $this->actingAs($this->userWithAccess([]))->get('/users')->assertForbidden();

        $this->actingAs($this->userWithAccess(['users' => ['read']]))->get('/users')->assertOk();
    }

    public function test_datatable_is_paginated_searchable_and_sortable(): void
    {
        $actor = $this->userWithAccess(['users' => ['read']]);
        $budi = User::factory()->create(['nik' => '9988776655', 'name' => 'Budi Santoso']);
        $budi->roles()->attach(Role::factory()->create(['name' => 'Sales']));
        User::factory()->create(['name' => 'Zaki']);
        User::factory()->create(['name' => 'Ani']);

        $columns = ['nik', 'name', 'email', 'roles'];

        $this->actingAs($actor)
            ->getJson('/api/users/datatable?'.$this->dataTableQuery($columns, ['search' => 'BUDI']))
            ->assertOk()
            ->assertJsonPath('recordsTotal', 4)
            ->assertJsonPath('recordsFiltered', 1)
            ->assertJsonPath('data.0.nik', '9988776655')
            ->assertJsonPath('data.0.roles.0.name', 'Sales')
            ->assertJsonMissingPath('data.0.password');

        $this->actingAs($actor)
            ->getJson('/api/users/datatable?'.$this->dataTableQuery($columns, ['length' => 2, 'order' => ['name', 'desc']]))
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.name', 'Zaki');
    }

    public function test_datatable_ignores_sorting_by_non_whitelisted_column(): void
    {
        $this->actingAs($this->userWithAccess(['users' => ['read']]))
            ->getJson('/api/users/datatable?'.$this->dataTableQuery(['password'], ['order' => ['password', 'asc']]))
            ->assertOk();
    }

    public function test_store_creates_user_with_roles(): void
    {
        $role = Role::factory()->create();

        $response = $this->actingAs($this->userWithAccess(['users' => ['create']]))->postJson('/api/users', [
            'nik' => '3201000000000001',
            'name' => 'Siti',
            'email' => 'siti@example.com',
            'password' => 'rahasia123',
            'role_ids' => [$role->id],
        ]);

        $response->assertCreated()->assertJsonPath('data.roles.0.id', $role->id);
        $response->assertJsonMissingPath('data.password');

        $user = User::query()->firstWhere('nik', '3201000000000001');
        $this->assertTrue(Hash::check('rahasia123', $user->password));
        $this->assertTrue($user->roles->contains($role));
    }

    public function test_store_requires_create_access(): void
    {
        $this->actingAs($this->userWithAccess(['users' => ['read']]))
            ->postJson('/api/users', [])
            ->assertForbidden();
    }

    public function test_store_rejects_duplicate_nik(): void
    {
        User::factory()->create(['nik' => '111']);

        $this->actingAs($this->userWithAccess(['users' => ['create']]))
            ->postJson('/api/users', ['nik' => '111', 'name' => 'X', 'email' => 'x@example.com', 'password' => 'rahasia123'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('nik');
    }

    public function test_update_without_password_keeps_old_password_and_syncs_roles(): void
    {
        $user = User::factory()->create();
        $user->roles()->attach($oldRole = Role::factory()->create());
        $newRole = Role::factory()->create();
        $oldPassword = $user->password;

        $this->actingAs($this->userWithAccess(['users' => ['update']]))
            ->putJson("/api/users/{$user->id}", [
                'nik' => $user->nik,
                'name' => 'Nama Baru',
                'email' => $user->email,
                'password' => '',
                'role_ids' => [$newRole->id],
            ])
            ->assertOk();

        $user->refresh();
        $this->assertSame('Nama Baru', $user->name);
        $this->assertSame($oldPassword, $user->password);
        $this->assertEquals([$newRole->id], $user->roles()->pluck('roles.id')->all());
        $this->assertNotContains($oldRole->id, $user->roles()->pluck('roles.id')->all());
    }

    public function test_user_cannot_delete_own_account(): void
    {
        $actor = $this->userWithAccess(['users' => ['delete']]);

        $this->actingAs($actor)->deleteJson("/api/users/{$actor->id}")->assertUnprocessable();
        $this->assertModelExists($actor);
    }

    public function test_delete_removes_user(): void
    {
        $user = User::factory()->create();

        $this->actingAs($this->userWithAccess(['users' => ['delete']]))
            ->deleteJson("/api/users/{$user->id}")
            ->assertOk();

        $this->assertModelMissing($user);
    }
}
