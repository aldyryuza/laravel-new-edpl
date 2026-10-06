<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\DeletesRestrictedModels;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Models\User;
use App\Support\AuditLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\DB;
use Yajra\DataTables\Facades\DataTables;

class UserController extends Controller implements HasMiddleware
{
    use DeletesRestrictedModels;

    /**
     * @return array<int, Middleware>
     */
    public static function middleware(): array
    {
        return [
            new Middleware('can:users.read', only: ['datatable', 'show']),
            new Middleware('can:users.create', only: ['store']),
            new Middleware('can:users.update', only: ['update']),
            new Middleware('can:users.delete', only: ['destroy']),
        ];
    }

    /**
     * Data tabel (Yajra, protokol DataTables server-side).
     * Pencarian memakai scope `search` yang sama dengan ekspor Excel.
     */
    public function datatable(Request $request): JsonResponse
    {
        $query = User::query()
            ->select(['users.id', 'users.nik', 'users.name', 'users.email', 'users.is_active', 'users.last_login_at', 'users.created_at'])
            ->with('roles:id,name');

        return DataTables::eloquent($query)
            ->whitelist(['nik', 'name', 'email', 'is_active', 'last_login_at', 'created_at'])
            ->filter(fn ($query) => $query->search($request->input('search.value')))
            // Kembalikan array (bukan Collection): Yajra mengubah objek menjadi string.
            ->editColumn('roles', fn (User $user) => $user->roles->map->only(['id', 'name'])->values()->all())
            ->toJson();
    }

    public function store(StoreUserRequest $request): JsonResponse
    {
        $user = DB::transaction(function () use ($request) {
            $user = User::query()->create($request->safe()->except('role_ids'));
            $this->syncRoles($user, $request->validated('role_ids', []));

            return $user;
        });

        return response()->json([
            'success' => true,
            'message' => 'User berhasil dibuat',
            'data' => $user->load('roles:id,name'),
        ], 201);
    }

    public function show(User $user): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'Data berhasil diambil',
            'data' => $user->load('roles:id,name'),
        ]);
    }

    public function update(UpdateUserRequest $request, User $user): JsonResponse
    {
        $data = $request->safe()->except(['role_ids', 'password']);

        if ($request->filled('password')) {
            $data['password'] = $request->validated('password');
        }

        DB::transaction(function () use ($request, $user, $data) {
            $user->update($data);
            $this->syncRoles($user, $request->validated('role_ids', []));

            if (isset($data['password'])) {
                activity(AuditLog::AUTH)
                    ->event('password_reset')
                    ->performedOn($user)
                    ->withProperties(AuditLog::requestContext())
                    ->log("Password user {$user->name} ({$user->nik}) direset admin");
            }
        });

        return response()->json([
            'success' => true,
            'message' => 'User berhasil diperbarui',
            'data' => $user->load('roles:id,name'),
        ]);
    }

    /**
     * Sinkronkan role user. Tabel pivot tidak memicu model event, jadi
     * perubahannya dicatat manual ke audit log (hanya jika ada perubahan).
     *
     * @param  array<int, int|string>  $roleIds
     */
    private function syncRoles(User $user, array $roleIds): void
    {
        $before = $user->roles()->orderBy('name')->pluck('name')->all();
        $changes = $user->roles()->sync($roleIds);

        if ($changes['attached'] === [] && $changes['detached'] === []) {
            return;
        }

        activity(AuditLog::DATA)
            ->event('roles_updated')
            ->performedOn($user)
            ->withChanges([
                'old' => ['roles' => $before],
                'attributes' => ['roles' => $user->roles()->orderBy('name')->pluck('name')->all()],
            ])
            ->log("Role user {$user->name} ({$user->nik}) diubah");
    }

    public function destroy(Request $request, User $user): JsonResponse
    {
        if ($user->is($request->user())) {
            return response()->json([
                'success' => false,
                'message' => 'Tidak dapat menghapus akun yang sedang dipakai login.',
            ], 422);
        }

        // User yang sudah dipakai data bisnis (DPL, approval, dst.) dijaga FK RESTRICT.
        return $this->deleteOrReject(
            $user,
            'User berhasil dihapus',
            'User sudah dipakai pada data lain sehingga tidak dapat dihapus. Nonaktifkan user ini sebagai gantinya.',
        );
    }
}
