<?php

namespace App\Http\Controllers\Api\Dpl;

use App\Http\Controllers\Controller;
use App\Http\Requests\Dpl\ApprovalRuleRequest;
use App\Models\Dpl\ApprovalRule;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\DB;
use Yajra\DataTables\Facades\DataTables;

/**
 * Aturan jalur approval item (legacy DPL_ITEM_APPROVAL_RULE). Dicek berurutan sort_order oleh DplApprovalService saat
 * submit; perubahan di sini tidak mengubah approval yang sudah berjalan.
 */
class ApprovalRuleController extends Controller implements HasMiddleware
{
    /**
     * @return array<string, mixed>
     */
    private static function relations(): array
    {
        return [
            'steps' => fn ($query) => $query->orderBy('sequence'),
            'steps.role:id,name',
            'provinces' => fn ($query) => $query->orderBy('name'),
        ];
    }

    /**
     * @return array<int, Middleware>
     */
    public static function middleware(): array
    {
        return [
            new Middleware('can:approval-rules.read', only: ['datatable']),
            new Middleware('can:approval-rules.create', only: ['store']),
            new Middleware('can:approval-rules.update', only: ['update']),
            new Middleware('can:approval-rules.delete', only: ['destroy']),
        ];
    }

    /**
     * Data tabel (Yajra). Pencarian: nama aturan.
     */
    public function datatable(Request $request): JsonResponse
    {
        $query = ApprovalRule::query()->with(self::relations());

        return DataTables::eloquent($query)
            ->whitelist(['sort_order', 'name', 'is_active'])
            ->filter(function (Builder $query) use ($request): void {
                $term = trim((string) $request->input('search.value'));

                if ($term !== '') {
                    $query->whereLike('name', "%{$term}%");
                }
            })
            ->toJson();
    }

    public function store(ApprovalRuleRequest $request): JsonResponse
    {
        $rule = DB::transaction(function () use ($request): ApprovalRule {
            $rule = ApprovalRule::query()->create($request->ruleData());
            $rule->steps()->sync($request->stepIds());
            $rule->provinces()->sync($request->provinceIds());

            return $rule;
        });

        return response()->json([
            'success' => true,
            'message' => 'Aturan approval berhasil dibuat',
            'data' => $rule->load(self::relations()),
        ], 201);
    }

    public function update(ApprovalRuleRequest $request, ApprovalRule $approvalRule): JsonResponse
    {
        DB::transaction(function () use ($request, $approvalRule): void {
            $approvalRule->update($request->ruleData());
            $approvalRule->steps()->sync($request->stepIds());
            $approvalRule->provinces()->sync($request->provinceIds());
        });

        return response()->json([
            'success' => true,
            'message' => 'Aturan approval berhasil diperbarui',
            'data' => $approvalRule->load(self::relations()),
        ]);
    }

    /**
     * Aturan tidak direferensikan approval yang berjalan (jalur sudah di-snapshot ke item state), jadi aman dihapus.
     */
    public function destroy(ApprovalRule $approvalRule): JsonResponse
    {
        DB::transaction(function () use ($approvalRule): void {
            $approvalRule->steps()->detach();
            $approvalRule->provinces()->detach();
            $approvalRule->delete();
        });

        return response()->json(['success' => true, 'message' => 'Aturan approval berhasil dihapus', 'data' => null]);
    }
}
