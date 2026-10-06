<?php

namespace App\Http\Controllers\Web\Dpl;

use App\Http\Controllers\Controller;
use App\Models\Dpl\PriceRegion;
use App\Services\PriceRegionMappingService;
use Illuminate\Contracts\View\View;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Halaman region harga outlet / distributor (satu view, tipe dari route defaults).
 */
class PriceRegionMappingController extends Controller
{
    public function index(PriceRegionMappingService $mappingService, string $type): View
    {
        $definition = $mappingService->definition($type);

        return view('pages.dpl.price-region-mappings.index', [
            'type' => $type,
            'label' => $definition['label'],
            'permission' => "{$definition['label']}-price-regions",
            'table' => $definition['table'],
            'codeColumn' => $definition['code_column'],
            'priceRegions' => PriceRegion::query()->orderBy('name')->get(['id', 'code', 'name', 'is_active']),
        ]);
    }

    public function template(PriceRegionMappingService $mappingService, string $type): BinaryFileResponse
    {
        $label = $mappingService->definition($type)['label'];

        return Excel::download($mappingService->template($type), "template-import-region-harga-{$label}.xlsx");
    }
}
