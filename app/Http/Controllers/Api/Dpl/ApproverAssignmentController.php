<?php

namespace App\Http\Controllers\Api\Dpl;

use App\Http\Controllers\Controller;
use App\Http\Requests\Dpl\ApproverAssignmentRequest;
use App\Models\Dpl\ApproverAssignment;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Yajra\DataTables\Facades\DataTables;

/**
 * Master approver DPL per role + scope (region / zona / global).
 * Snapshot approval yang sudah berjalan tidak terpengaruh perubahan di sini.
 */
class ApproverAssignmentController extends Controller implements HasMiddleware
{
    /**
     * @return array<int, Middleware>
     */
    public static function middleware(): array
    {
        return [
            new Middleware('can:approver-assignments.read', only: ['datatable', 'show']),
            new Middleware('can:approver-assignments.create', only: ['store']),
            new Middleware('can:approver-assignments.update', only: ['update']),
            new Middleware('can:approver-assignments.delete', only: ['destroy']),
        ];
    }

    /**
     * Data tabel (Yajra). Pencarian: NIK/nama user, role, region, zona. Filter opsional `role_id`.
     */
    public function datatable(Request $request): JsonResponse
    {
        $query = ApproverAssignment::query()
            ->with(['role:id,name', 'region:id,code,name', 'zone:id,code,name', 'user:id,nik,name,is_active'])
            ->when($request->integer('role_id'), fn (Builder $query, int $roleId) => $query->where('role_id', $roleId));

        return DataTables::eloquent($query)
            ->whitelist(['valid_from', 'valid_until', 'is_active'])
            ->addColumn('scope_type', fn (ApproverAssignment $assignment) => $assignment->scopeType())
            ->filter(function (Builder $query) use ($request): void {
                $term = trim((string) $request->input('search.value'));

                if ($term === '') {
                    return;
                }

                $query->where(fn (Builder $query) => $query
                    ->whereHas('user', fn (Builder $user) => $user->whereLike('nik', "%{$term}%")->orWhereLike('name', "%{$term}%"))
                    ->orWhereHas('role', fn (Builder $role) => $role->whereLike('name', "%{$term}%"))
                    ->orWhereHas('region', fn (Builder $region) => $region->whereLike('name', "%{$term}%")->orWhereLike('code', "%{$term}%"))
                    ->orWhereHas('zone', fn (Builder $zone) => $zone->whereLike('name', "%{$term}%")->orWhereLike('code', "%{$term}%")));
            })
            ->toJson();
    }

    public function store(ApproverAssignmentRequest $request): JsonResponse
    {
        $assignment = ApproverAssignment::query()->create($request->assignmentData());

        return response()->json([
            'success' => true,
            'message' => 'Approver berhasil ditambahkan',
            'data' => $this->present($assignment),
        ], 201);
    }

    public function show(ApproverAssignment $approverAssignment): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'Data berhasil diambil',
            'data' => $this->present($approverAssignment),
        ]);
    }

    public function update(ApproverAssignmentRequest $request, ApproverAssignment $approverAssignment): JsonResponse
    {
        $approverAssignment->update($request->assignmentData());

        return response()->json([
            'success' => true,
            'message' => 'Approver berhasil diperbarui',
            'data' => $this->present($approverAssignment),
        ]);
    }

    /**
     * Assignment tidak direferensikan FK oleh approval (snapshot ada di approval_step_assignees),
     * jadi aman dihapus. Untuk menjaga histori penugasan, lebih disarankan menonaktifkan/mengisi
     * "berlaku sampai".
     */
    public function destroy(ApproverAssignment $approverAssignment): JsonResponse
    {
        $approverAssignment->delete();

        return response()->json([
            'success' => true,
            'message' => 'Approver berhasil dihapus',
            'data' => null,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function present(ApproverAssignment $assignment): array
    {
        $assignment->load(['role:id,name', 'region:id,code,name', 'zone:id,code,name', 'user:id,nik,name,is_active']);

        return [...$assignment->toArray(), 'scope_type' => $assignment->scopeType()];
    }
}
