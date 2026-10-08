<?php

namespace App\Services\Dpl;

use App\Models\Dpl\CommercialParameter;

/**
 * Baca parameter komersial aktif (dpl.commercial_parameters), di-cache per instance (per request).
 */
class CommercialParameterReader
{
    /** @var array<string, string|bool|null> */
    private array $cache = [];

    /**
     * Nilai angka (string desimal), null bila parameter tidak ada / nonaktif / bukan angka.
     */
    public function number(string $code): ?string
    {
        $value = $this->raw($code);

        return is_string($value) && is_numeric($value) ? $value : null;
    }

    /**
     * Nilai teks (trim), null bila kosong.
     */
    public function text(string $code): ?string
    {
        $value = $this->raw($code);

        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }

    /**
     * Teks dipisah koma -> daftar huruf besar, mis. "KFTD, udc" -> ['KFTD', 'UDC'].
     *
     * @return array<int, string>
     */
    public function upperList(string $code): array
    {
        return array_values(array_filter(array_map(
            fn (string $item) => mb_strtoupper(trim($item)),
            explode(',', (string) $this->text($code)),
        ), fn (string $item) => $item !== ''));
    }

    private function raw(string $code): string|bool|null
    {
        if (! array_key_exists($code, $this->cache)) {
            $this->cache[$code] = CommercialParameter::query()->where('code', $code)->where('is_active', true)->first()?->typedValue();
        }

        return $this->cache[$code];
    }
}
