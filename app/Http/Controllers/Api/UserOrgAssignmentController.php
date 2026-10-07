<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\UserOrgAssignmentRequest;
use App\Models\UserOrgAssignment;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Yajra\DataTables\Facades\DataTables;

/**
 * Wilayah & team user (Nasional / zona / region / area). Menentukan outlet dan distributor yang terlihat user.
 */
class UserOrgAssignmentController extends Controller implements HasMiddleware
{
    /** Relasi yang ditampilkan di tabel/form. */
    private const RELATIONS = ['user:id,nik,name,is_active', 'zone:id,code,name', 'region:id,code,name', 'area:id,code,name', 'team:id,code,name'];

    /**
     * @return array<int, Middleware>
     */
    public static function middleware(): array
    {
        return [
            new Middleware('can:user-assignments.read', only: ['datatable']),
            new Middleware('can:user-assignments.create', only: ['store']),
            new Middleware('can:user-assignments.update', only: ['update']),
            new Middleware('can:user-assignments.delete', only: ['destroy']),
        ];
    }

    /**
     * Data tabel (Yajra). Pencarian: NIK/nama user, zona, region, area, team. Filter opsional `team_id`.
     */
    public function datatable(Request $request): JsonResponse
    {
        $query = UserOrgAssignment::query()
            ->with(self::RELATIONS)
            ->when($request->integer('team_id'), fn (Builder $query, int $teamId) => $query->where('team_id', $teamId));

        $matchesOrg = fn (string $term) => fn (Builder $org) => $org->whereLike('name', "%{$term}%")->orWhereLike('code', "%{$term}%");

        return DataTables::eloquent($query)
            ->whitelist(['valid_from', 'valid_until', 'is_active'])
            ->addColumn('scope_type', fn (UserOrgAssignment $assignment) => $assignment->scopeType())
            ->filter(function (Builder $query) use ($request, $matchesOrg): void {
                $term = trim((string) $request->input('search.value'));

                if ($term === '') {
                    return;
                }

                $query->where(fn (Builder $query) => $query
                    ->whereHas('user', fn (Builder $user) => $user->whereLike('nik', "%{$term}%")->orWhereLike('name', "%{$term}%"))
                    ->orWhereHas('zone', $matchesOrg($term))
                    ->orWhereHas('region', $matchesOrg($term))
                    ->orWhereHas('area', $matchesOrg($term))
                    ->orWhereHas('team', $matchesOrg($term)));
            })
            ->toJson();
    }

    public function store(UserOrgAssignmentRequest $request): JsonResponse
    {
        $assignment = UserOrgAssignment::query()->create($request->assignmentData());

        return response()->json([
            'success' => true,
            'message' => 'Wilayah user berhasil ditambahkan',
            'data' => $this->present($assignment),
        ], 201);
    }

    public function update(UserOrgAssignmentRequest $request, UserOrgAssignment $userAssignment): JsonResponse
    {
        $userAssignment->update($request->assignmentData());

        return response()->json([
            'success' => true,
            'message' => 'Wilayah user berhasil diperbarui',
            'data' => $this->present($userAssignment),
        ]);
    }

    /**
     * Tidak direferensikan FK lain, jadi aman dihapus. Untuk menjaga histori, lebih disarankan
     * menonaktifkan/mengisi "berlaku sampai".
     */
    public function destroy(UserOrgAssignment $userAssignment): JsonResponse
    {
        $userAssignment->delete();

        return response()->json([
            'success' => true,
            'message' => 'Wilayah user berhasil dihapus',
            'data' => null,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function present(UserOrgAssignment $assignment): array
    {
        return [...$assignment->load(self::RELATIONS)->toArray(), 'scope_type' => $assignment->scopeType()];
    }
}
