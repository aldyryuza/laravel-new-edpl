<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Services\ProductImportService;
use Illuminate\Contracts\View\View;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ProductController extends Controller
{
    /**
     * Pilihan filter volume diambil dari volume yang sudah dipakai product (grouping 500/250/100/25 ml).
     */
    public function index(): View
    {
        return view('pages.products.index', [
            'volumes' => Product::query()->whereNotNull('volume')->distinct()->orderByDesc('volume')->pluck('volume'),
        ]);
    }

    /**
     * Template Excel untuk import (sheet Data + Petunjuk).
     */
    public function template(ProductImportService $importService): BinaryFileResponse
    {
        return Excel::download($importService->template(), 'template-import-product.xlsx');
    }
}
