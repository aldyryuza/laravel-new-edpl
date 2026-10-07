<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\DeletesRestrictedModels;
use App\Http\Controllers\Controller;
use App\Http\Requests\TeamRequest;
use App\Models\Team;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Yajra\DataTables\Facades\DataTables;

/**
 * Master team sales (mis. team produk). Dipakai pada penugasan wilayah user.
 */
class TeamController extends Controller implements HasMiddleware
{
    use DeletesRestrictedModels;

    /**
     * @return array<int, Middleware>
     */
    public static function middleware(): array
    {
        return [
            new Middleware('can:teams.read', only: ['datatable']),
            new Middleware('can:teams.create', only: ['store']),
            new Middleware('can:teams.update', only: ['update']),
            new Middleware('can:teams.delete', only: ['destroy']),
        ];
    }

    public function datatable(): JsonResponse
    {
        $query = Team::query()
            ->select(['teams.id', 'teams.code', 'teams.name', 'teams.is_active'])
            ->withCount('assignments');

        return DataTables::eloquent($query)
            ->whitelist(['code', 'name', 'is_active'])
            ->toJson();
    }

    public function store(TeamRequest $request): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'Team berhasil dibuat',
            'data' => Team::query()->create($request->validated()),
        ], 201);
    }

    public function update(TeamRequest $request, Team $team): JsonResponse
    {
        $team->update($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Team berhasil diperbarui',
            'data' => $team,
        ]);
    }

    public function destroy(Team $team): JsonResponse
    {
        return $this->deleteOrReject(
            $team,
            'Team berhasil dihapus',
            'Team sudah dipakai pada penugasan wilayah user. Nonaktifkan team ini sebagai gantinya.',
        );
    }
}
