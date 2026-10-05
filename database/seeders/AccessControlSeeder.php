<?php

namespace Database\Seeders;

use App\Models\Menu;
use App\Models\Role;
use App\Models\RoleMenuAccess;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Data awal akses: menu bawaan, role Administrator (akses penuh ke semua menu),
 * dan satu user administrator.
 *
 * Idempotent: aman dijalankan ulang, tidak menimpa password user yang sudah ada.
 */
class AccessControlSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->seedMenus();

        $administrator = Role::query()->firstOrCreate(
            ['name' => Role::ADMINISTRATOR],
            ['description' => 'Akses penuh ke seluruh menu'],
        );

        $fullAccess = array_fill_keys(array_map(fn (string $action) => "can_{$action}", RoleMenuAccess::ACTIONS), true);

        Menu::query()->pluck('id')->each(fn (int $menuId) => RoleMenuAccess::query()->updateOrCreate(
            ['role_id' => $administrator->id, 'menu_id' => $menuId],
            $fullAccess,
        ));

        // Ganti password ini setelah login pertama.
        $admin = User::query()->firstOrCreate(
            ['nik' => 'admin'],
            ['name' => 'Administrator', 'email' => 'admin@example.com', 'password' => 'password'],
        );

        $admin->roles()->syncWithoutDetaching([$administrator->id]);
    }

    private function seedMenus(): void
    {
        $menus = [
            ['code' => 'dashboard', 'title' => 'Dashboard', 'icon' => 'bx bx-home-smile', 'route' => 'dashboard', 'sort_order' => 1],
            ['code' => 'example', 'title' => 'Contoh Halaman', 'icon' => 'bx bx-data', 'route' => 'example.index', 'sort_order' => 2],
            ['code' => 'settings', 'title' => 'Pengaturan', 'icon' => 'bx bx-cog', 'route' => null, 'sort_order' => 99, 'children' => [
                ['code' => 'users', 'title' => 'User', 'icon' => 'bx bx-user', 'route' => 'users.index', 'sort_order' => 1],
                ['code' => 'roles', 'title' => 'Role & Hak Akses', 'icon' => 'bx bx-shield-quarter', 'route' => 'roles.index', 'sort_order' => 2],
                ['code' => 'menus', 'title' => 'Menu', 'icon' => 'bx bx-menu', 'route' => 'menus.index', 'sort_order' => 3],
                ['code' => 'online-users', 'title' => 'User Online', 'icon' => 'bx bx-wifi', 'route' => 'online-users.index', 'sort_order' => 4],
                ['code' => 'audit-log', 'title' => 'Audit Log', 'icon' => 'bx bx-history', 'route' => 'audit-log.index', 'sort_order' => 5],
                ['code' => 'pulse', 'title' => 'Monitoring Server', 'icon' => 'bx bx-pulse', 'route' => 'pulse', 'sort_order' => 6],
            ]],
        ];

        foreach ($menus as $menu) {
            $children = $menu['children'] ?? [];
            unset($menu['children']);

            $parent = Menu::query()->firstOrCreate(['code' => $menu['code']], $menu);

            foreach ($children as $child) {
                Menu::query()->firstOrCreate(['code' => $child['code']], [...$child, 'parent_id' => $parent->id]);
            }
        }
    }
}
