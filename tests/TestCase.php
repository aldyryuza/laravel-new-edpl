<?php

namespace Tests;

use App\Models\Menu;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * Buat user dengan satu role baru yang punya hak akses tertentu.
     * Menu dibuat jika kode menu belum ada.
     *
     * @param  array<string, array<int, string>>  $access  contoh: ['users' => ['read', 'create']]
     */
    protected function userWithAccess(array $access): User
    {
        $role = Role::factory()->create();

        foreach ($access as $code => $actions) {
            $menu = Menu::query()->firstWhere('code', $code) ?? Menu::factory()->create(['code' => $code]);

            $role->menuAccesses()->create([
                'menu_id' => $menu->id,
                ...array_fill_keys(array_map(fn (string $action) => "can_{$action}", $actions), true),
            ]);
        }

        $user = User::factory()->create();
        $user->roles()->attach($role);

        return $user;
    }
}
