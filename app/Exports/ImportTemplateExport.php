<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\Export;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

/**
 * Template import: sheet pertama untuk diisi, sheet berikutnya berisi petunjuk/referensi.
 * Importer hanya membaca sheet pertama.
 */
class ImportTemplateExport implements Export, WithMultipleSheets
{
    /**
     * @param  array<int, ImportTemplateSheet>  $sheets
     */
    public function __construct(private array $sheets) {}

    /**
     * @return array<int, ImportTemplateSheet>
     */
    public function sheets(): array
    {
        return $this->sheets;
    }
}
