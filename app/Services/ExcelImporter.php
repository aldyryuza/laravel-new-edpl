<?php

namespace App\Services;

use App\Imports\SheetRowsImport;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;
use Maatwebsite\Excel\Facades\Excel;

/**
 * Dasar import Excel master data: baca sheet pertama (baris 1 = judul kolom), parsing nilai sel,
 * dan pelaporan error per baris. Validasi bisnis + penyimpanan ada di subclass.
 * Error dilempar sebagai ValidationException dengan pesan di key `file`.
 */
abstract class ExcelImporter
{
    public const MAX_ROWS = 5000;

    /** Batas jumlah pesan error yang dikembalikan agar respons tetap ringkas. */
    private const MAX_ERRORS = 100;

    private const TRUE_VALUES = ['1', 'ya', 'y', 'aktif', 'true', 'yes'];

    private const FALSE_VALUES = ['0', 'tidak', 't', 'nonaktif', 'false', 'no'];

    /**
     * Baris data (key = judul kolom yang di-slug) setelah cek file kosong, kolom wajib, dan jumlah baris.
     *
     * @param  array<int, string>  $requiredColumns
     * @return array<int, array<string, mixed>>
     *
     * @throws ValidationException
     */
    protected function readRows(UploadedFile $file, array $requiredColumns): array
    {
        $rows = Excel::toArray(new SheetRowsImport, $file)[0] ?? [];

        if ($rows === []) {
            $this->fail(['File tidak berisi data. Isi sheet pertama mulai baris ke-2 sesuai template.']);
        }

        $missing = array_diff($requiredColumns, array_keys($rows[0]));

        if ($missing !== []) {
            $this->fail(['Kolom wajib tidak ditemukan: '.implode(', ', $missing).'. Gunakan template yang disediakan.']);
        }

        if (count($rows) > self::MAX_ROWS) {
            $this->fail(['Maksimal '.self::MAX_ROWS.' baris per file.']);
        }

        return $rows;
    }

    /**
     * Nomor baris di Excel untuk index data (baris 1 = judul kolom).
     */
    protected function lineNumber(int $index): int
    {
        return $index + 2;
    }

    /**
     * Nilai sel sebagai teks. Angka bulat dari Excel (mis. 101.0) dikembalikan tanpa desimal.
     */
    protected function text(mixed $value): string
    {
        if (is_float($value) && floor($value) === $value) {
            return (string) (int) $value;
        }

        return trim((string) $value);
    }

    /**
     * Kosong = $default. Mengembalikan null bila nilai tidak dikenali.
     */
    protected function boolean(mixed $value, bool $default = true): ?bool
    {
        $value = mb_strtolower($this->text(is_bool($value) ? (int) $value : $value));

        return match (true) {
            $value === '' => $default,
            in_array($value, self::TRUE_VALUES, true) => true,
            in_array($value, self::FALSE_VALUES, true) => false,
            default => null,
        };
    }

    /**
     * Teks kode harus dipakai sebagai string: key array PHP untuk kode numerik ("101") berubah menjadi integer.
     *
     * @param  array<array-key, mixed>  $records
     * @return array<int, string>
     */
    protected function codes(array $records): array
    {
        return array_map('strval', array_keys($records));
    }

    /**
     * @param  array<int, string>  $errors
     *
     * @throws ValidationException
     */
    protected function fail(array $errors): never
    {
        $count = count($errors);
        $messages = array_slice($errors, 0, self::MAX_ERRORS);

        if ($count > self::MAX_ERRORS) {
            $messages[] = '... dan '.($count - self::MAX_ERRORS).' error lainnya.';
        }

        throw ValidationException::withMessages(['file' => $messages]);
    }
}
