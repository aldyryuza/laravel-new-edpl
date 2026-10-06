<?php

namespace App\Models\Dpl;

use App\Models\OutletGroup;
use App\Models\Product;
use App\Support\AuditLog;
use Database\Factories\Dpl\ProductPriceOverrideFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * Harga khusus (legacy dpl_price_product_special), mis. JKN/PADMA per group outlet.
 */
#[Table('dpl.product_price_overrides')]
#[Fillable([
    'price_type', 'outlet_group_id', 'product_id', 'price',
    'valid_from', 'valid_until', 'is_active', 'note',
])]
class ProductPriceOverride extends Model
{
    /** @use HasFactory<ProductPriceOverrideFactory> */
    use HasFactory, LogsActivity;

    public function getActivitylogOptions(): LogOptions
    {
        return AuditLog::dataOptions(['price_type', 'outlet_group_id', 'product_id', 'price', 'valid_from', 'valid_until', 'is_active', 'note'], fn () => "Harga khusus {$this->price_type} product {$this->product?->code}");
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'price' => 'decimal:4',
            'valid_from' => 'date:Y-m-d',
            'valid_until' => 'date:Y-m-d',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<OutletGroup, $this>
     */
    public function outletGroup(): BelongsTo
    {
        return $this->belongsTo(OutletGroup::class);
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
