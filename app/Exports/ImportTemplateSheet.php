<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithCustomValueBinder;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\StringValueBinder;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;

/**
 * Satu sheet template import. Semua kolom berformat teks agar kode seperti "001"
 * tidak diubah Excel menjadi angka 1.
 */
class ImportTemplateSheet extends StringValueBinder implements FromArray, ShouldAutoSize, WithColumnFormatting, WithCustomValueBinder, WithHeadings, WithTitle
{
    /**
     * @param  array<int, string>  $headings
     * @param  array<int, array<int, string|null>>  $rows
     */
    public function __construct(
        private string $title,
        private array $headings,
        private array $rows = [],
    ) {}

    /**
     * @return array<int, array<int, string|null>>
     */
    public function array(): array
    {
        return $this->rows;
    }

    /**
     * @return array<int, string>
     */
    public function headings(): array
    {
        return $this->headings;
    }

    public function title(): string
    {
        return $this->title;
    }

    /**
     * @return array<string, string>
     */
    public function columnFormats(): array
    {
        return ['A:'.Coordinate::stringFromColumnIndex(count($this->headings)) => NumberFormat::FORMAT_TEXT];
    }
}
