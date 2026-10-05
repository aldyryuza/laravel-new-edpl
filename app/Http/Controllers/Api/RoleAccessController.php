<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateRoleAccessRequest;
use App\Models\Menu;
use App\Models\Role;
use App\Models\RoleMenuAccess;
use App\Services\MenuService;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\DB;

/**
 * Hak akses action (create/read/update/delete/print/approve) per menu untuk satu role.
 */
class RoleAccessController extends Controller implements HasMiddleware
{
    /**
     * @return array<int, Middleware>
     */
    public static function middleware(): array
    {
        return [
            new Middleware('can:roles.read', only: ['show']),
            new Middleware('can:roles.update', only: ['update']),
        ];
    }

    /**
     * Semua menu (urut pohon) beserta flag akses role ini.
     * Menu yang belum punya baris akses dianggap semua flag false.
     */
    public function show(Role $role, MenuService $menuService): JsonResponse
    {
        $accesses = $role->menuAccesses()->get()->keyBy('menu_id');

        $data = $menuService->flatTree()->map(function (Menu $menu) use ($accesses) {
            $row = [
                'menu_id' => $menu->id,
                'code' => $menu->code,
                'title' => $menu->title,
                'route' => $menu->route,
                'depth' => $menu->depth,
                'is_active' => $menu->is_active,
            ];

            foreach (RoleMenuAccess::ACTIONS as $action) {
                $row["can_{$action}"] = (bool) $accesses->get($menu->id)?->{"can_{$action}"};
            }

            return $row;
        });

        return response()->json([
            'success' => true,
            'message' => 'Data berhasil diambil',
            'data' => $data->values(),
        ]);
    }

    /**
     * Simpan flag akses untuk menu yang dikirim. Menu yang tidak dikirim tidak diubah.
     */
    public function update(UpdateRoleAccessRequest $request, Role $role): JsonResponse
    {
        DB::transaction(function () use ($request, $role) {
            foreach ($request->validated('access') as $item) {
                $flags = [];

                foreach (RoleMenuAccess::ACTIONS as $action) {
                    $flags["can_{$action}"] = (bool) ($item["can_{$action}"] ?? false);
                }

                RoleMenuAccess::query()->updateOrCreate(
                    ['role_id' => $role->id, 'menu_id' => $item['menu_id']],
                    $flags,
                );
            }
        });

        return response()->json([
            'success' => true,
            'message' => 'Hak akses berhasil disimpan',
            'data' => null,
        ]);
    }
}
