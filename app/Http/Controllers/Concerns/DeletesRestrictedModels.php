<?php

namespace App\Http\Controllers\Concerns;

use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

/**
 * Hapus data yang dijaga FK RESTRICT: bila masih dipakai data lain, balas 422 alih-alih error 500.
 */
trait DeletesRestrictedModels
{
    /** SQLSTATE PostgreSQL untuk pelanggaran foreign key. */
    private const FOREIGN_KEY_VIOLATION = '23503';

    /**
     * @param  (Closure(): void)|null  $beforeDelete  hapus data turunan milik model (mis. histori) dalam transaksi yang sama
     */
    protected function deleteOrReject(Model $model, string $deletedMessage, string $inUseMessage, ?Closure $beforeDelete = null): JsonResponse
    {
        // Transaction (savepoint bila bersarang) menjaga koneksi PostgreSQL tetap bisa dipakai setelah gagal.
        try {
            DB::transaction(function () use ($model, $beforeDelete): void {
                $beforeDelete?->__invoke();
                $model->delete();
            });
        } catch (QueryException $exception) {
            if ($exception->getCode() !== self::FOREIGN_KEY_VIOLATION) {
                throw $exception;
            }

            return response()->json(['success' => false, 'message' => $inUseMessage], 422);
        }

        return response()->json(['success' => true, 'message' => $deletedMessage, 'data' => null]);
    }
}
