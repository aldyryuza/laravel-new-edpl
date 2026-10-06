<?php

namespace App\Http\Controllers\Api\Dpl;

use App\Http\Controllers\Controller;
use App\Http\Requests\Dpl\CommercialParameterRequest;
use App\Models\Dpl\CommercialParameter;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Yajra\DataTables\Facades\DataTables;

/**
 * Parameter komersial configurable (mis. MAX_DPL_DISCOUNT). Nilai diisi user; tidak di-seed.
 */
class CommercialParameterController extends Controller implements HasMiddleware
{
    /**
     * @return array<int, Middleware>
     */
    public static function middleware(): array
    {
        return [
            new Middleware('can:commercial-parameters.read', only: ['datatable']),
            new Middleware('can:commercial-parameters.create', only: ['store']),
            new Middleware('can:commercial-parameters.update', only: ['update']),
            new Middleware('can:commercial-parameters.delete', only: ['destroy']),
        ];
    }

    public function datatable(): JsonResponse
    {
        $query = CommercialParameter::query()->select(['id', 'code', 'value', 'description', 'is_active']);

        return DataTables::eloquent($query)
            ->whitelist(['code', 'is_active'])
            ->addColumn('value_type', fn (CommercialParameter $parameter) => $parameter->valueType())
            ->addColumn('typed_value', fn (CommercialParameter $parameter) => $parameter->typedValue())
            ->toJson();
    }

    public function store(CommercialParameterRequest $request): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'Parameter komersial berhasil dibuat',
            'data' => CommercialParameter::query()->create($request->parameterData()),
        ], 201);
    }

    public function update(CommercialParameterRequest $request, CommercialParameter $commercialParameter): JsonResponse
    {
        $commercialParameter->update($request->parameterData());

        return response()->json([
            'success' => true,
            'message' => 'Parameter komersial berhasil diperbarui',
            'data' => $commercialParameter,
        ]);
    }

    /**
     * Parameter yang masih dipakai program akan dianggap tidak ada; lebih aman dinonaktifkan.
     */
    public function destroy(CommercialParameter $commercialParameter): JsonResponse
    {
        $commercialParameter->delete();

        return response()->json(['success' => true, 'message' => 'Parameter komersial berhasil dihapus', 'data' => null]);
    }
}
