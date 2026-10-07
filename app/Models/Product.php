<?php

namespace App\Models;

use App\Models\Dpl\ProductPrice;
use App\Support\AuditLog;
use Database\Factories\ProductFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * Master product. Grouping 1L/500/250/100/25 ml ditentukan dari volume; group lain (mis. IV CAT WING,
 * IV CAT PEN) dari kolom product_group (config dpl.product_groups).
 */
#[Fillable(['code', 'name', 'volume', 'product_group', 'is_branded', 'is_active', 'netsuite_id'])]
class Product extends Model
{
    /** @use HasFactory<ProductFactory> */
    use HasFactory, LogsActivity;

    public function getActivitylogOptions(): LogOptions
    {
        return AuditLog::dataOptions(
            ['code', 'name', 'volume', 'product_group', 'is_branded', 'is_active', 'netsuite_id'],
            fn () => "Product {$this->name} ({$this->code})",
        );
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'volume' => 'decimal:4',
            'is_branded' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Label group product untuk DPL: product_group bila diisi, selain itu group volume (mis. 500ml).
     * Null = product tidak masuk group mana pun (tidak bisa dipilih di DPL).
     */
    public function dplGroup(): ?string
    {
        if ($this->product_group !== null && $this->product_group !== '') {
            return $this->product_group;
        }

        if ($this->volume === null) {
            return null;
        }

        $label = array_search((float) $this->volume, array_map('floatval', config('dpl.volume_groups')), true);

        return $label === false ? null : $label;
    }

    /**
     * Semua label group DPL sesuai urutan tampil: group volume lalu group manual.
     *
     * @return array<int, string>
     */
    public static function dplGroups(): array
    {
        return [...array_keys(config('dpl.volume_groups')), ...config('dpl.product_groups')];
    }

    /**
     * @return HasMany<ProductPrice, $this>
     */
    public function prices(): HasMany
    {
        return $this->hasMany(ProductPrice::class);
    }
}
