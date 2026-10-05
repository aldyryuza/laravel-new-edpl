<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\RoleRequest;
use App\Models\Role;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class RoleController extends Controller implements HasMiddleware
{
    /**
     * @return array<int, Middleware>
     */
    public static function middleware(): array
    {
        return [
            new Middleware('can:roles.read', only: ['index', 'show']),
            new Middleware('can:roles.create', only: ['store']),
            new Middleware('can:roles.update', only: ['update']),
            new Middleware('can:roles.delete', only: ['destroy']),
        ];
    }

    /**
     * Jumlah role kecil, jadi dikembalikan semua tanpa pagination.
     */
    public function index(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'Data berhasil diambil',
            'data' => Role::query()->withCount('users')->orderBy('name')->get(),
        ]);
    }

    public function store(RoleRequest $request): JsonResponse
    {
        $role = Role::query()->create($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Role berhasil dibuat',
            'data' => $role,
        ], 201);
    }

    public function show(Role $role): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'Data berhasil diambil',
            'data' => $role->loadCount('users'),
        ]);
    }

    public function update(RoleRequest $request, Role $role): JsonResponse
    {
        $role->update($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Role berhasil diperbarui',
            'data' => $role,
        ]);
    }

    public function destroy(Role $role): JsonResponse
    {
        if ($role->users()->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'Role masih dipakai oleh user. Lepaskan role dari user terlebih dahulu.',
            ], 422);
        }

        $role->delete();

        return response()->json([
            'success' => true,
            'message' => 'Role berhasil dihapus',
            'data' => null,
        ]);
    }
}
