<?php

namespace App\Http\Controllers\Web\Dpl;

use App\Http\Controllers\Controller;
use App\Models\Dpl\ProductPriceOverride;
use App\Models\OutletGroup;
use App\Models\Product;
use App\Services\ProductPriceOverrideImportService;
use Illuminate\Contracts\View\View;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ProductPriceOverrideController extends Controller
{
    /**
     * Pilihan product, group RS, dan jenis harga yang sudah dipakai (untuk saran input).
     */
    public function index(): View
    {
        return view('pages.dpl.special-prices.index', [
            'products' => Product::query()->orderBy('name')->get(['id', 'code', 'name', 'is_active']),
            'groups' => OutletGroup::query()->orderBy('name')->get(['id', 'code', 'name', 'is_active']),
            'priceTypes' => ProductPriceOverride::query()->distinct()->orderBy('price_type')->pluck('price_type'),
        ]);
    }

    public function template(ProductPriceOverrideImportService $importService): BinaryFileResponse
    {
        return Excel::download($importService->template(), 'template-import-harga-khusus.xlsx');
    }
}
