<?php

namespace App\Http\Controllers\Web\Dpl;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Services\ProductPriceImportService;
use Illuminate\Contracts\View\View;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ProductPriceController extends Controller
{
    /**
     * Pilihan product untuk form.
     */
    public function index(): View
    {
        return view('pages.dpl.product-prices.index', [
            'products' => Product::query()->orderBy('name')->get(['id', 'code', 'name', 'volume', 'is_active']),
        ]);
    }

    /**
     * Template Excel untuk import (sheet Data + Petunjuk).
     */
    public function template(ProductPriceImportService $importService): BinaryFileResponse
    {
        return Excel::download($importService->template(), 'template-import-harga-product.xlsx');
    }
}
