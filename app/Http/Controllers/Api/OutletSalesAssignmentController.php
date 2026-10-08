<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ExcelImportRequest;
use App\Http\Requests\OutletSalesAssignmentRequest;
use App\Models\Outlet;
use App\Models\OutletSalesAssignment;
use App\Models\User;
use App\Services\OutletSalesAssignmentImportService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Yajra\DataTables\Facades\DataTables;

/**
 * PIC outlet (AM / SPV), legacy dpl_map_outlet_users. Menentukan pemilik DPL anak konsolidasi Group RS.
 */
class OutletSalesAssignmentController extends Controller implements HasMiddleware
{
    private const RELATIONS = ['outlet:id,code,name', 'am:id,nik,name', 'spv:id,nik,name'];

    /**
     * @return array<int, Middleware>
     */
    public static function middleware(): array
    {
        return [
            new Middleware('can:outlet-sales-assignments.read', only: ['datatable', 'outletOptions', 'userOptions']),
            new Middleware('can:outlet-sales-assignments.create', only: ['store']),
            new Middleware('can:outlet-sales-assignments.update', only: ['update']),
            new Middleware('can:outlet-sales-assignments.delete', only: ['destroy']),
            // Import bisa membuat dan memperbarui data sekaligus.
            new Middleware(['can:outlet-sales-assignments.create', 'can:outlet-sales-assignments.update'], only: ['import']),
        ];
    }

    /**
     * Data tabel (Yajra). Pencarian: kode/nama outlet, NIK/nama AM atau SPV.
     */
    public function datatable(Request $request): JsonResponse
    {
        $query = OutletSalesAssignment::query()
            ->select(['id', 'outlet_id', 'am_user_id', 'spv_user_id', 'updated_at'])
            ->with(self::RELATIONS);

        return DataTables::eloquent($query)
            ->whitelist(['updated_at'])
            ->filter(function (Builder $query) use ($request): void {
                $term = trim((string) $request->input('search.value'));

                if ($term !== '') {
                    $user = fn (Builder $user) => $user->whereLike('nik', "%{$term}%")->orWhereLike('name', "%{$term}%");

                    $query->where(fn (Builder $query) => $query
                        ->whereHas('outlet', fn (Builder $outlet) => $outlet->whereLike('code', "%{$term}%")->orWhereLike('name', "%{$term}%"))
                        ->orWhereHas('am', $user)
                        ->orWhereHas('spv', $user));
                }
            })
            ->toJson();
    }

    /**
     * Pilihan outlet aktif untuk select2 AJAX (maks. 20), cari kode/nama.
     */
    public function outletOptions(Request $request): JsonResponse
    {
        $term = trim((string) $request->input('q'));

        $outlets = Outlet::query()
            ->where('is_active', true)
            ->when($term !== '', fn (Builder $query) => $query->where(fn (Builder $query) => $query
                ->whereLike('code', "%{$term}%")
                ->orWhereLike('name', "%{$term}%")))
            ->orderBy('name')
            ->limit(20)
            ->get(['id', 'code', 'name']);

        return response()->json([
            'success' => true,
            'message' => 'Data berhasil diambil',
            'data' => $outlets->map(fn (Outlet $outlet) => ['id' => $outlet->id, 'text' => "{$outlet->name} ({$outlet->code})"]),
        ]);
    }

    /**
     * Pilihan user aktif untuk select2 AJAX (maks. 20), cari NIK/nama.
     */
    public function userOptions(Request $request): JsonResponse
    {
        $term = trim((string) $request->input('q'));

        $users = User::query()
            ->where('is_active', true)
            ->when($term !== '', fn (Builder $query) => $query->where(fn (Builder $query) => $query
                ->whereLike('nik', "%{$term}%")
                ->orWhereLike('name', "%{$term}%")))
            ->orderBy('name')
            ->limit(20)
            ->get(['id', 'nik', 'name']);

        return response()->json([
            'success' => true,
            'message' => 'Data berhasil diambil',
            'data' => $users->map(fn (User $user) => ['id' => $user->id, 'text' => "{$user->name} ({$user->nik})"]),
        ]);
    }

    public function store(OutletSalesAssignmentRequest $request): JsonResponse
    {
        $assignment = OutletSalesAssignment::query()->create($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'PIC outlet berhasil dibuat',
            'data' => $assignment->load(self::RELATIONS),
        ], 201);
    }

    public function update(OutletSalesAssignmentRequest $request, OutletSalesAssignment $outletSalesAssignment): JsonResponse
    {
        $outletSalesAssignment->update($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'PIC outlet berhasil diperbarui',
            'data' => $outletSalesAssignment->load(self::RELATIONS),
        ]);
    }

    /**
     * Import Excel (upsert berdasarkan outlet). Bila ada baris error, tidak ada yang disimpan (422, error di `errors.file`).
     */
    public function import(ExcelImportRequest $request, OutletSalesAssignmentImportService $importService): JsonResponse
    {
        $result = $importService->import($request->file('file'));

        return response()->json([
            'success' => true,
            'message' => "Import PIC Outlet selesai: {$result['created']} dibuat, {$result['updated']} diperbarui, {$result['unchanged']} tidak berubah.",
            'data' => $result,
        ]);
    }

    /**
     * DPL anak menyimpan pemiliknya sendiri, jadi aman dihapus.
     */
    public function destroy(OutletSalesAssignment $outletSalesAssignment): JsonResponse
    {
        $outletSalesAssignment->delete();

        return response()->json(['success' => true, 'message' => 'PIC outlet berhasil dihapus', 'data' => null]);
    }
}
