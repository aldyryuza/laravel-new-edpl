<?php

namespace App\Http\Controllers\Web\Dpl;

use App\Http\Controllers\Controller;
use App\Models\Dpl\CommercialParameter;
use Illuminate\Contracts\View\View;

class CommercialParameterController extends Controller
{
    /**
     * Contoh kode parameter dari docs/Schema.md ditampilkan sebagai panduan (tidak di-seed).
     */
    public function index(): View
    {
        return view('pages.dpl.commercial-parameters.index', [
            'suggestedCodes' => CommercialParameter::SUGGESTED_CODES,
        ]);
    }
}
