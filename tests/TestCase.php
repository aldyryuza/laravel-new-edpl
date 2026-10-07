<?php

namespace Tests;

use App\Models\Menu;
use App\Models\Role;
use App\Models\User;
use App\Models\UserOrgAssignment;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * Query string request DataTables server-side (dipakai endpoint Yajra `.../datatable`).
     *
     * @param  array<int, string>  $columns  nama kolom sesuai urutan di tabel
     * @param  array<string, mixed>  $extra  contoh: ['search' => 'budi', 'order' => ['name', 'desc'], 'length' => 2]
     */
    protected function dataTableQuery(array $columns, array $extra = []): string
    {
        [$orderColumn, $orderDir] = $extra['order'] ?? [null, 'asc'];

        $params = [
            'draw' => 1,
            'start' => $extra['start'] ?? 0,
            'length' => $extra['length'] ?? 10,
            'search' => ['value' => $extra['search'] ?? '', 'regex' => 'false'],
            'columns' => array_map(fn (string $name) => [
                'data' => $name, 'name' => $name, 'searchable' => 'true', 'orderable' => 'true',
                'search' => ['value' => '', 'regex' => 'false'],
            ], $columns),
        ];

        if ($orderColumn !== null) {
            $params['order'] = [['column' => (int) array_search($orderColumn, $columns, true), 'dir' => $orderDir]];
        }

        unset($extra['search'], $extra['order'], $extra['start'], $extra['length']);

        return http_build_query([...$params, ...$extra]);
    }

    /**
     * Buat user dengan satu role baru yang punya hak akses tertentu.
     * Menu dibuat jika kode menu belum ada.
     *
     * @param  array<string, array<int, string>>  $access  contoh: ['users' => ['read', 'create']]
     * @param  bool  $national  beri wilayah Nasional (melihat semua outlet/distributor)
     */
    protected function userWithAccess(array $access, bool $national = true): User
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

        if ($national) {
            UserOrgAssignment::factory()->national()->for($user)->create();
        }

        return $user;
    }
}
