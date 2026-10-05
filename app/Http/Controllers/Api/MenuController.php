<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\MenuRequest;
use App\Models\Menu;
use App\Services\MenuService;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Yajra\DataTables\Facades\DataTables;

class MenuController extends Controller implements HasMiddleware
{
    /**
     * @return array<int, Middleware>
     */
    public static function middleware(): array
    {
        return [
            new Middleware('can:menus.read', only: ['index', 'datatable', 'show']),
            new Middleware('can:menus.create', only: ['store']),
            new Middleware('can:menus.update', only: ['update']),
            new Middleware('can:menus.delete', only: ['destroy']),
        ];
    }

    /**
     * Data tabel (Yajra). Urutan pohon (parent lalu sub menu) dipertahankan,
     * jadi sorting per kolom tidak diaktifkan; pencarian di judul/kode/route.
     */
    public function datatable(MenuService $menuService): JsonResponse
    {
        return DataTables::collection($menuService->flatTree()->map->toArray())
            ->whitelist(['title', 'code', 'route'])
            ->order(fn () => null)
            ->toJson();
    }

    /**
     * Semua menu, urut pohon dengan atribut `depth`.
     * Dipakai untuk kebutuhan non-tabel, misalnya pilihan parent di form menu.
     */
    public function index(MenuService $menuService): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'Data berhasil diambil',
            'data' => $menuService->flatTree()->values(),
        ]);
    }

    public function store(MenuRequest $request): JsonResponse
    {
        $menu = Menu::query()->create($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Menu berhasil dibuat. Atur hak aksesnya di halaman Role.',
            'data' => $menu,
        ], 201);
    }

    public function show(Menu $menu): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'Data berhasil diambil',
            'data' => $menu,
        ]);
    }

    public function update(MenuRequest $request, Menu $menu): JsonResponse
    {
        $menu->update($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Menu berhasil diperbarui',
            'data' => $menu,
        ]);
    }

    public function destroy(Menu $menu): JsonResponse
    {
        if ($menu->children()->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'Menu masih memiliki sub menu. Hapus atau pindahkan sub menu terlebih dahulu.',
            ], 422);
        }

        // Baris hak akses menu ini ikut terhapus (cascade).
        $menu->delete();

        return response()->json([
            'success' => true,
            'message' => 'Menu berhasil dihapus',
            'data' => null,
        ]);
    }
}
